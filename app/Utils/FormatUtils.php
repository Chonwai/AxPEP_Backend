<?php

namespace App\Utils;

class FormatUtils {
    public static function checkFASTAFormat($data) {
        $counter = 0;
        $flag = true;
        foreach(preg_split("/((\r?\n)|(\r\n?))/", $data) as $line){
            $counter++;
            if ($counter % 2 == 1) {
                if ($line[0] == '>') {
                    continue;
                } else {
                    $flag = false;
                    break;
                }
            }
        }
        return $flag;
    }
}