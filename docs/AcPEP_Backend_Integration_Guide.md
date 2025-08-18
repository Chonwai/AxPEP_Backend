# AcPEP 後端整合實施指南

## 📋 概述

本指南詳細說明如何將 AcPEP 從傳統 Python 腳本架構遷移到微服務架構，參考 AmPEP30 的成功實踐，提供完整的實施步驟和程式碼範例。

## 🎯 整合目標

1. **最小改動原則** - 保持現有 FileUtils 和下載 API 不變
2. **無縫切換** - 支持微服務/本地腳本雙模式運行
3. **向後兼容** - 生成相同格式的結果文件
4. **錯誤處理** - 自動回退機制保證系統穩定性

## 🏗️ 實施架構

```
📦 AcPEP 整合架構
├── 🔄 AcPEPMicroserviceClient     # HTTP 客戶端
├── 🛠️ TaskUtils 新增方法          # 微服務調用邏輯  
├── 🎯 AcPEPJob 更新               # 任務處理邏輯
├── 📁 環境變數配置                # 功能開關
└── 📊 結果文件適配器              # 格式轉換
```

## 📝 實施步驟

### 步驟 1: 創建 AcPEP 微服務客戶端

創建 `app/Services/AcPEPMicroserviceClient.php`：

```php
<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class AcPEPMicroserviceClient
{
    private Client $client;
    private string $baseUrl;
    private string $classificationUrl;

    public function __construct()
    {
        $this->client = new Client;
        $this->baseUrl = env('ACPEP_MICROSERVICE_BASE_URL', 'http://localhost:8003');
        $this->classificationUrl = env('ACPEP_CLASSIFICATION_BASE_URL', 'http://localhost:8004');
    }

    /**
     * 預測服務 - 支持多種方法
     */
    public function predictFasta(string $fastaContent, string $method, int $precision = 3): array
    {
        try {
            $response = $this->client->request('POST', rtrim($this->baseUrl, '/').'/predict/fasta', [
                'form_params' => [
                    'fasta_content' => $fastaContent,
                    'method' => $method,
                    'precision' => $precision,
                ],
                'timeout' => 3600, // 1小時超時
            ]);

            $raw = (string) $response->getBody()->getContents();
            $parsed = $this->normalizeResponse(json_decode($raw, true));
            
            if (empty($parsed)) {
                Log::error("AcPEP /{$method} prediction returned empty results. Raw: " . substr($raw, 0, 500));
                throw new \Exception("AcPEP {$method} prediction returned no results");
            }

            return $parsed;
        } catch (\Throwable $e) {
            Log::error("AcPEP {$method} prediction failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 分類服務
     */
    public function classifyFasta(string $fastaContent): array
    {
        try {
            $response = $this->client->request('POST', rtrim($this->classificationUrl, '/').'/classify', [
                'form_params' => [
                    'fasta_content' => $fastaContent,
                ],
                'timeout' => 3600,
            ]);

            $raw = (string) $response->getBody()->getContents();
            $data = json_decode($raw, true);
            
            if (!$data || !isset($data['results']) || !is_array($data['results'])) {
                Log::error("AcPEP classification returned invalid format. Raw: " . substr($raw, 0, 500));
                throw new \Exception("AcPEP classification returned invalid results");
            }

            return $data['results'];
        } catch (\Throwable $e) {
            Log::error("AcPEP classification failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 健康檢查
     */
    public function healthCheck(): array
    {
        $results = [];
        
        // 檢查預測服務
        try {
            $response = $this->client->request('GET', rtrim($this->baseUrl, '/').'/health', [
                'timeout' => 10,
            ]);
            $results['prediction'] = json_decode($response->getBody()->getContents(), true);
        } catch (\Throwable $e) {
            Log::error('AcPEP prediction service health check failed: ' . $e->getMessage());
            $results['prediction'] = ['status' => 'error', 'error' => $e->getMessage()];
        }

        // 檢查分類服務
        try {
            $response = $this->client->request('GET', rtrim($this->classificationUrl, '/').'/health', [
                'timeout' => 10,
            ]);
            $results['classification'] = json_decode($response->getBody()->getContents(), true);
        } catch (\Throwable $e) {
            Log::error('AcPEP classification service health check failed: ' . $e->getMessage());
            $results['classification'] = ['status' => 'error', 'error' => $e->getMessage()];
        }

        return $results;
    }

    /**
     * 響應標準化處理
     */
    private function normalizeResponse(?array $body): array
    {
        if (!$body) {
            return [];
        }

        // 處理不同的響應格式
        $items = [];
        if (isset($body['results']) && is_array($body['results'])) {
            $items = $body['results'];
        } elseif (isset($body['data']) && is_array($body['data'])) {
            $items = $body['data'];
        } elseif (is_array($body)) {
            if (array_key_exists(0, $body)) {
                $items = $body;
            } elseif (isset($body['prediction'])) {
                $items = [$body];
            }
        }

        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $prediction = $this->extractScalarValue($item['prediction'] ?? null);
            $probability = $this->extractScalarValue($item['acp_probability'] ?? $item['probability'] ?? null);
            $name = $this->extractScalarValue($item['sequence_name'] ?? $item['name'] ?? null);
            $status = $this->extractScalarValue($item['status'] ?? 'success');
            $error = $this->extractScalarValue($item['error'] ?? null);

            // 檢查是否為錯誤響應
            $isError = ($status === 'error') || ($prediction === null && $probability === null && $error !== null);

            if ($isError) {
                $normalized[] = [
                    'name' => $name,
                    'prediction' => 'ERROR',
                    'probability' => 0.0,
                    'error' => $error ?: 'Unknown error',
                ];
            } elseif ($prediction !== null && $probability !== null) {
                $normalized[] = [
                    'name' => $name,
                    'prediction' => $this->normalizeLabel($prediction),
                    'probability' => is_numeric($probability) ? (float) $probability : 0.0,
                ];
            }
        }

        return $normalized;
    }

    /**
     * 從數組或標量中提取值
     */
    private function extractScalarValue($value)
    {
        if (is_array($value)) {
            return reset($value);
        }
        return $value;
    }

    /**
     * 標籤標準化
     */
    private function normalizeLabel($label): string
    {
        $value = is_string($label) ? strtolower(trim($label)) : $label;

        if ($value === 1 || $value === '1' || $value === 'acp') {
            return 'ACP'; // 抗癌肽
        }
        if ($value === 0 || $value === '0' || $value === 'non-acp' || $value === 'non_acp' || $value === 'nonacp') {
            return 'non-ACP';
        }

        return (is_string($label) && stripos($label, 'acp') !== false) ? 'ACP' : 'non-ACP';
    }
}
```

