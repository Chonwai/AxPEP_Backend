<?php

namespace App\Http\Controllers\Apis;

use App\DAO\DAOSimpleFactory;
use App\Http\Controllers\Controller;
use App\Services\CodonsServices;
use App\Utils\RequestUtils;
use Illuminate\Http\Request;

class CodonController extends Controller
{
    public function responseAll()
    {
        $res = CodonsServices::getInstance()->responseAll();
        return $res;
    }
}
