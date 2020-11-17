<?php

namespace App\Utils;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class TaskUtils
{
    public static function createTaskFolder($task)
    {
        Storage::makeDirectory("Tasks/$task->id/");
    }

    public static function runAmPEPTask($task)
    {
        $process = new Process(['Rscript', '../AmPEP/predict.R', "storage/app/Tasks/$task->id/input.fasta", "storage/app/Tasks/$task->id/ampep.out"]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function runRFAmPEP30Task($task)
    {
        $process = new Process(['Rscript', '../Deep-AmPEP30/RF-AmPEP30.R', "storage/app/Tasks/$task->id/input.fasta", "storage/app/Tasks/$task->id/rfampep30.out"]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function runDeepAmPEP30Task($task)
    {
        $process = new Process(['Rscript', '../Deep-AmPEP30/Deep-AmPEP30.R', "storage/app/Tasks/$task->id/input.fasta", "storage/app/Tasks/$task->id/deepampep30.out"]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function runAcPEPTask($task)
    {
        $process = new Process(['Rscript', '../AcPEP/pred.R', "storage/app/Tasks/$task->id/input.fasta", "storage/app/Tasks/$task->id/acpep.out"]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
