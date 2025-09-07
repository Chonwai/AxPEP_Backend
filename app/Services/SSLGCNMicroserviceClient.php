<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class SSLGCNMicroserviceClient
{
    private Client $client;

    private string $baseUrl;

    private int $timeout;

    private int $maxRetries;

    /**
     * 支援的毒理學端點列表
     */
    private const SUPPORTED_TASK_TYPES = [
        'NR-AR', 'NR-AR-LBD', 'NR-AhR', 'NR-Aromatase',
        'NR-ER', 'NR-ER-LBD', 'NR-PPAR-gamma', 'SR-ARE',
        'SR-ATAD5', 'SR-HSE', 'SR-MMP', 'SR-p53',
    ];

    public function __construct()
    {
        $this->baseUrl = env('SSL_GCN_MICROSERVICE_BASE_URL', 'http://localhost:8007');
        $this->timeout = env('SSL_GCN_MICROSERVICE_TIMEOUT', 300);
        $this->maxRetries = env('SSL_GCN_MICROSERVICE_MAX_RETRIES', 3);

        $this->client = new Client([
            'timeout' => $this->timeout + 30, // 額外 30 秒緩衝
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'AxPEP-Backend/1.0',
            ],
        ]);
    }

    /**
     * 健康檢查
     */
    public function health(): array
    {
        try {
            $response = $this->client->request('GET', rtrim($this->baseUrl, '/').'/health', [
                'timeout' => 15,
            ]);

            return json_decode((string) $response->getBody()->getContents(), true) ?: [];
        } catch (\Throwable $e) {
            Log::error('SSL-GCN /health failed: '.$e->getMessage());

            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * 取得模型資訊
     */
    public function modelInfo(): array
    {
        try {
            $response = $this->client->request('GET', rtrim($this->baseUrl, '/').'/model/info', [
                'timeout' => 15,
            ]);

            return json_decode((string) $response->getBody()->getContents(), true) ?: [];
        } catch (\Throwable $e) {
            Log::error('SSL-GCN /model/info failed: '.$e->getMessage());

            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * 取得支援的毒理學端點列表
     */
    public function getSupportedTasks(): array
    {
        try {
            $response = $this->client->request('GET', rtrim($this->baseUrl, '/').'/predict/tasks', [
                'timeout' => 15,
            ]);

            return json_decode((string) $response->getBody()->getContents(), true) ?: [];
        } catch (\Throwable $e) {
            Log::error('SSL-GCN /predict/tasks failed: '.$e->getMessage());

            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * 單一分子毒理學預測
     *
     * @param  string  $moleculeId  分子識別符
     * @param  string  $smiles  SMILES 格式分子結構
     * @param  string  $taskType  毒理學端點類型
     * @return array 預測結果
     */
    public function predictSingle(string $moleculeId, string $smiles, string $taskType): array
    {
        $this->validateTaskType($taskType);

        $retries = 0;
        while ($retries < $this->maxRetries) {
            try {
                $payload = [
                    'molecule_id' => $moleculeId,
                    'smiles' => $smiles,
                    'task_type' => $taskType,
                ];

                $response = $this->client->request('POST', rtrim($this->baseUrl, '/').'/predict/single', [
                    'json' => $payload,
                    'timeout' => $this->timeout,
                ]);

                $result = json_decode((string) $response->getBody()->getContents(), true);

                if (! is_array($result)) {
                    throw new \RuntimeException('Invalid JSON response from SSL-GCN microservice');
                }

                Log::info('SSL-GCN single prediction completed', [
                    'molecule_id' => $moleculeId,
                    'task_type' => $taskType,
                    'prediction' => $result['prediction'] ?? 'unknown',
                ]);

                return $result;

            } catch (ConnectException $e) {
                $retries++;
                $waitTime = min(30, pow(2, $retries)); // 指數退避，最大 30 秒

                Log::warning("SSL-GCN connection failed, retry {$retries}/{$this->maxRetries} in {$waitTime}s: ".$e->getMessage());

                if ($retries >= $this->maxRetries) {
                    throw new \Exception("SSL-GCN microservice connection failed after {$this->maxRetries} retries: ".$e->getMessage());
                }

                sleep($waitTime);

            } catch (RequestException $e) {
                Log::error('SSL-GCN API request failed: '.$e->getMessage());
                throw new \Exception('SSL-GCN prediction failed: '.$e->getMessage());
            }
        }

        throw new \Exception('SSL-GCN prediction failed after all retries');
    }

    /**
     * 批量分子毒理學預測
     *
     * @param  array  $molecules  分子陣列，格式：[{molecule_id, smiles}]
     * @param  string  $taskType  毒理學端點類型
     * @return array 預測結果陣列
     */
    public function predictBatch(array $molecules, string $taskType): array
    {
        $this->validateTaskType($taskType);

        // 驗證批次大小（根據API文檔建議限制為50）
        if (count($molecules) > 50) {
            throw new \InvalidArgumentException('Batch size exceeds maximum limit of 50 molecules');
        }

        $retries = 0;
        while ($retries < $this->maxRetries) {
            try {
                $payload = [
                    'molecules' => $molecules,
                    'task_type' => $taskType,
                ];

                $response = $this->client->request('POST', rtrim($this->baseUrl, '/').'/predict/batch', [
                    'json' => $payload,
                    'timeout' => $this->timeout * 2, // 批次預測需要更長時間
                ]);

                $result = json_decode((string) $response->getBody()->getContents(), true);

                if (! is_array($result)) {
                    throw new \RuntimeException('Invalid JSON response from SSL-GCN microservice');
                }

                Log::info('SSL-GCN batch prediction completed', [
                    'total_molecules' => count($molecules),
                    'task_type' => $taskType,
                    'successful_predictions' => count($result),
                ]);

                return $result;

            } catch (ConnectException $e) {
                $retries++;
                $waitTime = min(30, pow(2, $retries));

                Log::warning("SSL-GCN batch connection failed, retry {$retries}/{$this->maxRetries} in {$waitTime}s: ".$e->getMessage());

                if ($retries >= $this->maxRetries) {
                    throw new \Exception("SSL-GCN microservice connection failed after {$this->maxRetries} retries: ".$e->getMessage());
                }

                sleep($waitTime);

            } catch (RequestException $e) {
                Log::error('SSL-GCN batch API request failed: '.$e->getMessage());
                throw new \Exception('SSL-GCN batch prediction failed: '.$e->getMessage());
            }
        }

        throw new \Exception('SSL-GCN batch prediction failed after all retries');
    }

    /**
     * 從 FASTA 格式內容進行批量預測（工具方法）
     * 注意：對於 SSL-GCN，FASTA 文件中的序列實際上是 SMILES 格式
     *
     * @param  string  $fastaContent  FASTA 格式內容
     * @param  string  $taskType  毒理學端點類型
     * @return array 標準化預測結果
     */
    public function predictFasta(string $fastaContent, string $taskType): array
    {
        $molecules = $this->parseFastaToMolecules($fastaContent);

        if (empty($molecules)) {
            throw new \InvalidArgumentException('No valid SMILES found in FASTA content');
        }

        // 如果分子數量過多，分批處理
        $allResults = [];
        $batchSize = 50;

        for ($i = 0; $i < count($molecules); $i += $batchSize) {
            $batch = array_slice($molecules, $i, $batchSize);
            $batchResults = $this->predictBatch($batch, $taskType);
            $allResults = array_merge($allResults, $batchResults);
        }

        return $allResults;
    }

    /**
     * 解析 FASTA 內容提取分子資訊
     * 對於 SSL-GCN，序列部分實際上是 SMILES 格式
     */
    private function parseFastaToMolecules(string $fastaContent): array
    {
        $molecules = [];
        $lines = array_filter(array_map('trim', explode("\n", $fastaContent)));

        $currentId = null;
        foreach ($lines as $line) {
            if (empty($line) || strpos($line, '#') === 0) {
                continue; // 跳過空行和註解
            }

            if (strpos($line, '>') === 0) {
                // Header 行
                $currentId = trim(substr($line, 1));
            } elseif ($currentId !== null) {
                // 序列行（實際上是 SMILES）
                $smiles = trim($line);
                if (! empty($smiles)) {
                    $molecules[] = [
                        'molecule_id' => $currentId,
                        'smiles' => $smiles,
                    ];
                    $currentId = null; // 重置，準備下一個分子
                }
            }
        }

        return $molecules;
    }

    /**
     * 驗證毒理學端點類型
     */
    private function validateTaskType(string $taskType): void
    {
        if (! in_array($taskType, self::SUPPORTED_TASK_TYPES)) {
            throw new \InvalidArgumentException("Unsupported task type: {$taskType}. Supported types: ".implode(', ', self::SUPPORTED_TASK_TYPES));
        }
    }

    /**
     * 檢查毒理學端點是否受支援
     */
    public function isTaskTypeSupported(string $taskType): bool
    {
        return in_array($taskType, self::SUPPORTED_TASK_TYPES);
    }

    /**
     * 取得所有支援的毒理學端點
     */
    public function getAllSupportedTaskTypes(): array
    {
        return self::SUPPORTED_TASK_TYPES;
    }
}
