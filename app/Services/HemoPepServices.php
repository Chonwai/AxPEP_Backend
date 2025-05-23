<?php

namespace App\Services;

use App\DAO\DAOSimpleFactory;
use App\Http\Requests\TasksRules;
use App\Jobs\HemoPepJob;
use App\Utils\FileUtils;
use App\Utils\RequestUtils;
use App\Utils\Res\ResFactoryUtils;
use App\Utils\ResponseUtils;
use App\Utils\TaskUtils;
use App\Utils\Utils;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class HemoPepServices implements BaseServicesInterface
{
    private static $_instance = null;

    private function __construct()
    {
        // 避免在外部構造此類
    }

    private function __clone()
    {
        // 避免在外部克隆此類
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
            case 'createNewTaskByTextarea':
                $validator = Validator::make($request->all(), TasksRules::textareaRules());
                break;
            case 'createNewTaskByFile':
                $validator = Validator::make($request->all(), TasksRules::fileRules());
                break;
            default:
                $validator = Validator::make($request->all(), TasksRules::rules());
                break;
        }

        if ($validator->fails()) {
            $res = Utils::integradeResponseMessage(ResponseUtils::validatorErrorMessage($validator), false, 1000);

            return $res;
        } else {
            return true;
        }
    }

    public function createNewTaskByFile(Request $request)
    {
        // 確保設置source值
        if (! $request->has('source')) {
            $request->merge(['source' => 'hemopep']);
        }

        $data = DAOSimpleFactory::createTasksDAO()->insert($request);
        $methods = $this->insertTasksMethods($request, $data);
        TaskUtils::createTaskFolder($data);
        Storage::putFileAs("Tasks/$data->id/", $request->file('file'), 'input.fasta');
        FileUtils::createResultFile("Tasks/$data->id/", $methods);
        FileUtils::insertSequencesAndHeaderOnResult("../storage/app/Tasks/$data->id/", $methods, 'HemoPep');
        HemoPepJob::dispatch($data, $request->input())->delay(Carbon::now()->addSeconds(1));

        return ResFactoryUtils::getServicesRes($data, 'fail');
    }

    public function createNewTaskByTextarea(Request $request)
    {
        // 確保設置source值
        if (! $request->has('source')) {
            $request->merge(['source' => 'hemopep']);
        }

        $data = DAOSimpleFactory::createTasksDAO()->insert($request);
        $methods = $this->insertTasksMethods($request, $data);
        TaskUtils::createTaskFolder($data);
        Storage::disk('local')->put("Tasks/$data->id/input.fasta", $request->fasta);
        FileUtils::createResultFile("Tasks/$data->id/", $methods);
        FileUtils::insertSequencesAndHeaderOnResult("../storage/app/Tasks/$data->id/", $methods, 'HemoPep');
        HemoPepJob::dispatch($data, $request->input())->delay(Carbon::now()->addSeconds(1));

        return ResFactoryUtils::getServicesRes($data, 'fail');
    }

    public function insertTasksMethods($request, $data)
    {
        $methods = [];
        foreach ($request->methods as $key => $value) {
            if ($value == true) {
                RequestUtils::addSpecificInput(['method' => $key, 'task_id' => $data->id]);
                $method = TasksMethodsServices::getInstance()->insert($request);
                array_push($methods, $method->method);
            } else {
                continue;
            }
        }

        return $methods;
    }

    public function finishedTask($taskID)
    {
        $data = DAOSimpleFactory::createTasksDAO()->finished($taskID);
        $methods = DAOSimpleFactory::createTasksMethodsDAO()->getSpecifyByTaskID($taskID);
        FileUtils::writeHemoPepResultFile($taskID, $methods);

        return $data;
    }

    public function failedTask($taskID)
    {
        $data = DAOSimpleFactory::createTasksDAO()->failed($taskID);

        return $data;
    }
}
