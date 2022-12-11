<?php

namespace App\Jobs;

use App\Services\TasksServices;
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
    private $function;

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
    public function __construct($task, $codonCode, $methods, $function = 'AmPEP')
    {
        $this->task = $task;
        $this->codonCode = $codonCode;
        $this->methods = $methods;
        $this->taskID = $task->id;
        $this->function = $function;
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

        if ($this->function == 'AmPEP') {
            FileUtils::createResultFile("Tasks/$this->taskID/", $this->methods);
        } else if ($this->function == 'AcPEP') {
            FileUtils::createAcPEPResultFile("Tasks/$this->taskID/", $this->methods);
        } else {
            FileUtils::createResultFile("Tasks/$this->taskID/", $this->methods);
        }

        FileUtils::insertSequencesAndHeaderOnResult("storage/app/Tasks/$this->taskID/", $this->methods, $this->function);
    }

    public function failed(\Exception $e = null)
    {
        echo ("Fail Status:" . $e);
        TasksServices::getInstance()->failedTask($this->task->id);
    }
}
