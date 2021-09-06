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

    public static function runAcPEPTask($task, $method)
    {
        $process = new Process([env('PYTHON_VER', 'python3'), '../xDeep-AcPEP/prediction/prediction.py', '-t', "$method", '-m', '../xDeep-AcPEP/prediction/model/', '-d', "storage/app/Tasks/$task->id/input.fasta", '-o', "storage/app/Tasks/$task->id/$method.out."]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function runAcPEPClassificationTask($task)
    {
        $process = new Process([env('PYTHON_VER', 'python3'), '../xDeep-AcPEP-Classification/main.py', "../xDeep-AcPEP-Classification/$task->id.fasta"]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function runCodonTask($task, $codonCode = "1")
    {
        $process = new Process([env('PYTHON_VER', 'python3'), "../Genome/ORF.py", "storage/app/Tasks/$task->id/codon.fasta", "$codonCode"]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function runBESToxTask($task)
    {
        $process = new Process([env('PYTHON_VER', 'python3'), "../BESTox/main.py", "storage/app/Tasks/$task->id/input.smi", "storage/app/Tasks/$task->id/result.csv"]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function runSSLBESToxTask($task, $method)
    {
        $process = new Process([env('PYTHON_VER', 'python3'), "../SSL-GCN/main.py", "-d", "storage/app/Tasks/$task->id/input.fasta", "-m", "../SSL-GCN/model/", "-t", "$method", "-o", "storage/app/Tasks/$task->id/$method."]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function renameCodonFasta($task)
    {
        $process = new Process(['mv', "storage/app/Tasks/$task->id/codon_orf.fasta", "storage/app/Tasks/$task->id/input.fasta"]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function copyAcPEPInputFile($task)
    {
        $process = new Process(['cp', "storage/app/Tasks/$task->id/input.fasta", "../xDeep-AcPEP-Classification/$task->id.fasta"]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function renameAcPEPResultFile($task, $method)
    {
        $process = new Process(['mv', "storage/app/Tasks/$task->id/$method.out.result_input.fasta.csv", "storage/app/Tasks/$task->id/$method.out"]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function renameAcPEPClassificationResultFile($task)
    {
        $process = new Process(['mv', "../xDeep-AcPEP-Classification/$task->id.csv", "storage/app/Tasks/$task->id/xDeep-AcPEP-Classification.csv"]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
