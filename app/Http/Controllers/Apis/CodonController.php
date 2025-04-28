<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Services\CodonsServices;

class CodonController extends Controller
{
    public function responseAll()
    {
        $res = CodonsServices::getInstance()->responseAll();

        return $res;
    }
}
