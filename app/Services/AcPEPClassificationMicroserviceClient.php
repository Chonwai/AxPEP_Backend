<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class AcPEPClassificationMicroserviceClient
{
    private Client $client;

    private string $baseUrl;

    public function __construct()
    {
        $this->client = new Client;
        // 預設依照 xDeep-AcPEP-Classification 團隊文檔，開發環境在 8003
        $this->baseUrl = env('XDEEP_ACPEP_CLASSIFICATION_BASE_URL', 'http://localhost:8003');
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
            Log::error('xDeep-AcPEP-Classification /health failed: '.$e->getMessage());

            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * 批次預測（FASTA 文本）
     * 回傳標準化陣列：[{ name, prediction, probability }]
     */
    public function predictFasta(string $fastaText): array
    {
        try {
            $resp = $this->client->request('POST', rtrim($this->baseUrl, '/').'/predict/batch', [
                'json' => [
                    'fasta' => $fastaText,
                ],
                'timeout' => 3600,
            ]);

            $raw = (string) $resp->getBody()->getContents();
            $body = json_decode($raw, true);
            if (! is_array($body)) {
                throw new \RuntimeException('Invalid JSON response');
            }

            return $this->normalizeBatchResponse($body);
        } catch (\Throwable $e) {
            Log::error('xDeep-AcPEP-Classification predictFasta failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * 將不同格式的回應標準化為 [{ name, prediction, probability }]
     */
    private function normalizeBatchResponse(array $body): array
    {
        $items = [];
        if (isset($body['data']['predictions']) && is_array($body['data']['predictions'])) {
            $items = $body['data']['predictions'];
        } elseif (isset($body['data']) && is_array($body['data'])) {
            // 某些實作可能直接給陣列
            $items = $body['data'];
        } elseif (isset($body['results']) && is_array($body['results'])) {
            $items = $body['results'];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $name = $this->scalar($item['name'] ?? $item['sequence_name'] ?? null);
            $prediction = $this->scalar($item['prediction'] ?? null);
            $probability = $this->scalar($item['probability'] ?? $item['confidence'] ?? null);

            if ($name === null || $prediction === null || $probability === null) {
                continue;
            }
            $normalized[] = [
                'name' => (string) $name,
                'prediction' => is_numeric($prediction) ? (int) $prediction : (string) $prediction,
                'probability' => is_numeric($probability) ? (float) $probability : 0.0,
            ];
        }

        return $normalized;
    }

    private function scalar($v)
    {
        if (is_array($v)) {
            return reset($v);
        }

        return $v;
    }
}
