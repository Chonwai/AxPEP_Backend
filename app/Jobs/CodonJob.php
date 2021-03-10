<?php

namespace App\Jobs;

use App\Utils\FileUtils;
use App\Utils\TaskUtils;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CodonJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $task;
    private $codonCode;
    private $methods;
    private $taskID;

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
    public function __construct($task, $codonCode, $methods)
    {
        $this->task = $task;
        $this->codonCode = $codonCode;
        $this->methods = $methods;
        $this->taskID = $task->id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        echo ('Running ' . $this->task->id . " Codon Task!\n");
        TaskUtils::runCodonTask($this->task, $this->codonCode);
        TaskUtils::renameCodonFasta($this->task);
        FileUtils::createResultFile("Tasks/$this->taskID/", $this->methods);
        FileUtils::insertSequencesAndHeaderOnResult("storage/app/Tasks/$this->taskID/", $this->methods);
    }
}
