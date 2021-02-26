<?php

namespace App\Utils;

class FormatUtils
{
    private $validateChart = 'GAVLIPFYWSTCMNQKRHDEgavlipfywstcmnqkrhde';

    public static function checkFASTAFormat($data)
    {
        $counter = 0;
        $status = true;
        $headerList = [];
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $data) as $line) {
            $counter++;
            if ($counter % 2 == 1) {
                if ($line[0] != '>') {
                    $status = 'FASTA Header is error!';
                    break;
                }
                if (in_array($line, $headerList)) {
                    $status = "FASTA Header " . $line . " has been repeated!";
                    break;
                }
                array_push($headerList, $line);
                if ($line[0] == '>') {
                    continue;
                }
            }
        }
        return $status;
    }

    // public static function checkFASTAFormat($data)
    // {
    //     $counter = 0;
    //     $status = true;
    //     $headerList = [];
    //     $previousLine = '';
    //     $currentLineArray = [];
    //     foreach (preg_split("/((\r?\n)|(\r\n?))/", $data) as $line) {
    //         $counter++;
    //         if ($line[0] == '>') {
    //             $previousLine = $line;
    //             continue;
    //         } elseif ($line[0] != '>') {
    //             $currentLineArray = str_split($line);
    //             foreach ($currentLineArray as $char) {
    //                 if (strpos(self::$validateChart, $char) !== false) {
    //                     echo "true";
    //                     continue;
    //                 }
    //             }
    //         }
    //         // if ($counter % 2 == 1) {
    //         //     if ($line[0] != '>') {
    //         //         $status = 'FASTA Header is error!';
    //         //         break;
    //         //     }
    //         //     if (in_array($line, $headerList)) {
    //         //         $status = "FASTA Header " . $line . " has been repeated!";
    //         //         break;
    //         //     }
    //         //     array_push($headerList, $line);
    //         //     if ($line[0] == '>') {
    //         //         continue;
    //         //     }
    //         // }
    //     }
    //     return $status;
    // }
}
