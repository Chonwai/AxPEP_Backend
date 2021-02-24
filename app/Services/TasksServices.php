<?php

namespace App\Services;

use App\DAO\DAOSimpleFactory;
use App\Http\Requests\TasksRules;
use App\Imports\AmPEPResultImport;
use App\Jobs\AmPEPJob;
use App\Jobs\CodonJob;
use App\Utils\FileUtils;
use App\Utils\RequestUtils;
use App\Utils\ResponseUtils;
use App\Utils\Res\ResFactoryUtils;
use App\Utils\TaskUtils;
use App\Utils\Utils;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

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
            case 'createNewTaskByTextarea':
                $validator = Validator::make($request->all(), TasksRules::textareaRules());
                break;
            case 'createNewTaskByFileAndCodon':
                $validator = Validator::make($request->all(), TasksRules::codonRules());
                break;
            case 'downloadSpecifyClassification':
                $validator = Validator::make($request->all(), TasksRules::rules());
                break;
            case 'downloadSpecifyPredictionScore':
                $validator = Validator::make($request->all(), TasksRules::rules());
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
        $data[0]->classifications = Excel::toArray(new AmPEPResultImport, "Tasks/$request->id/classification.csv", null, \Maatwebsite\Excel\Excel::CSV)[0];
        $data[0]->scores = Excel::toArray(new AmPEPResultImport, "Tasks/$request->id/score.csv", null, \Maatwebsite\Excel\Excel::CSV)[0];
        return ResFactoryUtils::getServicesRes($data, 'fail');
    }

    public function responseSpecifyTaskByEmail(Request $request)
    {
        $data = DAOSimpleFactory::createTasksDAO()->getSpecifyTaskByEmail($request);
        return ResFactoryUtils::getServicesRes($data, 'fail');
    }

    public function downloadSpecifyClassification(Request $request)
    {
        $file = Storage::download("Tasks/$request->id/classification.csv");
        return $file;
    }

    public function downloadSpecifyPredictionScore(Request $request)
    {
        $file = Storage::download("Tasks/$request->id/score.csv");
        return $file;
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
        return ResFactoryUtils::getServicesRes($data, 'fail');
    }

    public function createNewTaskByTextarea(Request $request)
    {
        $data = DAOSimpleFactory::createTasksDAO()->insert($request);
        $methods = $this->insertTasksMethods($request, $data);
        TaskUtils::createTaskFolder($data);
        Storage::disk('local')->put("Tasks/$data->id/input.fasta", $request->fasta);
        FileUtils::createResultFile("Tasks/$data->id/", $methods);
        FileUtils::insertSequencesAndHeaderOnResult("../storage/app/Tasks/$data->id/", $methods);
        AmPEPJob::dispatch($data, $request->input())->delay(Carbon::now()->addSeconds(1));
        return ResFactoryUtils::getServicesRes($data, 'fail');
    }

    public function createNewTaskByFileAndCodon(Request $request)
    {
        $data = DAOSimpleFactory::createTasksDAO()->insert($request);
        $methods = $this->insertTasksMethods($request, $data);
        TaskUtils::createTaskFolder($data);
        Storage::putFileAs("Tasks/$data->id/", $request->file('file'), "codon.fasta");
        CodonJob::dispatch($data, $request->input(), $methods);
        AmPEPJob::dispatch($data, $request->input())->delay(Carbon::now()->addSeconds(3));
        return ResFactoryUtils::getServicesRes($data, 'fail');
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

    public function countDistinctIpNDays($request)
    {
        $data = DAOSimpleFactory::createTasksDAO()->countDistinctIpNDays($request);
        return ResFactoryUtils::getServicesRes($data, 'fail');
    }

    public function countTasksNDays($request)
    {
        $data = DAOSimpleFactory::createTasksDAO()->countTasksNDays($request);
        return ResFactoryUtils::getServicesRes($data, 'fail');
    }
}
