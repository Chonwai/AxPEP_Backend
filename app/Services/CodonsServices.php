<?php

namespace App\Services;

use App\DAO\DAOSimpleFactory;
use App\Http\Requests\TasksRules;
use App\Utils\Res\ResFactoryUtils;
use App\Utils\ResponseUtils;
use App\Utils\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CodonsServices
{
    private static $_instance = null;

    private function __construct()
    {
        // Avoid constructing this class on the outside.
    }

    private function __clone()
    {
        // Avoid cloning this class on the outside.
    }

    public static function getInstance()
    {
        if (! (self::$_instance instanceof self)) {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    public function dataValidation($request, $method)
    {
        switch ($method) {
            case 'createNewTaskByFile':
                $validator = Validator::make($request->all(), TasksRules::fileRules());
                break;
            case 'responseSpecifyTaskByEmail':
                $validator = Validator::make($request->all(), TasksRules::emailRules());
                break;
            default:
                // code...
                break;
        }

        if ($validator->fails()) {
            $res = Utils::integradeResponseMessage(ResponseUtils::validatorErrorMessage($validator), false, 1000);

            return $res;
        } else {
            return true;
        }
    }

    public function responseAll()
    {
        $data = DAOSimpleFactory::createCodonsDAO()->getAll();

        return ResFactoryUtils::getServicesRes($data, 'fail');
    }

    public function responseSpecify(Request $request)
    {
        $data = DAOSimpleFactory::createCodonsDAO()->getSpecify($request);

        return ResFactoryUtils::getServicesRes($data, 'fail');
    }

    public function insert($request)
    {
        $data = DAOSimpleFactory::createCodonsDAO()->insert($request);

        return ResFactoryUtils::getServicesRes($data, 'fail');
    }
}
