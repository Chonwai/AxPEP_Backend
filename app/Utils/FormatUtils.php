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
            if ($line != '' || $line != null) {
                $counter++;
                if ($counter % 2 == 1) {
                    if ($line[0] != '>' || $line[0] == null) {
                        $status = 'FASTA Header is error!';
                        break;
                    }
                    if (in_array($line, $headerList)) {
                        $status = 'FASTA Header '.$line.' has been repeated!';
                        break;
                    }
                    array_push($headerList, $line);
                    if ($line[0] == '>') {
                        continue;
                    }
                }
            }
        }

        return $status;
    }

    public static function checkAcPEPFASTAFormat($data)
    {
        $counter = 0;
        $status = true;
        $headerList = [];
        $headerAndSequenceList = preg_split("/((\r?\n)|(\r\n?))/", $data);
        foreach ($headerAndSequenceList as $line) {
            if ($line != '' || $line != null) {
                $counter++;
                if ($counter % 2 == 1) {
                    if ($line[0] != '>' || $line[0] == null) {
                        $status = 'FASTA Header is error!';
                        break;
                    }
                    if (in_array($line, $headerList)) {
                        $status = 'FASTA Header '.$line.' has been repeated!';
                        break;
                    }
                    array_push($headerList, $line);
                    if ($line[0] == '>') {
                        continue;
                    }
                } else {
                    if (strlen($line) > 38) {
                        $status = 'The '.$headerAndSequenceList[$counter - 2].' FASTA sequence '.$headerAndSequenceList[$counter - 1].' is error! The sequence is bigger than 38 characters!';
                        break;
                    }
                }
            }
        }

        return $status;
    }
}
