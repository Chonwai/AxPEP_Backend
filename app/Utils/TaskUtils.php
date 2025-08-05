<?php

namespace App\Utils;

use App\Services\AmPEPMicroserviceClient;
use App\Services\AmpRegressionECSAPredictMicroserviceClient;
use App\Services\EcotoxicologyMicroserviceClient;
use Illuminate\Support\Facades\Log;
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
        $rScriptPath = env('AMPEP_PREDICT_R_PATH', '../AmPEP/predict.R');
        $inputPath = storage_path("app/Tasks/$task->id/input.fasta");
        $outputPath = storage_path("app/Tasks/$task->id/ampep.out");

        $process = new Process(['Rscript', $rScriptPath, $inputPath, $outputPath]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function runRFAmPEP30Task($task)
    {
        $process = new Process(['Rscript', '../Deep-AmPEP30/RF-AmPEP30.R', "storage/app/Tasks/$task->id/input.fasta", "storage/app/Tasks/$task->id/rfampep30.out"]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function runDeepAmPEP30Task($task)
    {
        $process = new Process(['Rscript', '../Deep-AmPEP30/Deep-AmPEP30.R', "storage/app/Tasks/$task->id/input.fasta", "storage/app/Tasks/$task->id/deepampep30.out"]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    /**
     * 使用AmPEP微服務進行預測（新方法）
     * 遵循現有微服務調用模式，提供向後兼容的輸出格式
     */
    public static function runAmPEPMicroservice($task)
    {
        try {
            $fastaPath = "storage/app/Tasks/$task->id/input.fasta";
            $taskID = $task->id;

            $microserviceClient = new AmPEPMicroserviceClient;
            $response = $microserviceClient->predict($fastaPath, $taskID);

            if ($response && $response['status'] == 'success') {
                // 將微服務響應轉換為現有的.out文件格式，保持向後兼容
                self::writeAmPEPMicroserviceResults($task->id, $response['data']);
                Log::info("AmPEP微服務預測完成，TaskID: {$taskID}");
            } else {
                throw new \Exception('AmPEP微服務返回失敗狀態');
            }
        } catch (\Exception $e) {
            Log::error("AmPEP微服務調用失敗，TaskID: {$task->id}, Error: ".$e->getMessage());
            throw $e;
        }
    }

    /**
     * 將微服務響應轉換為現有的.out文件格式
     * 保持與現有FileUtils的兼容性
     */
    private static function writeAmPEPMicroserviceResults($taskId, $data)
    {
        try {
            $outputPath = storage_path("app/Tasks/$taskId/ampep.out");
            $content = '';

            // 根據微服務響應格式構建輸出內容
            if (isset($data) && is_array($data)) {
                foreach ($data as $sequence) {
                    if (isset($sequence['sequence_name'], $sequence['prediction'], $sequence['probability'])) {
                        // 格式：sequence_name prediction probability
                        $content .= "{$sequence['sequence_name']} {$sequence['prediction']} {$sequence['probability']}\n";
                    }
                }
            }

            // 寫入文件，保持與現有格式一致
            file_put_contents($outputPath, $content);
            Log::info("AmPEP微服務結果已寫入文件: {$outputPath}");

        } catch (\Exception $e) {
            Log::error("寫入AmPEP微服務結果失敗，TaskID: {$taskId}, Error: ".$e->getMessage());
            throw $e;
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

            $microserviceClient = new AmpRegressionECSAPredictMicroserviceClient;
            $microserviceClient->predict($fastaPath, $taskID);

            $absoluteResultPath = base_path("../AMP_Regression_EC_SA_Predict/result/$taskID.csv");
            $absoluteTargetResultPath = base_path("storage/app/Tasks/$task->id/amp_activity_prediction.csv");
            $process = new Process(['cp', "$absoluteResultPath", "$absoluteTargetResultPath"]);
            $process->setTimeout(3600);
            $process->run();
        } catch (\Exception $e) {
            Log::error('AMP Regression Microservice call failed: '.$e->getMessage());

            return;
        }
    }

    public static function runAcPEPTask($task, $method)
    {
        $process = new Process([env('PYTHON_VER', 'python3'), '../xDeep-AcPEP/prediction/prediction.py', '-t', "$method", '-m', '../xDeep-AcPEP/prediction/model/', '-d', "storage/app/Tasks/$task->id/input.fasta", '-o', "storage/app/Tasks/$task->id/$method.out."]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function runAcPEPClassificationTask($task)
    {
        $process = new Process([env('PYTHON_VER', 'python3'), '../xDeep-AcPEP-Classification/main.py', "../xDeep-AcPEP-Classification/$task->id.fasta"]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function runCodonTask($task, $codonCode = '1')
    {
        $process = new Process([env('PYTHON_VER', 'python3'), '../Genome/ORF.py', "storage/app/Tasks/$task->id/codon.fasta", "$codonCode"]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function runBESToxTask($task)
    {
        $process = new Process([env('PYTHON_VER', 'python3'), '../BESTox/main.py', "storage/app/Tasks/$task->id/input.smi", "storage/app/Tasks/$task->id/result.csv"]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function runSSLBESToxTask($task, $method)
    {
        $process = new Process([env('PYTHON_VER', 'python3'), '../SSL-GCN/main.py', '-d', "storage/app/Tasks/$task->id/input.fasta", '-m', '../SSL-GCN/model/', '-t', "$method", '-o', "storage/app/Tasks/$task->id/$method."]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function runEcotoxicologyTask($task, $method)
    {
        try {
            $fastaPath = "storage/app/Tasks/$task->id/input.fasta";
            $taskID = $task->id;

            $microserviceClient = new EcotoxicologyMicroserviceClient;
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
                        $response['data']['predictions'][$index],
                    ]);
                }
                fclose($handle);
            } else {
                throw new \Exception('Microservice response is not successful');
            }
        } catch (\Exception $e) {
            Log::error('Ecotoxicology Microservice call failed: '.$e->getMessage());

            return;
        }
    }

    public static function renameCodonFasta($task)
    {
        $process = new Process(['mv', "storage/app/Tasks/$task->id/codon_orf.fasta", "storage/app/Tasks/$task->id/input.fasta"]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function copyAcPEPInputFile($task)
    {
        $process = new Process(['cp', "storage/app/Tasks/$task->id/input.fasta", "../xDeep-AcPEP-Classification/$task->id.fasta"]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function renameAcPEPResultFile($task, $method)
    {
        $process = new Process(['mv', "storage/app/Tasks/$task->id/$method.out.result_input.fasta.csv", "storage/app/Tasks/$task->id/$method.out"]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function renameAcPEPClassificationResultFile($task)
    {
        $process = new Process(['mv', "../xDeep-AcPEP-Classification/$task->id.csv", "storage/app/Tasks/$task->id/xDeep-AcPEP-Classification.csv"]);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
