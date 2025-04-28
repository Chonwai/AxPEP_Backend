<?php

namespace App\Jobs;

use App\Services\AcPEPServices;
use App\Services\TasksServices;
use App\Utils\TaskUtils;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AcPEPJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $task;

    private $request;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 7200;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($task, $request)
    {
        $this->task = $task;
        $this->request = $request;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        foreach ($this->request['methods'] as $key => $value) {
            if ($value == true) {
                TaskUtils::runAcPEPTask($this->task, $key);
                TaskUtils::renameAcPEPResultFile($this->task, $key);
            } else {
                continue;
            }
        }
        TaskUtils::copyAcPEPInputFile($this->task);
        TaskUtils::runAcPEPClassificationTask($this->task);
        TaskUtils::renameAcPEPClassificationResultFile($this->task);

        AcPEPServices::getInstance()->finishedTask($this->task->id);
    }

    public function failed(?\Exception $e = null)
    {
        echo 'Fail Status:'.$e;
        echo json_encode($e);
        TasksServices::getInstance()->failedTask($this->task->id);
    }
}
