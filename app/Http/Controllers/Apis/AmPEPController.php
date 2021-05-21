<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Services\AmPEPServices;
use Illuminate\Http\Request;

class AmPEPController extends Controller
{
    public function createNewTaskByFile(Request $request)
    {
        $status = AmPEPServices::getInstance()->dataValidation($request, 'createNewTaskByFile');

        if ($status === true) {
            $res = AmPEPServices::getInstance()->createNewTaskByFile($request);
            return $res;
        } else {
            return response()->json($status, 200);
        }
    }

    public function createNewTaskByTextarea(Request $request)
    {
        $status = AmPEPServices::getInstance()->dataValidation($request, 'createNewTaskByTextarea');

        if ($status === true) {
            $res = AmPEPServices::getInstance()->createNewTaskByTextarea($request);
            return $res;
        } else {
            return response()->json($status, 200);
        }
    }
}
