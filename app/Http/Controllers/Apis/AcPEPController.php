<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Services\AcPEPServices;
use Illuminate\Http\Request;

class AcPEPController extends Controller
{
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
