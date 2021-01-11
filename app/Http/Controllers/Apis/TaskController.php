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

    public function createNewTaskByFile(Request $request)
    {
        $status = TasksServices::getInstance()->dataValidation($request, 'createNewTaskByFile');

        if ($status === true) {
            $res = TasksServices::getInstance()->createNewTaskByFile($request);
            return $res;
        } else {
            return response()->json($status, 200);
        }
    }

    public function createNewTaskByTextarea(Request $request)
    {
        $status = TasksServices::getInstance()->dataValidation($request, 'createNewTaskByTextarea');

        if ($status === true) {
            $res = TasksServices::getInstance()->createNewTaskByTextarea($request);
            return $res;
        } else {
            return response()->json($status, 200);
        }
    }

    public function createNewTaskByFileAndCodon(Request $request)
    {
        $status = TasksServices::getInstance()->dataValidation($request, 'createNewTaskByFileAndCodon');

        if ($status === true) {
            $res = TasksServices::getInstance()->createNewTaskByFileAndCodon($request);
            return $res;
        } else {
            return response()->json($status, 200);
        }
    }
}
