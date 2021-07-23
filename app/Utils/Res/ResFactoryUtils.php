<?php

namespace App\Utils\Res;

use App\Utils\Res\Products\ServicesResFail;
use App\Utils\Res\Products\ServicesResSuccess;
use App\Utils\Res\Products\ServicesResUnknownProblems;
use App\Utils\Res\Products\ServicesResUserUpdateFailed;
use App\Utils\Res\ResAbstractFactory;

class ResFactoryUtils extends ResAbstractFactory
{
    public static function getServicesRes($data, $type)
    {
        $res = null;

        switch ($type) {
            case 'success':
                $res = ServicesResSuccess::getInstance()->createServicesRes($data);
                break;
            case 'fail':
                $res = ServicesResFail::getInstance()->createServicesRes($data);
                break;
            case 'unknownProblems':
                $res = ServicesResUnknownProblems::getInstance()->createServicesRes($data);
                break;
            case 'userUpdateFailed':
                $res = ServicesResUserUpdateFailed::getInstance()->createServicesRes($data);
                break;
            default:
                # code...
                break;
        }

        return $res;
    }
}
