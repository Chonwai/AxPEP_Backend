<?php

namespace App\Utils;

use Illuminate\Support\Facades\Storage;

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
                $id = ltrim($line, '>');
            } else {
                $sequence = $line;
                fputcsv($fClassification, array_merge(['id' => $id], $methodArray, ['number_of_positives' => ''], ['sequence' => $sequence]));
                fputcsv($fScore, array_merge(['id' => $id], $methodArray, ['product_of_probability' => ''], ['sequence' => $sequence]));
            }
        }
    }
}
