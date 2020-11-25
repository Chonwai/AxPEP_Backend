<?php

namespace App\Utils;

use App\Imports\AmPEPResultImport;
use App\Imports\OutImport;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class FileUtils
{
    public static function createResultFile($path, $methods)
    {
        $methodString = '';
        foreach ($methods as $key => $value) {
            $methodString = $methodString . "$value,";
        }
        Storage::put($path . 'classification.csv', 'id,' . $methodString . "number_of_positives,sequence\n");
        Storage::put($path . 'score.csv', 'id,' . $methodString . "product_of_probability,sequence\n");
    }

    public static function insertSequencesAndHeaderOnResult($path, $methods)
    {
        $methodArray = [];
        foreach ($methods as $key => $value) {
            array_push($methodArray, '');
        }
        $fInput = fopen($path . 'input.fasta', 'r');
        $fClassification = fopen($path . 'classification.csv', 'a+');
        $fScore = fopen($path . 'score.csv', 'a+');
        $i = 0;
        $id = '';
        while ($line = fgets($fInput)) {
            $i++;
            if ($i % 2 == 1) {
                $id = ltrim($line, ">");
                $id = ltrim(str_replace("\r\n", '', $id));
                echo(json_encode($id));
            } else {
                $sequence = $line;
                $sequence = ltrim(str_replace("\r\n", '', $sequence));
                fputcsv($fClassification, array_merge(['id' => $id], $methodArray, ['number_of_positives' => ''], ['sequence' => ltrim(str_replace(PHP_EOL, '', $sequence))]));
                fputcsv($fScore, array_merge(['id' => $id], $methodArray, ['product_of_probability' => ''], ['sequence' => ltrim(str_replace(PHP_EOL, '', $sequence))]));
            }
        }
    }

    public static function writeResultFile($id, $methods)
    {
        foreach ($methods as $key => $value) {
            self::matching($id, $value->method);
        }
    }

    public static function matching($id, $method) {
        $fResult = Excel::toArray(new OutImport, "Tasks/$id/$method.out", null, \Maatwebsite\Excel\Excel::TSV);
        $classificationArray = Excel::toArray(new AmPEPResultImport, "Tasks/$id/classification.csv", null, \Maatwebsite\Excel\Excel::CSV);
        $scoreArray = Excel::toArray(new AmPEPResultImport, "Tasks/$id/score.csv", null, \Maatwebsite\Excel\Excel::CSV);
        foreach ($fResult[0] as $key => $value) {
            $result = explode(" ", $value[0]);
            $classificationArray[0] = array_map(function($value) use ($result, $method) {
                if ($value['id'] == $result[0]) {
                    $value["$method"] = $result[1];
                }
                return $value;
            }, $classificationArray[0]);
            $scoreArray[0] = array_map(function($value) use ($result, $method) {
                if ($value['id'] == $result[0]) {
                    $value["$method"] = $result[1];
                }
                return $value;
            }, $scoreArray[0]);
        }
    }
}
