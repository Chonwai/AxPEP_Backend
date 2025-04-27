<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Services\HemoPepServices;
use Illuminate\Http\Request;

class HemoPepController extends Controller
{
    /**
     * 通過文件創建新任務
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createNewTaskByFile(Request $request)
    {
        $validation = HemoPepServices::getInstance()->dataValidation($request, __FUNCTION__);
        if ($validation !== true) {
            return response()->json($validation);
        }

        $result = HemoPepServices::getInstance()->createNewTaskByFile($request);
        return response()->json($result);
    }

    /**
     * 通過文本框創建新任務
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createNewTaskByTextarea(Request $request)
    {
        $validation = HemoPepServices::getInstance()->dataValidation($request, __FUNCTION__);
        if ($validation !== true) {
            return response()->json($validation);
        }

        $result = HemoPepServices::getInstance()->createNewTaskByTextarea($request);
        return response()->json($result);
    }
}
