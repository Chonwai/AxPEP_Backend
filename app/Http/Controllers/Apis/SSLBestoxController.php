<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Services\SSLBESToxServices;
use Illuminate\Http\Request;

class SSLBestoxController extends Controller
{
    public function createNewTaskByFile(Request $request)
    {
        $status = SSLBESToxServices::getInstance()->dataValidation($request, 'createNewTaskByFile');

        if ($status === true) {
            $res = SSLBESToxServices::getInstance()->createNewTaskByFile($request);
            return $res;
        } else {
            return response()->json($status, 200);
        }
    }

    public function createNewTaskByTextarea(Request $request)
    {
        $status = SSLBESToxServices::getInstance()->dataValidation($request, 'createNewTaskByTextarea');

        if ($status === true) {
            $res = SSLBESToxServices::getInstance()->createNewTaskByTextarea($request);
            return $res;
        } else {
            return response()->json($status, 200);
        }
    }
}
