<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Services\TasksServices;
use App\Utils\RequestUtils;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function responseSpecify(Request $request)
    {
        RequestUtils::addTaskID($request);
        $res = TasksServices::getInstance()->responseSpecify($request);
        return $res;
    }

    public function responseSpecifyTaskByEmail(Request $request)
    {
        RequestUtils::addEmail($request);

        $status = TasksServices::getInstance()->dataValidation($request, 'responseSpecifyTaskByEmail');

        if ($status === true) {
            $res = TasksServices::getInstance()->responseSpecifyTaskByEmail($request);
            return $res;
        } else {
            return response()->json($status, 200);
        }
    }

    public function downloadSpecifyClassification(Request $request)
    {
        RequestUtils::addTaskID($request);

        $status = TasksServices::getInstance()->dataValidation($request, 'downloadSpecifyClassification');

        if ($status === true) {
            $res = TasksServices::getInstance()->downloadSpecifyClassification($request);
            return $res;
        } else {
            return response()->json($status, 200);
        }
    }

    public function downloadSpecifyPredictionScore(Request $request)
    {
        RequestUtils::addTaskID($request);

        $status = TasksServices::getInstance()->dataValidation($request, 'downloadSpecifyPredictionScore');

        if ($status === true) {
            $res = TasksServices::getInstance()->downloadSpecifyPredictionScore($request);
            return $res;
        } else {
            return response()->json($status, 200);
        }
    }

    public function downloadSpecifyResult(Request $request)
    {
        RequestUtils::addTaskID($request);

        $status = TasksServices::getInstance()->dataValidation($request, 'downloadSpecifyResult');

        if ($status === true) {
            $res = TasksServices::getInstance()->downloadSpecifyResult($request);
            return $res;
        } else {
            return response()->json($status, 200);
        }
    }

    public function countDistinctIpNDays(Request $request)
    {
        $data = TasksServices::getInstance()->countDistinctIpNDays($request);
        return $data;
    }

    public function countTasksNDays(Request $request)
    {
        $data = TasksServices::getInstance()->countTasksNDays($request);
        return $data;
    }

    public function countEachMethods(Request $request)
    {
        $data = TasksServices::getInstance()->countEachMethods($request);
        return $data;
    }
}
