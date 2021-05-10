<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Services\AcPEPServices;
use App\Services\TasksServices;
use App\Utils\RequestUtils;
use Illuminate\Http\Request;

class AcPEPController extends Controller
{
    public function responseSpecify(Request $request)
    {
        RequestUtils::addTaskID($request);
        $res = AcPEPServices::getInstance()->responseSpecify($request);
        return $res;
    }

    public function responseSpecifyTaskByEmail(Request $request) {
        RequestUtils::addEmail($request);

        $status = TasksServices::getInstance()->dataValidation($request, 'responseSpecifyTaskByEmail');

        if ($status === true) {
            $res = TasksServices::getInstance()->responseSpecifyTaskByEmail($request, 'acpep');
            return $res;
        } else {
            return response()->json($status, 200);
        }
    }

    public function createNewTaskByFile(Request $request)
    {
        $status = AcPEPServices::getInstance()->dataValidation($request, 'createNewTaskByFile');

        if ($status === true) {
            $res = AcPEPServices::getInstance()->createNewTaskByFile($request);
            return $res;
        } else {
            return response()->json($status, 200);
        }
    }

    public function createNewTaskByTextarea(Request $request)
    {
        $status = AcPEPServices::getInstance()->dataValidation($request, 'createNewTaskByTextarea');

        if ($status === true) {
            $res = AcPEPServices::getInstance()->createNewTaskByTextarea($request);
            return $res;
        } else {
            return response()->json($status, 200);
        }
    }
}
