<?php

namespace App\Utils;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use App\Services\AmpRegressionECSAPredictMicroserviceClient;
use App\Services\EcotoxicologyMicroserviceClient;
use Illuminate\Support\Facades\Log;

class TaskUtils
{
    public static function createTaskFolder($task)
    {
        Storage::makeDirectory("Tasks/$task->id/");
    }

    public static function runAmPEPTask($task)
    {
        $rScriptPath = env('AMPEP_PREDICT_R_PATH', '../AmPEP/predict.R');
        $inputPath = storage_path("app/Tasks/$task->id/input.fasta");
        $outputPath = storage_path("app/Tasks/$task->id/ampep.out");

        $process = new Process(['Rscript', $rScriptPath, $inputPath, $outputPath]);
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

    public static function runAmpRegressionEcSaPredictMicroservice($task)
    {
        try {
            $fastaPath = "storage/app/Tasks/$task->id/input.fasta";
            $taskID = $task->id;

            $absolutePath = base_path($fastaPath);
            $absoluteTargetPath = base_path("../AMP_Regression_EC_SA_Predict/fasta/$taskID.fasta");
            $process = new Process(['cp', "$absolutePath", "$absoluteTargetPath"]);
            $process->setTimeout(3600);
            $process->run();

            $microserviceClient = new AmpRegressionECSAPredictMicroserviceClient();
            $microserviceClient->predict($fastaPath, $taskID);

            $absoluteResultPath = base_path("../AMP_Regression_EC_SA_Predict/result/$taskID.csv");
            $absoluteTargetResultPath = base_path("storage/app/Tasks/$task->id/amp_activity_prediction.csv");
            $process = new Process(['cp', "$absoluteResultPath", "$absoluteTargetResultPath"]);
            $process->setTimeout(3600);
            $process->run();
        } catch (\Exception $e) {
            Log::error("AMP Regression Microservice call failed: " . $e->getMessage());
            return null;
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

    public static function runEcotoxicologyTask($task, $method)
    {
        try {
            $fastaPath = "storage/app/Tasks/$task->id/input.fasta";
            $taskID = $task->id;

            $microserviceClient = new EcotoxicologyMicroserviceClient();
            $response = $microserviceClient->predict($fastaPath, $method);

            if ($response && $response['status'] == 'success') {
                $resultPath = "Tasks/$task->id/$method.result.csv";

                // 使用 Storage facade 來創建和寫入文件
                Storage::put($resultPath, '');
                $file = Storage::path($resultPath);
                $handle = fopen($file, 'w');
                if ($handle === false) {
                    throw new \Exception("Failed to open file: $file");
                }
                fputcsv($handle, ['id', 'smiles', 'pre']);
                foreach ($response['data']['fasta_ids'] as $index => $fasta_id) {
                    fputcsv($handle, [
                        $fasta_id,
                        $response['data']['smiles'][$index],
                        $response['data']['predictions'][$index]
                    ]);
                }
                fclose($handle);
            } else {
                throw new \Exception("Microservice response is not successful");
            }
        } catch (\Exception $e) {
            Log::error("Ecotoxicology Microservice call failed: " . $e->getMessage());
            return null;
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
