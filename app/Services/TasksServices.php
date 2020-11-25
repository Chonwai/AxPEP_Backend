<?php

namespace App\Services;

use App\DAO\DAOSimpleFactory;
use App\Http\Requests\TasksRules;
use App\Jobs\AmPEPJob;
use App\Utils\FileUtils;
use App\Utils\RequestUtils;
use App\Utils\ResponseUtils;
use App\Utils\TaskUtils;
use App\Utils\Utils;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TasksServices implements BaseServicesInterface
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
        if (!(self::$_instance instanceof self)) {
            self::$_instance = new self();
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
                # code...
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
        $data = DAOSimpleFactory::createTasksDAO()->getSpecify($request);
        return $data;
    }

    public function responseSpecifyTaskByEmail(Request $request)
    {
        $data = DAOSimpleFactory::createTasksDAO()->getSpecifyTaskByEmail($request);
        return $data;
    }

    public function createNewTaskByFile(Request $request)
    {
        $data = DAOSimpleFactory::createTasksDAO()->insert($request);
        $methods = $this->insertTasksMethods($request, $data);
        TaskUtils::createTaskFolder($data);
        Storage::putFileAs("Tasks/$data->id/", $request->file('file'), 'input.fasta');
        FileUtils::createResultFile("Tasks/$data->id/", $methods);
        FileUtils::insertSequencesAndHeaderOnResult("../storage/app/Tasks/$data->id/", $methods);
        AmPEPJob::dispatch($data, $request->input())->delay(Carbon::now()->addSeconds(1));
        return $data;
    }

    public function insertTasksMethods($request, $data)
    {
        $methods = [];
        if ($request->ampep == true) {
            RequestUtils::addSpecificInput(['method' => 'ampep', 'task_id' => $data->id]);
            $method = TasksMethodsServices::getInstance()->insert($request);
            array_push($methods, $method->method);
        }
        if ($request->deepampep30 == true) {
            RequestUtils::addSpecificInput(['method' => 'deepampep30', 'task_id' => $data->id]);
            $method = TasksMethodsServices::getInstance()->insert($request);
            array_push($methods, $method->method);

        }
        if ($request->rfampep30 == true) {
            RequestUtils::addSpecificInput(['method' => 'rfampep30', 'task_id' => $data->id]);
            $method = TasksMethodsServices::getInstance()->insert($request);
            array_push($methods, $method->method);
        }
        return $methods;
    }

    public function finishedTask($taskID)
    {
        $data = DAOSimpleFactory::createTasksDAO()->finished($taskID);
        $methods = DAOSimpleFactory::createTasksMethodsDAO()->getSpecifyByTaskID($taskID);
        FileUtils::writeResultFile($taskID, $methods);
        return $data;
    }
}
