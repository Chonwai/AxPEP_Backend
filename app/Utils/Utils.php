<?php

namespace App\Utils;

class Utils
{
    public static function integradeResponseMessage($message, $status, $code = 9000)
    {
        $response = [];
        $response['status'] = $status;
        $response['code'] = $code;
        $response['message'] = $message;
        return $response;
    }
}
