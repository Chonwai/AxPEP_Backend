<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Jobs\AmPEPJob;
use App\Services\TasksServices;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function test()
    {
        AmPEPJob::dispatch()->delay(Carbon::now()->addSeconds(5));
        echo ('Calculating...!');
    }

    public function createNewTaskByFile(Request $request)
    {
        $status = TasksServices::getInstance()->dataValidation($request, 'createNewTaskByFile');

        if ($status === true) {
            $res = TasksServices::getInstance()->createNewTaskByFile($request, $operation = 'ssr');
            return $res;
        } else {
            return response()->json($status, 200);
        }
    }
}
