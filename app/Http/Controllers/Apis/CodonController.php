<?php

namespace App\Http\Controllers\Apis;

use App\DAO\DAOSimpleFactory;
use App\Http\Controllers\Controller;
use App\Services\CodonsServices;
use App\Utils\RequestUtils;
use Illuminate\Http\Request;

class CodonController extends Controller
{
    public function responseAll()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        echo $ip
        // $res = CodonsServices::getInstance()->responseAll();
        // return $res;
    }
}
