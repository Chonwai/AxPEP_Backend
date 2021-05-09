<?php

namespace App\Utils;

use App\Exports\AmPEPResultExport;
use App\Imports\AmPEPResultImport;
use App\Imports\OutImport;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class FileUtils
{
    public static function createResultFile($path, $methods)
    {
        $methodString = self::prepareMethodString($methods);
        Storage::put($path . 'classification.csv', 'id,' . $methodString . "number_of_positives,sequence\n");
        Storage::put($path . 'score.csv', 'id,' . $methodString . "product_of_probability,sequence\n");
    }

    public static function createAcPEPResultFile($path, $methods)
    {
        $methodString = self::prepareMethodString($methods);
        Storage::put($path . 'classification.csv', 'id,' . $methodString . "sequence\n");
    }

    public static function insertSequencesAndHeaderOnResult($path, $methods, $function = 'AmPEP')
    {
        [$fInput, $fClassification, $fScore, $methodArray] = self::prepareSequencesAndHeaderFile($path, $methods, $function = 'AmPEP');
        self::prepareSequencesAndHeaderContent($fInput, $fClassification, $fScore, $methodArray, $function);
    }

    public static function loadResultFile($id, $methods)
    {
        $classifications = Excel::toArray(new AmPEPResultImport, "Tasks/$id/classification.csv", null, \Maatwebsite\Excel\Excel::CSV);
        $scores = Excel::toArray(new AmPEPResultImport, "Tasks/$id/score.csv", null, \Maatwebsite\Excel\Excel::CSV);
        return [$classifications, $scores];
    }

    public static function loadClassificationsFile($id, $methods)
    {
        $classifications = Excel::toArray(new AmPEPResultImport, "Tasks/$id/classification.csv", null, \Maatwebsite\Excel\Excel::CSV);
        return $classifications;
    }

    public static function writeAmPEPResultFile($id, $methods)
    {
        [$classifications, $scores] = self::loadResultFile($id, $methods);

        foreach ($methods as $key => $value) {
            [$classifications, $scores] = self::matchingAmPEP($id, $value->method, $classifications, $scores);
        }

        [$classifications, $scores] = self::calculateResultFile($classifications, $scores, $methods);

        Excel::store(new AmPEPResultExport($classifications[0]), "Tasks/$id/classification.csv", null, \Maatwebsite\Excel\Excel::CSV);
        Excel::store(new AmPEPResultExport($scores[0]), "Tasks/$id/score.csv", null, \Maatwebsite\Excel\Excel::CSV);
    }

    public static function writeAcPEPResultFile($id, $methods)
    {
        $classifications = self::loadClassificationsFile($id, $methods);

        foreach ($methods as $key => $value) {
            $classifications = self::matchingAcPEP($id, $value->method, $classifications);
        }

        Excel::store(new AmPEPResultExport($classifications[0]), "Tasks/$id/classification.csv", null, \Maatwebsite\Excel\Excel::CSV);
    }

    public static function matchingAmPEP($id, $method, $classifications, $scores)
    {
        $fResult = Excel::toArray(new OutImport, "Tasks/$id/$method.out", null, \Maatwebsite\Excel\Excel::TSV);
        $symbol = " ";
        foreach ($fResult[0] as $key => $value) {
            $result = explode($symbol, $value[0]);
            $classifications[0] = array_map(function ($value) use ($result, $method) {
                if ($value['id'] == $result[0]) {
                    $value["$method"] = $result[1];
                }
                return $value;
            }, $classifications[0]);
            $scores[0] = array_map(function ($value) use ($result, $method) {
                if ($value['id'] == $result[0]) {
                    $value["$method"] = $result[2];
                }
                return $value;
            }, $scores[0]);
        }
        return [$classifications, $scores];
    }

    public static function matchingAcPEP($id, $method, $classifications)
    {
        $fResult = Excel::toArray(new OutImport, "Tasks/$id/$method.out", null, \Maatwebsite\Excel\Excel::CSV);
        array_shift($fResult[0]);
        foreach ($fResult[0] as $key => $value) {
            $classifications[0] = array_map(function ($val) use ($value, $method) {
                if ($val['id'] === $value[0]) {
                    $val["$method"] = $value[1];
                }
                return $val;
            }, $classifications[0]);
        }
        return $classifications;
    }

    public static function calculateResultFile($classifications, $scores, $methods)
    {
        $classifications[0] = array_map(function ($value) use ($methods) {
            $numberOfPositives = '0';
            foreach ($methods as $key => $method) {
                if ($value["$method->method"] == "1") {
                    $numberOfPositives++;
                }
            }
            $value['number_of_positives'] = $numberOfPositives;
            return $value;
        }, $classifications[0]);

        $scores[0] = array_map(function ($value) use ($methods) {
            $productOfProbability = 1;
            foreach ($methods as $key => $method) {
                $productOfProbability = $productOfProbability * $value["$method->method"];
            }
            $value['product_of_probability'] = $productOfProbability;
            return $value;
        }, $scores[0]);

        return [$classifications, $scores];
    }

    private static function prepareMethodString($methods)
    {
        $methodString = '';
        foreach ($methods as $key => $value) {
            $methodString = $methodString . "$value,";
        }
        return $methodString;
    }

    private static function prepareSequencesAndHeaderFile($path, $methods, $function = 'AmPEP')
    {
        $methodArray = [];
        foreach ($methods as $key => $value) {
            array_push($methodArray, '');
        }
        $fInput = fopen($path . 'input.fasta', 'r');
        $fClassification = fopen($path . 'classification.csv', 'a+');

        switch ($function) {
            case 'AmPEP':
                $fScore = fopen($path . 'score.csv', 'a+');
                break;
            case 'AcPEP':
                $fScore = null;
                break;
            default:
                $fScore = fopen($path . 'score.csv', 'a+');
                break;
        }

        return [$fInput, $fClassification, $fScore, $methodArray];
    }

    private static function prepareSequencesAndHeaderContent($fInput, $fClassification, $fScore, $methodArray, $function = 'AmPEP')
    {
        $i = 0;
        $id = '';
        while ($line = fgets($fInput)) {
            $i++;
            if ($i % 2 == 1) {
                $id = ltrim($line, ">");
                $id = ltrim(str_replace("\r\n", '', $id));
                $id = ltrim(str_replace("\n", '', $id));
                $id = ltrim(str_replace(PHP_EOL, '', $id));
            } else {
                $sequence = ltrim($line);
                $sequence = ltrim(str_replace("\r\n", '', $sequence));
                $sequence = ltrim(str_replace("\n", '', $sequence));
                $sequence = ltrim(str_replace(PHP_EOL, '', $sequence));
                if ($function == 'AmPEP') {
                    fputcsv($fClassification, array_merge(['id' => $id], $methodArray, ['number_of_positives' => ''], ['sequence' => $sequence]));
                    fputcsv($fScore, array_merge(['id' => $id], $methodArray, ['product_of_probability' => ''], ['sequence' => $sequence]));
                } elseif ($function == 'AcPEP') {
                    fputcsv($fClassification, array_merge(['id' => $id], $methodArray, ['sequence' => $sequence]));
                }
            }
        }
    }
}
