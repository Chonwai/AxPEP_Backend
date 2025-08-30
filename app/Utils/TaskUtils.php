<?php

namespace App\Utils;

use App\Services\AcPEPMicroserviceClient;
use App\Services\AmPEP30MicroserviceClient;
use App\Services\AmPEPMicroserviceClient;
use App\Services\AmpRegressionECSAPredictMicroserviceClient;
use App\Services\BESToxMicroserviceClient;
use App\Services\CodonMicroserviceClient;
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
        // 新實作：優先使用微服務，必要時可回退到舊腳本（由上層控制）
        self::runAcPEPTaskMicroservice($task, $method);
    }

    public static function runAcPEPClassificationTask($task)
    {
        // 保留舊腳本方法供回退；預設在 Job 中已切到微服務
        $process = new Process([env('PYTHON_VER', 'python3'), '../xDeep-AcPEP-Classification/main.py', "../xDeep-AcPEP-Classification/$task->id.fasta"]);
        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    /**
     * 使用 xDeep-AcPEP 微服務執行各 tissue 方法，並寫出 <method>.out（空白分隔三欄：id prediction probability）
     * 注意：AcPEP 的 prediction 是連續值；為了與現有 AmPEP 檔案相容，這裡第二欄仍以二元標籤表示（> 0 則 1，否則 0），第三欄寫入連續分數
     */
    public static function runAcPEPTaskMicroservice($task, string $method)
    {
        try {
            $fastaPath = storage_path("app/Tasks/$task->id/input.fasta");
            $sequences = self::parseFastaFile($fastaPath);
            $items = array_map(function ($seq) {
                return [
                    'name' => $seq['id'],
                    'sequence' => $seq['sequence'],
                ];
            }, $sequences);

            $client = new AcPEPMicroserviceClient;
            $results = $client->predictBatchItems($method, $items);

            self::writeAcPEPMicroserviceResults($task->id, $method, $results);
            Log::info("xDeep-AcPEP microservice prediction completed, TaskID: {$task->id}, method: {$method}");
        } catch (\Throwable $e) {
            Log::error('xDeep-AcPEP microservice failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * 將 AcPEP 微服務結果寫為 <method>.out（空白分隔）
     * 行格式：sequence_name label score
     * - label：由 prediction 連續值轉換（> 0 -> 1；<= 0 -> 0）。如需調整閾值可之後改為 env 參數
     * - score：直接寫連續值 prediction
     */
    private static function writeAcPEPMicroserviceResults($taskId, string $method, array $results): void
    {
        $outputPath = storage_path("app/Tasks/$taskId/$method.out");

        // 讀取 FASTA 以保序
        $fastaPath = storage_path("app/Tasks/$taskId/input.fasta");
        $fastaContent = file_get_contents($fastaPath);
        if ($fastaContent === false) {
            throw new \Exception("Failed to read FASTA file: $fastaPath");
        }

        $order = [];
        foreach (explode("\n", $fastaContent) as $line) {
            if (strpos($line, '>') === 0) {
                $order[] = trim(substr($line, 1));
            }
        }

        // 建立 name -> payload 索引
        $map = [];
        foreach ($results as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = $row['name'] ?? null;
            if (! is_string($name)) {
                continue;
            }
            $prediction = $row['prediction'] ?? null;
            $outOfAd = $row['out_of_ad'] ?? false;

            $threshold = (float) env('XDEEP_ACPEP_LABEL_THRESHOLD', 0.0);

            if ($prediction === null || $outOfAd === true) {
                $score = 0.0;
                $label = '0';
                $map[$name] = [$name, $label, sprintf('%.6f', $score)];

                continue;
            }

            if (! is_numeric($prediction)) {
                continue;
            }
            $score = (float) $prediction;
            $label = $score > $threshold ? '1' : '0';
            $map[$name] = [$name, $label, sprintf('%.6f', $score)];
        }

        // 以 CSV 寫檔，並加入表頭，符合 FileUtils::matchingAcPEPClassification() 預期
        $fp = fopen($outputPath, 'w');
        if ($fp === false) {
            throw new \RuntimeException("Unable to open output file for writing: $outputPath");
        }
        // 表頭（內容不被消費，但會被丟棄）
        fputcsv($fp, ['id', 'classification', 'score']);

        foreach ($order as $seqName) {
            if (isset($map[$seqName])) {
                [$name, $label, $score] = $map[$seqName];
                fputcsv($fp, [$name, $label, $score]);
            } else {
                // 缺值以 0 分與 0 標籤
                fputcsv($fp, [$seqName, '0', '0']);
            }
        }
        fclose($fp);
        Log::info("xDeep-AcPEP CSV results written: {$outputPath}, total sequences: ".count($order));
    }

    /**
     * 使用 xDeep-AcPEP-Classification 微服務執行分類，寫出 xDeep-AcPEP-Classification.csv
     * 若失敗則拋出例外交由上層回退到舊腳本
     */
    public static function runAcPEPClassificationTaskMicroservice($task)
    {
        try {
            $fastaPath = storage_path("app/Tasks/$task->id/input.fasta");
            $fastaContent = file_get_contents($fastaPath);
            if ($fastaContent === false) {
                throw new \Exception("Failed to read FASTA file: $fastaPath");
            }

            $client = new \App\Services\AcPEPClassificationMicroserviceClient;
            $normalized = $client->predictFasta($fastaContent);

            self::writeAcPEPClassificationMicroserviceResults($task->id, $normalized);
            Log::info("xDeep-AcPEP-Classification microservice completed, TaskID: {$task->id}");
        } catch (\Throwable $e) {
            Log::error('xDeep-AcPEP-Classification microservice failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * 將微服務結果寫為 xDeep-AcPEP-Classification.csv
     * 目標格式：sequence_name,classification,confidence
     * 其中 classification 以 prediction(0/1) 映射為 label（與現有 FileUtils 匹配：value[1]）
     */
    private static function writeAcPEPClassificationMicroserviceResults($taskId, array $normalized): void
    {
        $outputPath = storage_path("app/Tasks/$taskId/xDeep-AcPEP-Classification.csv");

        // 依據 FASTA 保序
        $fastaPath = storage_path("app/Tasks/$taskId/input.fasta");
        $fastaContent = file_get_contents($fastaPath);
        if ($fastaContent === false) {
            throw new \Exception("Failed to read FASTA file: $fastaPath");
        }

        $order = [];
        foreach (explode("\n", $fastaContent) as $line) {
            if (strpos($line, '>') === 0) {
                $order[] = trim(substr($line, 1));
            }
        }

        // name -> row 索引
        $map = [];
        foreach ($normalized as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = $row['name'] ?? null;
            if (! is_string($name)) {
                continue;
            }
            $prediction = $row['prediction'] ?? null;
            $prob = $row['probability'] ?? null;
            if ($prediction === null || $prob === null) {
                continue;
            }

            // 將 0/1 映射為分類字串，與舊流程一致：FileUtils::matchingAcPEPScore 會讀 value[1] 作為 classification
            $label = ($prediction === 1 || $prediction === '1') ? '1' : '0';
            $map[$name] = [$name, $label, (float) $prob];
        }

        $lines = [];
        foreach ($order as $seqName) {
            if (isset($map[$seqName])) {
                [$name, $label, $prob] = $map[$seqName];
                $lines[] = sprintf('%s,%s,%.6f', $name, $label, $prob);
            } else {
                // 缺資料時以空分類與 0 分數
                $lines[] = sprintf('%s,,0', $seqName);
            }
        }

        file_put_contents($outputPath, implode("\n", $lines)."\n");
        Log::info("xDeep-AcPEP-Classification results written: {$outputPath}, total sequences: ".count($order));
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

    /**
     * 使用 Codon 微服務進行 ORF 抽取，並寫出 codon_orf.fasta
     * 失敗時拋出例外，交由上層回退到本地腳本
     */
    public static function runCodonTaskMicroservice($task, $codonCode = '1')
    {
        try {
            $fastaPath = storage_path("app/Tasks/$task->id/codon.fasta");
            $fastaContent = file_get_contents($fastaPath);
            if ($fastaContent === false) {
                throw new \Exception("Failed to read FASTA file: $fastaPath");
            }

            $client = new CodonMicroserviceClient;
            $codonTable = is_numeric($codonCode) ? (int) $codonCode : 1;
            $result = $client->predictBatchFasta($fastaContent, $codonTable);

            $outputPath = storage_path("app/Tasks/$task->id/codon_orf.fasta");
            file_put_contents($outputPath, (string) ($result['fasta'] ?? ''));
            Log::info("Codon microservice generated fasta for TaskID: {$task->id}");
        } catch (\Throwable $e) {
            Log::error('Codon microservice failed: '.$e->getMessage());
            throw $e;
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

    /**
     * 使用 BESTox 微服務進行毒性預測，並寫出 result.csv（格式：id,smiles,pre）
     * 失敗時拋出例外，交由上層回退到本地腳本
     */
    public static function runBESToxMicroservice($task)
    {
        try {
            $smiPath = storage_path("app/Tasks/$task->id/input.smi");
            $smiContent = file_get_contents($smiPath);
            if ($smiContent === false) {
                throw new \Exception("Failed to read SMI file: $smiPath");
            }

            $client = new BESToxMicroserviceClient;
            $results = $client->predictSmilesText($smiContent);

            self::writeBESToxMicroserviceResults($task->id, $results);
            Log::info("BESTox microservice prediction completed, TaskID: {$task->id}");
        } catch (\Throwable $e) {
            Log::error('BESTox microservice failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * 將 BESTox 微服務結果寫為 result.csv（格式：id,smiles,pre）
     * 與現有本地腳本輸出格式保持一致
     */
    private static function writeBESToxMicroserviceResults($taskId, array $results): void
    {
        $outputPath = storage_path("app/Tasks/$taskId/result.csv");

        // 讀取原始 SMI 檔案以保持分子順序
        $smiPath = storage_path("app/Tasks/$taskId/input.smi");
        $smiContent = file_get_contents($smiPath);
        if ($smiContent === false) {
            throw new \Exception("Failed to read SMI file: $smiPath");
        }

        $originalOrder = [];
        $lines = array_filter(array_map('trim', explode("\n", $smiContent)));
        foreach ($lines as $index => $line) {
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            $parts = preg_split('/\s+/', $line, 2);
            $smiles = $parts[0];
            $moleculeId = isset($parts[1]) ? $parts[1] : "mol_" . ($index + 1);

            $originalOrder[] = [
                'smiles' => $smiles,
                'molecule_id' => $moleculeId,
            ];
        }

        // 建立微服務結果的索引（以 smiles 或 molecule_id 為鍵）
        $resultMap = [];
        foreach ($results as $result) {
            $key = $result['molecule_id'] ?? $result['smiles'] ?? '';
            if (!empty($key)) {
                $resultMap[$key] = $result;
            }
        }

        // 開啟 CSV 檔案寫入
        $fp = fopen($outputPath, 'w');
        if ($fp === false) {
            throw new \RuntimeException("Unable to open output file for writing: $outputPath");
        }

        // 寫入 CSV 表頭（與舊版本格式一致）
        fputcsv($fp, ['id', 'smiles', 'pre']);

        // 依照原始順序寫入結果
        foreach ($originalOrder as $original) {
            $moleculeId = $original['molecule_id'];
            $smiles = $original['smiles'];

            // 優先以 molecule_id 匹配，其次用 smiles
            $result = $resultMap[$moleculeId] ?? $resultMap[$smiles] ?? null;

            if ($result && $result['status'] === 'success' && $result['ld50'] !== null) {
                // 成功預測：使用 LD50 值作為 pre 欄位
                $predictionValue = $result['ld50'];
                fputcsv($fp, [$moleculeId, $smiles, sprintf('%.6f', $predictionValue)]);
            } else {
                // 預測失敗或無結果：使用預設值
                $errorMsg = $result['error'] ?? 'No prediction available';
                Log::warning("BESTox prediction failed for $moleculeId ($smiles): $errorMsg");
                fputcsv($fp, [$moleculeId, $smiles, '0.000000']); // 預設值
            }
        }

        fclose($fp);
        Log::info("BESTox microservice results written: {$outputPath}, total molecules: ".count($originalOrder));
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
