<?php

namespace App\Utils;

use Illuminate\Http\Request;

class RequestUtils
{
    public static function addEmail(Request $request)
    {
        $request->request->add(['email' => $request->email]);
    }

    public static function addTaskID(Request $request)
    {
        $request->request->add(['id' => $request->id]);
    }
}
