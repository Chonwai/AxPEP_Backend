<?php

namespace App\Utils;

use App\Imports\AmPEPResultImport;
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
            } else {
                $sequence = $line;
                fputcsv($fClassification, array_merge(['id' => str_replace(PHP_EOL, '', $id)], $methodArray, ['number_of_positives' => ''], ['sequence' => str_replace(PHP_EOL, '', $sequence)]));
                fputcsv($fScore, array_merge(['id' => str_replace(PHP_EOL, '', $id)], $methodArray, ['product_of_probability' => ''], ['sequence' => str_replace(PHP_EOL, '', $sequence)]));
            }
        }
    }

    public static function writeResultFile($id, $methods)
    {
        foreach ($methods as $key => $value) {
            switch ($value->method) {
                case 'ampep':
                    // $fResult = fopen("../storage/app/Tasks/$id/ampep.out", 'r');
                    $fResult = Excel::toCollection(new AmPEPResultImport, "Tasks/$id/ampep.out", \Maatwebsite\Excel\Excel::TSV);
                    $collection = Excel::toCollection(new AmPEPResultImport, "Tasks/$id/classification.csv", null, \Maatwebsite\Excel\Excel::CSV);
                    echo (json_encode($collection[0]));
                    // echo (json_encode($fResult[0]));
                    break;
                case 'deepampep30':
                    # code...
                    break;
                case 'rfampep30':
                    # code...
                    break;
            }
        }
    }

    // public static function
}
