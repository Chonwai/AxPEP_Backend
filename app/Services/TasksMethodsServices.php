<?php

namespace App\Services;

use App\DAO\DAOSimpleFactory;
use App\Http\Requests\TasksRules;
use App\Jobs\AmPEPJob;
use App\Utils\ResponseUtils;
use App\Utils\TaskUtils;
use App\Utils\Utils;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TasksMethodsServices implements BaseServicesInterface
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

    public function responseSpecify(Request $request)
    {
        $data = DAOSimpleFactory::createTasksMethodsDAO()->getSpecify($request);

        return $data;
    }

    public function insert($request)
    {
        $data = DAOSimpleFactory::createTasksMethodsDAO()->insert($request);

        return $data;
    }

    public function createNewTaskByFile(Request $request)
    {
        $data = DAOSimpleFactory::createTasksMethodsDAO()->insert($request);

        TaskUtils::createTaskFolder($data);
        Storage::putFileAs("Tasks/$data->id/", $request->file('file'), 'input.fasta');
        Storage::put("Tasks/$data->id/result.csv");
        AmPEPJob::dispatch($data, $request->input())->delay(Carbon::now()->addSeconds(3));

        return $data;
    }
}
