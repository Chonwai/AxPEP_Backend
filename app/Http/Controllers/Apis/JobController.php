<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use App\Jobs\AmPEPJob;
use Carbon\Carbon;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function test() {
        AmPEPJob::dispatch()->delay(Carbon::now()->addSeconds(5));
        echo('Calculating...!');
    }
}
