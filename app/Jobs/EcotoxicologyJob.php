<?php

namespace App\Jobs;

use App\Services\EcotoxicologyServices;
use App\Utils\TaskUtils;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EcotoxicologyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $task;
    protected $request;

    public function __construct($task, $request)
    {
        $this->task = $task;
        $this->request = $request;
    }

    public function handle()
    {
        foreach ($this->request['methods'] as $key => $value) {
            if ($value == true) {
                TaskUtils::runEcotoxicologyTask($this->task, $key);
            } else {
                continue;
            }
        }
        EcotoxicologyServices::getInstance()->finishedTask($this->task->id);
    }

    public function failed(\Exception $e = null)
    {
        EcotoxicologyServices::getInstance()->failedTask($this->task->id);
    }
}
