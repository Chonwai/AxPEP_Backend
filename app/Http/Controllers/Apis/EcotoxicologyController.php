<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Services\EcotoxicologyServices;
use Illuminate\Http\Request;

class EcotoxicologyController extends Controller
{
    public function createNewTaskByFile(Request $request)
    {
        $status = EcotoxicologyServices::getInstance()->dataValidation($request, 'createNewTaskByFile');

        if ($status === true) {
            $res = EcotoxicologyServices::getInstance()->createNewTaskByFile($request);

            return $res;
        } else {
            return response()->json($status, 200);
        }
    }

    public function createNewTaskByTextarea(Request $request)
    {
        $status = EcotoxicologyServices::getInstance()->dataValidation($request, 'createNewTaskByTextarea');

        if ($status === true) {
            $res = EcotoxicologyServices::getInstance()->createNewTaskByTextarea($request);

            return $res;
        } else {
            return response()->json($status, 200);
        }
    }
}