### 步驟 2: 更新 TaskUtils

在 `app/Utils/TaskUtils.php` 中新增方法：

```php
/**
 * 使用微服務運行 AcPEP 預測任務
 */
public static function runAcPEPTaskMicroservice($task, $method)
{
    try {
        $fastaPath = storage_path("app/Tasks/$task->id/input.fasta");
        $fastaContent = file_get_contents($fastaPath);
        if ($fastaContent === false) {
            throw new \Exception("Failed to read FASTA file: $fastaPath");
        }

        $client = new \App\Services\AcPEPMicroserviceClient();
        $results = $client->predictFasta($fastaContent, $method);

        self::writeAcPEPMicroserviceResults($task->id, $method, $results);
        Log::info("AcPEP microservice prediction completed, TaskID: {$task->id}, Method: {$method}");
    } catch (\Exception $e) {
        Log::error("AcPEP microservice failed, TaskID: {$task->id}, Method: {$method}, Error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * 使用微服務運行 AcPEP 分類任務
 */
public static function runAcPEPClassificationTaskMicroservice($task)
{
    try {
        $fastaPath = storage_path("app/Tasks/$task->id/input.fasta");
        $fastaContent = file_get_contents($fastaPath);
        if ($fastaContent === false) {
            throw new \Exception("Failed to read FASTA file: $fastaPath");
        }

        $client = new \App\Services\AcPEPMicroserviceClient();
        $results = $client->classifyFasta($fastaContent);

        self::writeAcPEPClassificationMicroserviceResults($task->id, $results);
        Log::info("AcPEP microservice classification completed, TaskID: {$task->id}");
    } catch (\Exception $e) {
        Log::error("AcPEP microservice classification failed, TaskID: {$task->id}, Error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * 將 AcPEP 微服務預測結果寫為 {method}.out 文件
 */
private static function writeAcPEPMicroserviceResults($taskId, string $method, array $data): void
{
    try {
        $outputPath = storage_path("app/Tasks/$taskId/$method.out");
        
        // 從 FASTA 獲取正確的序列名稱順序
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

        // 建立 name -> result 映射
        $nameToResult = [];
        foreach ($data as $result) {
            if (is_array($result) && isset($result['name']) && is_string($result['name'])) {
                $nameToResult[$result['name']] = $result;
            }
        }

        $lines = [];
        foreach ($fastaSequenceNames as $index => $sequenceName) {
            $result = null;
            if (isset($nameToResult[$sequenceName])) {
                $result = $nameToResult[$sequenceName];
            } elseif (isset($data[$index]) && is_array($data[$index])) {
                $result = $data[$index];
            }

            if ($result === null) {
                Log::warning("[$method] Missing prediction for sequence $sequenceName in task $taskId");
                continue;
            }

            $prediction = $result['prediction'] ?? null;
            $probability = $result['probability'] ?? null;

            // 處理錯誤響應
            if ($prediction === 'ERROR') {
                $errorMsg = $result['error'] ?? 'Unknown error';
                Log::info("[$method] Error response for sequence $sequenceName in task $taskId: $errorMsg");
                $lines[] = sprintf('%s -1 -1 # %s', $sequenceName, $errorMsg);
                continue;
            }

            if ($prediction === null || $probability === null) {
                Log::warning("[$method] Invalid prediction for sequence $sequenceName in task $taskId");
                continue;
            }

            // 標準化預測結果：ACP=1, non-ACP=0, ERROR=-1
            $predictionNum = ($prediction === 'ACP' || $prediction === 1 || $prediction === '1') ? 1 : 0;
            $lines[] = sprintf('%s %d %.3f', $sequenceName, $predictionNum, (float) $probability);
        }

        file_put_contents($outputPath, implode("\n", $lines) . "\n");
        Log::info("[$method] Results written to $outputPath for task $taskId");

    } catch (\Exception $e) {
        Log::error("Failed to write AcPEP microservice results for method $method in task $taskId: " . $e->getMessage());
        throw $e;
    }
}

/**
 * 將 AcPEP 微服務分類結果寫為 CSV 文件
 */
private static function writeAcPEPClassificationMicroserviceResults($taskId, array $results): void
{
    try {
        $outputPath = storage_path("app/Tasks/$taskId/xDeep-AcPEP-Classification.csv");
        
        $lines = ['sequence_name,classification,confidence'];
        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }
            
            $sequenceName = $result['sequence_name'] ?? 'unknown';
            $classification = $result['classification'] ?? 'unknown';
            $confidence = $result['confidence'] ?? 0.0;
            
            $lines[] = sprintf('%s,%s,%.3f', $sequenceName, $classification, (float) $confidence);
        }
        
        file_put_contents($outputPath, implode("\n", $lines) . "\n");
        Log::info("AcPEP classification results written to $outputPath for task $taskId");
        
    } catch (\Exception $e) {
        Log::error("Failed to write AcPEP classification results for task $taskId: " . $e->getMessage());
        throw $e;
    }
}
```

