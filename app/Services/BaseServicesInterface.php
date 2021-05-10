<?php

namespace App\Services;

use Illuminate\Http\Request;

interface BaseServicesInterface
{
    public function dataValidation($request, $method);
    // public function responseAll();
    // public function responseSpecify(Request $request);
    // public function insert(Request $request);
}
