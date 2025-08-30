<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class BESToxMicroserviceClient
{
    private Client $client;

    private string $baseUrl;

    public function __construct()
    {
        $this->client = new Client;
        $this->baseUrl = env('BESTOX_MICROSERVICE_BASE_URL', 'http://localhost:8006');
    }

    /**
     * 健康檢查
     */
    public function health(): array
    {
        try {
            $resp = $this->client->request('GET', rtrim($this->baseUrl, '/').'/health', [
                'timeout' => 15,
            ]);

            return json_decode((string) $resp->getBody()->getContents(), true) ?: [];
        } catch (\Throwable $e) {
            Log::error('BESTox /health failed: '.$e->getMessage());

            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * 取得模型資訊
     */
    public function modelInfo(): array
    {
        try {
            $resp = $this->client->request('GET', rtrim($this->baseUrl, '/').'/model/info', [
                'timeout' => 15,
            ]);

            return json_decode((string) $resp->getBody()->getContents(), true) ?: [];
        } catch (\Throwable $e) {
            Log::error('BESTox /model/info failed: '.$e->getMessage());

            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * 取得服務狀態
     */
    public function status(): array
    {
        try {
            $resp = $this->client->request('GET', rtrim($this->baseUrl, '/').'/status', [
                'timeout' => 15,
            ]);

            return json_decode((string) $resp->getBody()->getContents(), true) ?: [];
        } catch (\Throwable $e) {
            Log::error('BESTox /status failed: '.$e->getMessage());

            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * 單一分子毒性預測
     *
     * @param  string  $smiles  SMILES 格式分子結構
     * @param  string|null  $moleculeId  分子識別符
     * @return array 預測結果
     */
    public function predictSingle(string $smiles, ?string $moleculeId = null): array
    {
        try {
            $payload = ['smiles' => $smiles];
            if ($moleculeId !== null) {
                $payload['molecule_id'] = $moleculeId;
            }

            $resp = $this->client->request('POST', rtrim($this->baseUrl, '/').'/predict/single', [
                'json' => $payload,
                'timeout' => 300,
            ]);

            $raw = (string) $resp->getBody()->getContents();
            $body = json_decode($raw, true);

            if (! is_array($body)) {
                throw new \RuntimeException('Invalid JSON response');
            }

            return $body;
        } catch (\Throwable $e) {
            Log::error('BESTox predictSingle failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * 批次分子毒性預測
     *
     * @param  array  $molecules  分子陣列，格式：[{smiles, molecule_id}]
     * @param  string|null  $batchId  批次識別符
     * @return array 標準化預測結果
     */
    public function predictBatch(array $molecules, ?string $batchId = null): array
    {
        try {
            // 驗證批次大小（API 限制最多 100 個）
            if (count($molecules) > 100) {
                throw new \InvalidArgumentException('Batch size exceeds maximum limit of 100 molecules');
            }

            $payload = ['molecules' => $molecules];
            if ($batchId !== null) {
                $payload['batch_id'] = $batchId;
            }

            $resp = $this->client->request('POST', rtrim($this->baseUrl, '/').'/predict/batch', [
                'json' => $payload,
                'timeout' => 3600, // 批次預測可能需要更長時間
            ]);

            $raw = (string) $resp->getBody()->getContents();
            $body = json_decode($raw, true);

            if (! is_array($body)) {
                throw new \RuntimeException('Invalid JSON response');
            }

            return $this->normalizeBatchResponse($body);
        } catch (\Throwable $e) {
            Log::error('BESTox predictBatch failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * 從 SMILES 文字內容進行批次預測（工具方法）
     *
     * @param  string  $smilesText  SMILES 文字內容，每行一個 SMILES
     * @return array 標準化預測結果
     */
    public function predictSmilesText(string $smilesText): array
    {
        $molecules = [];
        $lines = array_filter(array_map('trim', explode("\n", $smilesText)));

        foreach ($lines as $index => $line) {
            if (empty($line) || strpos($line, '#') === 0) {
                continue; // 跳過空行和註解
            }

            // 支援 "SMILES ID" 或 "SMILES" 格式
            $parts = preg_split('/\s+/', $line, 2);
            $smiles = $parts[0];
            $moleculeId = isset($parts[1]) ? $parts[1] : 'mol_'.($index + 1);

            $molecules[] = [
                'smiles' => $smiles,
                'molecule_id' => $moleculeId,
            ];
        }

        if (empty($molecules)) {
            throw new \InvalidArgumentException('No valid SMILES found in input text');
        }

        return $this->predictBatch($molecules);
    }

    /**
     * 標準化批次預測回應格式
     * 確保回應格式與現有系統相容
     */
    private function normalizeBatchResponse(array $body): array
    {
        if (! isset($body['success']) || ! $body['success']) {
            $errorMsg = $body['error_message'] ?? 'Unknown error';
            throw new \RuntimeException("BESTox prediction failed: $errorMsg");
        }

        $predictions = $body['predictions'] ?? [];
        $failedMolecules = $body['failed_molecules'] ?? [];

        $normalized = [];
        foreach ($predictions as $prediction) {
            $normalized[] = [
                'molecule_id' => $prediction['molecule_id'] ?? 'unknown',
                'smiles' => $prediction['smiles'] ?? '',
                'ld50' => $prediction['ld50'] ?? null,
                'log10_ld50' => $prediction['log10_ld50'] ?? null,
                'prediction_confidence' => $prediction['prediction_confidence'] ?? null,
                'processing_time_ms' => $prediction['processing_time_ms'] ?? null,
                'status' => 'success',
            ];
        }

        // 處理失敗的分子
        foreach ($failedMolecules as $failedSmiles) {
            $normalized[] = [
                'molecule_id' => 'unknown',
                'smiles' => $failedSmiles,
                'ld50' => null,
                'log10_ld50' => null,
                'prediction_confidence' => null,
                'processing_time_ms' => null,
                'status' => 'failed',
                'error' => 'Prediction failed',
            ];
        }

        Log::info('BESTox batch prediction normalized', [
            'total_processed' => $body['total_processed'] ?? 0,
            'total_successful' => $body['total_successful'] ?? 0,
            'total_failed' => $body['total_failed'] ?? 0,
        ]);

        return $normalized;
    }
}
