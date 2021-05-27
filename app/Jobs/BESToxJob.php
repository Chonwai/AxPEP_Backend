<?php

namespace App\Jobs;

use App\Services\BESToxServices;
use App\Services\TasksServices;
use App\Utils\TaskUtils;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BESToxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $task;

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
    public function __construct($task)
    {
        $this->task = $task;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        echo ('Running ' . $this->task->id . " BESTox Task!\n");
        TaskUtils::runBESToxTask($this->task);
        BESToxServices::getInstance()->finishedTask($this->task->id);
    }

    public function failed(\Exception $e = null)
    {
        echo ("Fail Status:" . $e);
        BESToxServices::getInstance()->failedTask($this->task->id);
    }
}
