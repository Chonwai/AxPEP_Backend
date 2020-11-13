<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Storage;

class AmPEPJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        echo ('HiHi');
        $uuid = Str::uuid();
        Storage::makeDirectory("Result/$uuid/");

        $process = new Process(['Rscript', '../AmPEP/predict.R', '../AmPEP/input.fasta', "../AmPEP/$uuid.out"]);
        $process->setTimeout(3600);
        $process->run();

        $process2 = new Process(['COPY', "..\AmPEP\\$uuid.out", "storage/app/Result/$uuid/"]);
        $process2->run();

        // // executes after the command finishes
        // if (!$process->isSuccessful()) {
        //     throw new ProcessFailedException($process);
        // }

        echo $process2->getOutput();
    }
}
