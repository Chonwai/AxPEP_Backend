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

    /**
     * Add Specific Input on $request.
     *
     * @param  array  $specificInput
     * @param  Request  $request
     * @return void
     */
    public static function addSpecificInput($specificInput)
    {
        request()->merge($specificInput);
    }
}
