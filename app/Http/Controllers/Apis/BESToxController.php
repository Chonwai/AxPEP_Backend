<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Services\BESToxServices;
use Illuminate\Http\Request;

class BESToxController extends Controller
{
    public function createNewTaskByFile(Request $request)
    {
        $status = BESToxServices::getInstance()->dataValidation($request, 'createNewTaskByFile');

        if ($status === true) {
            $res = BESToxServices::getInstance()->createNewTaskByFile($request);
            return $res;
        } else {
            return response()->json($status, 200);
        }
    }

    public function createNewTaskByTextarea(Request $request)
    {
        $status = BESToxServices::getInstance()->dataValidation($request, 'createNewTaskByTextarea');

        if ($status === true) {
            $res = BESToxServices::getInstance()->createNewTaskByTextarea($request);
            return $res;
        } else {
            return response()->json($status, 200);
        }
    }
}
