<?php

namespace App\Services;

use App\DAO\DAOSimpleFactory;
use App\Http\Requests\TasksRules;
use App\Imports\AmPEPResultImport;
use App\Utils\FileUtils;
use App\Utils\Res\ResFactoryUtils;
use App\Utils\ResponseUtils;
use App\Utils\Utils;
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
        if (! (self::$_instance instanceof self)) {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    public function dataValidation($request, $method)
    {
        switch ($method) {
            case 'responseSpecifyTaskByEmail':
                $validator = Validator::make($request->all(), TasksRules::emailRules());
                break;
            case 'createNewTaskByTextarea':
                $validator = Validator::make($request->all(), TasksRules::textareaRules());
                break;
            case 'createNewTaskByFile':
                $validator = Validator::make($request->all(), TasksRules::fileRules());
                break;
            case 'downloadSpecifyClassification':
                $validator = Validator::make($request->all(), TasksRules::rules());
                break;
            case 'downloadSpecifyPredictionScore':
                $validator = Validator::make($request->all(), TasksRules::rules());
                break;
            case 'downloadSpecifyResult':
                $validator = Validator::make($request->all(), TasksRules::rules());
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
        $data = DAOSimpleFactory::createTasksDAO()->getSpecify($request);
        if ($request->application == 'ampep') {
            $data[0]->classifications = Excel::toArray(new AmPEPResultImport, "Tasks/$request->id/classification.csv", null, \Maatwebsite\Excel\Excel::CSV)[0];
            $data[0]->scores = Excel::toArray(new AmPEPResultImport, "Tasks/$request->id/score.csv", null, \Maatwebsite\Excel\Excel::CSV)[0];
            if (Storage::disk('local')->exists("Tasks/$request->id/amp_activity_prediction.csv")) {
                $data[0]->amp_activity_prediction = Excel::toArray(new AmPEPResultImport, "Tasks/$request->id/amp_activity_prediction.csv", null, \Maatwebsite\Excel\Excel::CSV)[0];
            }
        } elseif ($request->application == 'acpep') {
            $data[0]->classifications = Excel::toArray(new AmPEPResultImport, "Tasks/$request->id/classification.csv", null, \Maatwebsite\Excel\Excel::CSV)[0];
            $data[0]->scores = Excel::toArray(new AmPEPResultImport, "Tasks/$request->id/score.csv", null, \Maatwebsite\Excel\Excel::CSV)[0];
        } elseif ($request->application == 'bestox') {
            $data[0]->result = Excel::toArray(new AmPEPResultImport, "Tasks/$request->id/result.csv", null, \Maatwebsite\Excel\Excel::CSV)[0];
        } elseif ($request->application == 'ssl-gcn' || $request->application == 'ecotoxicology') {
            $data[0]->classifications = Excel::toArray(new AmPEPResultImport, "Tasks/$request->id/classification.csv", null, \Maatwebsite\Excel\Excel::CSV)[0];
        } elseif ($request->application == 'hemopep') {
            $data[0]->classifications = Excel::toArray(new AmPEPResultImport, "Tasks/$request->id/classification.csv", null, \Maatwebsite\Excel\Excel::CSV)[0];
            $data[0]->scores = Excel::toArray(new AmPEPResultImport, "Tasks/$request->id/score.csv", null, \Maatwebsite\Excel\Excel::CSV)[0];

            if (Storage::disk('local')->exists("Tasks/$request->id/hemopep60_detailed.csv")) {
                $data[0]->detailed_predictions = Excel::toArray(new AmPEPResultImport, "Tasks/$request->id/hemopep60_detailed.csv", null, \Maatwebsite\Excel\Excel::CSV)[0];
            }
        }

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

    public function downloadSpecifyResult(Request $request)
    {
        $file = Storage::download("Tasks/$request->id/result.csv");

        return $file;
    }

    public function finishedTask($taskID)
    {
        $data = DAOSimpleFactory::createTasksDAO()->finished($taskID);
        $methods = DAOSimpleFactory::createTasksMethodsDAO()->getSpecifyByTaskID($taskID);
        FileUtils::writeAmPEPResultFile($taskID, $methods);

        return $data;
    }

    public function failedTask($taskID)
    {
        $data = DAOSimpleFactory::createTasksDAO()->failed($taskID);

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

    public function countEachMethods($request)
    {
        $data = DAOSimpleFactory::createTasksDAO()->countEachMethods($request);

        return ResFactoryUtils::getServicesRes($data, 'fail');
    }
}