### 步驟 3: 更新 AcPEPJob

修改 `app/Jobs/AcPEPJob.php`：

```php
public function handle()
{
    // 處理預測任務
    foreach ($this->request['methods'] as $key => $value) {
        if ($value == true) {
            $useMicroservice = env('USE_ACPEP_MICROSERVICE', false);
            
            if ($useMicroservice) {
                try {
                    Log::info("嘗試使用AcPEP微服務，TaskID: {$this->task->id}, Method: {$key}");
                    TaskUtils::runAcPEPTaskMicroservice($this->task, $key);
                    TaskUtils::renameAcPEPResultFile($this->task, $key);
                    Log::info("AcPEP微服務調用成功，TaskID: {$this->task->id}, Method: {$key}");
                } catch (\Exception $e) {
                    Log::error("AcPEP微服務調用失敗，回退到本地腳本，TaskID: {$this->task->id}, Method: {$key}, 錯誤: {$e->getMessage()}");
                    // 回退到原有方法
                    TaskUtils::runAcPEPTask($this->task, $key);
                    TaskUtils::renameAcPEPResultFile($this->task, $key);
                }
            } else {
                // 使用原有方法
                TaskUtils::runAcPEPTask($this->task, $key);
                TaskUtils::renameAcPEPResultFile($this->task, $key);
            }
        }
    }

    // 處理分類任務
    TaskUtils::copyAcPEPInputFile($this->task);
    
    $useClassificationMicroservice = env('USE_ACPEP_CLASSIFICATION_MICROSERVICE', false);
    
    if ($useClassificationMicroservice) {
        try {
            Log::info("嘗試使用AcPEP分類微服務，TaskID: {$this->task->id}");
            TaskUtils::runAcPEPClassificationTaskMicroservice($this->task);
            Log::info("AcPEP分類微服務調用成功，TaskID: {$this->task->id}");
        } catch (\Exception $e) {
            Log::error("AcPEP分類微服務調用失敗，回退到本地腳本，TaskID: {$this->task->id}, 錯誤: {$e->getMessage()}");
            // 回退到原有方法
            TaskUtils::runAcPEPClassificationTask($this->task);
            TaskUtils::renameAcPEPClassificationResultFile($this->task);
        }
    } else {
        // 使用原有方法
        TaskUtils::runAcPEPClassificationTask($this->task);
        TaskUtils::renameAcPEPClassificationResultFile($this->task);
    }

    AcPEPServices::getInstance()->finishedTask($this->task->id);
}
```

