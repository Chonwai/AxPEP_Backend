<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class AcPEPMicroserviceClient
{
    private Client $client;

    private string $baseUrl;

    public function __construct()
    {
        $this->client = new Client;
        $this->baseUrl = env('XDEEP_ACPEP_BASE_URL', 'http://localhost:8004');
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
            Log::error('xDeep-AcPEP /health failed: '.$e->getMessage());

            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * 取得模型資訊（可用 tissue 等）
     */
    public function modelInfo(): array
    {
        try {
            $resp = $this->client->request('GET', rtrim($this->baseUrl, '/').'/model/info', [
                'timeout' => 15,
            ]);

            return json_decode((string) $resp->getBody()->getContents(), true) ?: [];
        } catch (\Throwable $e) {
            Log::error('xDeep-AcPEP /model/info failed: '.$e->getMessage());

            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * 批次預測（以 items 陣列 + tissue）
     * items: [{ name, sequence }]
     * 回傳標準化陣列：[{ name, tissue, prediction, out_of_ad }]
     */
    public function predictBatchItems(string $tissue, array $items): array
    {
        try {
            $resp = $this->client->request('POST', rtrim($this->baseUrl, '/').'/predict/batch', [
                'json' => [
                    'tissue' => $tissue,
                    'items' => $items,
                ],
                'timeout' => 3600,
            ]);

            $raw = (string) $resp->getBody()->getContents();
            $body = json_decode($raw, true);
            if (! is_array($body)) {
                throw new \RuntimeException('Invalid JSON response');
            }

            return $this->normalizeBatchResponse($body, $tissue);
        } catch (\Throwable $e) {
            Log::error('xDeep-AcPEP predictBatchItems failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * 將回應標準化為 [{ name, tissue, prediction, out_of_ad }]
     */
    private function normalizeBatchResponse(array $body, string $tissue): array
    {
        $items = [];
        if (isset($body['results']) && is_array($body['results'])) {
            $items = $body['results'];
        } elseif (isset($body['data']['results']) && is_array($body['data']['results'])) {
            $items = $body['data']['results'];
        } elseif (isset($body['data']) && is_array($body['data'])) {
            $items = $body['data'];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $name = $this->scalar($item['name'] ?? null);
            $prediction = $this->scalar($item['prediction'] ?? null);
            $outOfAd = $this->scalar($item['out_of_ad'] ?? false);

            if ($name === null || $prediction === null) {
                continue;
            }

            $normalized[] = [
                'name' => (string) $name,
                'tissue' => $item['tissue'] ?? $tissue,
                'prediction' => is_numeric($prediction) ? (float) $prediction : $prediction,
                'out_of_ad' => (bool) $outOfAd,
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
