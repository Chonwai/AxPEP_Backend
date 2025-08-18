<?php

namespace App\Utils;

use App\Services\AmPEP30MicroserviceClient;
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
     * 使用 AmPEP30 微服務（RF）進行預測並寫出 rfampep30.out
     */
    public static function runRFAmPEP30Microservice($task)
    {
        try {
            $fastaPath = storage_path("app/Tasks/$task->id/input.fasta");
            $fastaContent = file_get_contents($fastaPath);
            if ($fastaContent === false) {
                throw new \Exception("Failed to read FASTA file: $fastaPath");
            }

            $client = new AmPEP30MicroserviceClient;
            $results = $client->predictFasta($fastaContent, 'rf');

            self::writeAmPEP30MicroserviceResults($task->id, 'rfampep30', $results);
            Log::info("RFAmPEP30 microservice prediction completed, TaskID: {$task->id}");
        } catch (\Exception $e) {
            Log::error("RFAmPEP30 microservice failed, TaskID: {$task->id}, Error: ".$e->getMessage());
            throw $e;
        }
    }

    /**
     * 使用 AmPEP30 微服務（CNN）進行預測並寫出 deepampep30.out
     */
    public static function runDeepAmPEP30Microservice($task)
    {
        try {
            $fastaPath = storage_path("app/Tasks/$task->id/input.fasta");
            $fastaContent = file_get_contents($fastaPath);
            if ($fastaContent === false) {
                throw new \Exception("Failed to read FASTA file: $fastaPath");
            }

            $client = new AmPEP30MicroserviceClient;
            $results = $client->predictFasta($fastaContent, 'cnn');

            self::writeAmPEP30MicroserviceResults($task->id, 'deepampep30', $results);
            Log::info("DeepAmPEP30 microservice prediction completed, TaskID: {$task->id}");
        } catch (\Exception $e) {
            Log::error("DeepAmPEP30 microservice failed, TaskID: {$task->id}, Error: ".$e->getMessage());
            throw $e;
        }
    }

    /**
     * 將 AmPEP30 微服務結果寫為 <method>.out（空白分隔三欄）以保持向後相容
     */
    private static function writeAmPEP30MicroserviceResults($taskId, string $method, array $data): void
    {
        try {
            $outputPath = storage_path("app/Tasks/$taskId/$method.out");

            // 從 FASTA 取得正確的序列名稱順序
            $fastaPath = storage_path("app/Tasks/$taskId/input.fasta");
            $fastaContent = file_get_contents($fastaPath);
            if ($fastaContent === false) {
                throw new \Exception("Failed to read FASTA file: $fastaPath");
            }

            $fastaSequenceNames = [];
            foreach (explode("\n", $fastaContent) as $line) {
                if (strpos($line, '>') === 0) {
                    $fastaSequenceNames[] = trim(substr($line, 1));
                }
            }

            // 構建 name -> payload 的索引，若微服務有回傳 name，可用來對齊
            $nameToPayload = [];
            foreach ($data as $payload) {
                if (is_array($payload) && isset($payload['name']) && is_string($payload['name'])) {
                    $nameToPayload[$payload['name']] = $payload;
                }
            }

            $lines = [];
            foreach ($fastaSequenceNames as $index => $sequenceName) {
                $payload = null;
                if (isset($nameToPayload[$sequenceName])) {
                    $payload = $nameToPayload[$sequenceName];
                } elseif (isset($data[$index]) && is_array($data[$index])) {
                    $payload = $data[$index];
                }

                if ($payload === null) {
                    Log::warning("[$method] Missing prediction for index $index (".$sequenceName.") in task $taskId");

                    continue;
                }

                $prediction = $payload['prediction'] ?? null;
                $probability = $payload['probability'] ?? null;

                // 防止非標量引發 "Array to string conversion"
                if (is_array($prediction)) {
                    $prediction = reset($prediction);
                }
                if (is_array($probability)) {
                    $probability = reset($probability);
                }

                // 檢查是否為錯誤響應
                if ($prediction === 'ERROR') {
                    $errorMsg = $payload['error'] ?? 'Unknown error';
                    Log::info("[$method] Error response for sequence $sequenceName in task $taskId: $errorMsg");
                    $lines[] = sprintf('%s -1 -1 # %s', $sequenceName, $errorMsg);

                    continue;
                }

                if ($prediction === null || $probability === null) {
                    Log::warning("[$method] Invalid prediction payload at index $index in task $taskId");

                    continue;
                }

                // 根據前端要求映射預測結果：AMP=1, non-AMP=0, ERROR=-1
                $predictionStr = (string) $prediction;
                $predictionNorm = null;

                if (strtolower($predictionStr) === 'amp' || $predictionStr === '1') {
                    $predictionNorm = '1';  // AMP = 陽性序列
                } elseif (strtolower($predictionStr) === 'non-amp' || $predictionStr === '0') {
                    $predictionNorm = '0';  // non-AMP = 陰性序列
                } else {
                    // 如果無法識別，根據字符串內容判斷
                    $predictionNorm = (stripos($predictionStr, 'amp') !== false && ! stripos($predictionStr, 'non')) ? '1' : '0';
                }

                $lines[] = sprintf('%s %s %s', $sequenceName, $predictionNorm, (string) $probability);
            }

            file_put_contents($outputPath, implode("\n", $lines)."\n");
            Log::info("AmPEP30 microservice results written: {$outputPath}, total sequences: ".count($fastaSequenceNames));
        } catch (\Exception $e) {
            Log::error("Write AmPEP30 microservice results failed, TaskID: {$taskId}, Error: ".$e->getMessage());
            throw $e;
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

            // 讀取原始FASTA文件以獲取正確的序列名稱順序
            $fastaPath = storage_path("app/Tasks/$taskId/input.fasta");
            $fastaContent = file_get_contents($fastaPath);
            $fastaSequenceNames = [];

            // 解析FASTA文件獲取序列名稱
            $lines = explode("\n", $fastaContent);
            foreach ($lines as $line) {
                if (strpos($line, '>') === 0) {
                    $sequenceName = trim(substr($line, 1));
                    $fastaSequenceNames[] = $sequenceName;
                }
            }

            $content = '';

            // 根據微服務響應格式構建輸出內容，但使用原始FASTA的序列名稱
            if (isset($data) && is_array($data)) {
                foreach ($data as $index => $sequence) {
                    if (isset($sequence['prediction'], $sequence['probability'])) {
                        // 使用原始FASTA文件中的序列名稱，而不是微服務返回的名稱
                        $originalSequenceName = isset($fastaSequenceNames[$index]) ? $fastaSequenceNames[$index] : "sequence_$index";

                        // 格式：sequence_name prediction probability
                        $content .= "{$originalSequenceName} {$sequence['prediction']} {$sequence['probability']}\n";
                    }
                }
            }

            // 寫入文件，保持與現有格式一致
            file_put_contents($outputPath, $content);
            Log::info("AmPEP微服務結果已寫入文件: {$outputPath}，處理了".count($fastaSequenceNames).'個序列');

        } catch (\Exception $e) {
            Log::error("寫入AmPEP微服務結果失敗，TaskID: {$taskId}, Error: ".$e->getMessage());
            throw $e;
        }
    }

    public static function runAmpRegressionEcSaPredictMicroservice($task)
    {
        // 可配置的AMP Regression實現：V2 JSON API優先，V1文件傳輸作為後備
        $useV2API = env('USE_AMP_REGRESSION_V2_API', true);

        if ($useV2API) {
            try {
                Log::info("嘗試使用AMP Regression V2 JSON API，TaskID: {$task->id}");
                self::runAmpRegressionEcSaPredictMicroserviceV2($task);
                Log::info("AMP Regression V2 API調用成功，TaskID: {$task->id}");

                return;
            } catch (\Exception $e) {
                // V2 API失敗時，回退到V1文件傳輸
                Log::error("AMP Regression V2 API調用失敗，回退到V1文件傳輸，TaskID: {$task->id}, 錯誤: {$e->getMessage()}");
                Log::error('V2 API URL: '.env('AMP_REGRESSION_EC_SA_PREDICT_BASE_URL', 'not_set'));
            }
        }

        // 使用V1文件傳輸實現
        self::runAmpRegressionEcSaPredictMicroserviceV1($task);
    }

    /**
     * AMP Regression EC SA Predict V2 實現 (JSON API)
     */
    public static function runAmpRegressionEcSaPredictMicroserviceV2($task)
    {
        try {
            // 1. 讀取並解析FASTA文件
            $fastaPath = storage_path("app/Tasks/$task->id/input.fasta");
            $sequences = self::parseFastaFile($fastaPath);

            // 2. 調用V2 JSON API
            $microserviceClient = new \App\Services\AmpRegressionECSAPredictMicroserviceClientV2;
            $result = $microserviceClient->predictSequences($task->id, $sequences);

            // 3. 直接處理JSON結果，生成CSV文件
            self::saveAmpRegressionResults($task->id, $result['predictions']);

            Log::info("AMP Regression V2 prediction completed for task: {$task->id}");

        } catch (\Exception $e) {
            Log::error("AMP Regression V2 failed for task {$task->id}: ".$e->getMessage());
            throw $e;
        }
    }

    /**
     * AMP Regression EC SA Predict V1 實現 (文件傳輸)
     */
    public static function runAmpRegressionEcSaPredictMicroserviceV1($task)
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
            Log::error('AMP Regression V1 Microservice call failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * 解析FASTA文件為序列數組
     */
    private static function parseFastaFile($fastaPath)
    {
        $content = file_get_contents($fastaPath);
        $sequences = [];
        $currentId = null;
        $currentSequence = '';

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if (strpos($line, '>') === 0) {
                // 保存上一個序列
                if ($currentId !== null) {
                    $sequences[] = [
                        'id' => $currentId,
                        'sequence' => $currentSequence,
                    ];
                }
                // 開始新序列
                $currentId = substr($line, 1);
                $currentSequence = '';
            } else {
                $currentSequence .= $line;
            }
        }

        // 保存最後一個序列
        if ($currentId !== null) {
            $sequences[] = [
                'id' => $currentId,
                'sequence' => $currentSequence,
            ];
        }

        return $sequences;
    }

    /**
     * 保存AMP Regression預測結果為CSV文件
     */
    private static function saveAmpRegressionResults($taskId, $predictions)
    {
        try {
            $csvData = "id,sequence,ec_predicted_MIC_μM,sa_predicted_MIC_μM\n";

            foreach ($predictions as $prediction) {
                $csvData .= "{$prediction['id']},{$prediction['sequence']},{$prediction['ec_predicted_MIC_μM']},{$prediction['sa_predicted_MIC_μM']}\n";
            }

            $resultPath = storage_path("app/Tasks/$taskId/amp_activity_prediction.csv");
            file_put_contents($resultPath, $csvData);

            if (app()->bound('log')) {
                Log::info("AMP Regression V2 結果已保存: {$resultPath}，預測數量: ".count($predictions));
            }

        } catch (\Exception $e) {
            if (app()->bound('log')) {
                Log::error("保存AMP Regression V2結果失敗，TaskID: {$taskId}, Error: ".$e->getMessage());
            }
            throw $e;
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