### 步驟 4: 環境變數配置

在 `.env` 文件中添加：

```env
# AcPEP 微服務配置
USE_ACPEP_MICROSERVICE=false
USE_ACPEP_CLASSIFICATION_MICROSERVICE=false
ACPEP_MICROSERVICE_BASE_URL=http://localhost:8003
ACPEP_CLASSIFICATION_BASE_URL=http://localhost:8004
```

### 步驟 5: 健康檢查命令

創建 `app/Console/Commands/CheckAcPEPMicroservice.php`：

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AcPEPMicroserviceClient;

class CheckAcPEPMicroservice extends Command
{
    protected $signature = 'acpep:health-check';
    protected $description = 'Check AcPEP microservice health status';

    public function handle()
    {
        $this->info('Checking AcPEP Microservice Health...');
        
        try {
            $client = new AcPEPMicroserviceClient();
            $results = $client->healthCheck();
            
            $this->info('Health Check Results:');
            $this->line(json_encode($results, JSON_PRETTY_PRINT));
            
            // 檢查服務狀態
            $predictionHealthy = isset($results['prediction']['status']) && 
                               (is_array($results['prediction']['status']) ? 
                                in_array('healthy', $results['prediction']['status']) : 
                                $results['prediction']['status'] === 'healthy');
                                
            $classificationHealthy = isset($results['classification']['status']) && 
                                   (is_array($results['classification']['status']) ? 
                                    in_array('healthy', $results['classification']['status']) : 
                                    $results['classification']['status'] === 'healthy');
            
            if ($predictionHealthy && $classificationHealthy) {
                $this->info('✅ All AcPEP microservices are healthy!');
                return Command::SUCCESS;
            } else {
                $this->error('❌ Some AcPEP microservices are not healthy');
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $this->error('Health check failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
```

## 🧪 測試腳本

創建測試腳本 `tests/Feature/AcPEPMicroserviceTest.php`：

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\AcPEPMicroserviceClient;

class AcPEPMicroserviceTest extends TestCase
{
    private $client;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new AcPEPMicroserviceClient();
    }

    public function testHealthCheck()
    {
        if (!env('USE_ACPEP_MICROSERVICE', false)) {
            $this->markTestSkipped('AcPEP microservice is disabled');
        }

        $result = $this->client->healthCheck();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('prediction', $result);
        $this->assertArrayHasKey('classification', $result);
    }

    public function testPredictFasta()
    {
        if (!env('USE_ACPEP_MICROSERVICE', false)) {
            $this->markTestSkipped('AcPEP microservice is disabled');
        }

        $fastaContent = ">test_seq\nGLFDIVKKVVGALGSL";
        $result = $this->client->predictFasta($fastaContent, 'method1');
        
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('prediction', $result[0]);
        $this->assertArrayHasKey('probability', $result[0]);
    }

    public function testClassifyFasta()
    {
        if (!env('USE_ACPEP_CLASSIFICATION_MICROSERVICE', false)) {
            $this->markTestSkipped('AcPEP classification microservice is disabled');
        }

        $fastaContent = ">test_seq\nGLFDIVKKVVGALGSL";
        $result = $this->client->classifyFasta($fastaContent);
        
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('classification', $result[0]);
        $this->assertArrayHasKey('confidence', $result[0]);
    }
}
```

## 🚀 部署和測試

### 本地測試
```bash
# 1. 啟動 AcPEP 微服務（需要 AcPEP 團隊提供）
docker-compose up acpep-prediction acpep-classification

# 2. 檢查服務健康狀態
php artisan acpep:health-check

# 3. 運行測試
php artisan test --filter AcPEPMicroserviceTest

# 4. 啟用微服務模式
echo "USE_ACPEP_MICROSERVICE=true" >> .env
echo "USE_ACPEP_CLASSIFICATION_MICROSERVICE=true" >> .env

# 5. 測試完整流程
# 通過 API 提交 AcPEP 任務並觀察日誌
```

### 生產部署
1. 部署 AcPEP 微服務到生產環境
2. 更新環境變數指向生產 URL
3. 逐步啟用微服務功能
4. 監控性能和錯誤率

## 📊 監控和日誌

### 關鍵指標
- 🕐 **響應時間** - 微服務調用延遲
- 📊 **成功率** - 預測和分類成功比例  
- 🔄 **回退率** - 回退到本地腳本的頻率
- 🚨 **錯誤率** - 各種錯誤類型統計

### 日誌示例
```
[INFO] 嘗試使用AcPEP微服務，TaskID: 12345, Method: method1
[INFO] AcPEP microservice prediction completed, TaskID: 12345, Method: method1  
[INFO] AcPEP微服務調用成功，TaskID: 12345, Method: method1
[ERROR] AcPEP微服務調用失敗，回退到本地腳本，TaskID: 12345, Method: method1, 錯誤: Connection timeout
```

## 🎉 完成檢查清單

- [ ] 實現 `AcPEPMicroserviceClient`
- [ ] 更新 `TaskUtils` 添加微服務方法  
- [ ] 修改 `AcPEPJob` 支持切換
- [ ] 添加環境變數配置
- [ ] 實現健康檢查命令
- [ ] 編寫測試腳本
- [ ] 本地測試驗證
- [ ] 性能基準測試
- [ ] 生產部署計劃
- [ ] 監控告警配置

---

**實施指南版本**: 1.0.0  
**最後更新**: 2024年12月30日

這份指南提供了完整的實施路線圖，確保 AcPEP 微服務集成的成功和系統穩定性！
