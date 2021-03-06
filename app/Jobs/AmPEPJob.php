<?php

namespace App\Jobs;

use App\Services\TasksServices;
use App\Utils\TaskUtils;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AmPEPJob implements ShouldQueue
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
                if ($key === 'ampep') {
                    echo ('Running ' . $this->task->id . " AmPEP Task!\n");
                    TaskUtils::runAmPEPTask($this->task);
                }
                if ($key === 'deepampep30') {
                    echo ('Running ' . $this->task->id . " DeepAmPEP30 Task!\n");
                    TaskUtils::runDeepAmPEP30Task($this->task);
                }
                if ($key === 'rfampep30') {
                    echo ('Running ' . $this->task->id . " RFAmPEP30 Task!\n");
                    TaskUtils::runRFAmPEP30Task($this->task);
                }
            } else {
                continue;
            }
        }

        TasksServices::getInstance()->finishedTask($this->task->id);
    }

    public function failed(\Exception $e = null)
    {
        echo ("Fail Status:" . $e);
        TasksServices::getInstance()->failedTask($this->task->id);
    }
}
