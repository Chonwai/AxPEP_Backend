<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class AmPEP30MicroserviceClient
{
    private Client $client;

    private string $baseUrl;

    public function __construct()
    {
        $this->client = new Client;
        $this->baseUrl = env('DEEPAMPEP30_MICROSERVICE_BASE_URL', 'http://host.docker.internal:8002');
    }

    /**
     * 嘗試使用 JSON Router (/api/predict)，失敗則回退到 Final API (/predict/fasta)
     * 回傳結果已標準化為陣列，每筆含 prediction 與 probability
     */
    public function predictFasta(string $fastaContent, string $method = 'rf', int $precision = 3): array
    {
        // 1) Alternative router: /api/predict (JSON)
        // try {
        //     $response = $this->client->request('POST', rtrim($this->baseUrl, '/').'/api/predict', [
        //         'json' => [
        //             'fasta' => $fastaContent,
        //             'method' => $method,
        //         ],
        //         'timeout' => 3600,
        //     ]);

        //     $parsed = $this->normalizeResponse(json_decode($response->getBody()->getContents(), true));
        //     if (! empty($parsed)) {
        //         return $parsed;
        //     }

        //     throw new \Exception('Empty parsed results from /api/predict');
        // } catch (\Throwable $e) {
        //     Log::warning('AmPEP30 /api/predict failed, fallback to /predict/fasta: '.$e->getMessage());
        // }

        // 2) Final API: /predict/fasta (form)
        try {
            $response = $this->client->request('POST', rtrim($this->baseUrl, '/').'/predict/fasta', [
                'form_params' => [
                    'fasta_content' => $fastaContent,
                    'method' => $method,
                    'precision' => $precision,
                ],
                'timeout' => 3600,
            ]);

            $raw = (string) $response->getBody()->getContents();
            $parsed = $this->normalizeResponse(json_decode($raw, true));
            if (empty($parsed)) {
                Log::error('AmPEP30 /predict/fasta returned empty after normalization. Raw body (truncated): '.substr($raw, 0, 500));
                throw new \Exception('AmPEP30 predict returned no results');
            }

            return $parsed;
        } catch (\Throwable $e) {
            Log::error('AmPEP30 /predict/fasta failed: '.$e->getMessage());
            throw $e;
        }
    }

    public function healthCheck()
    {
        try {
            $response = $this->client->request('GET', rtrim($this->baseUrl, '/').'/health', [
                'timeout' => 10,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Throwable $e) {
            Log::error('AmPEP30 health check failed: '.$e->getMessage());

            return;
        }
    }

    /**
     * 將不同路由的回應標準化為 [ { prediction, probability }, ... ]
     */
    private function normalizeResponse(?array $body): array
    {
        if (! $body) {
            return [];
        }

        // 可能的結構：
        // - { status: 'success', results: [ ... ] }
        // - { status: 'success', data: [ ... ] }
        // - [ { ... } ] 或單筆物件
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
        foreach ($items as $it) {
            if (! is_array($it)) {
                continue;
            }
            $predictionRaw = $it['prediction'] ?? null;
            $prob = $it['amp_probability'] ?? ($it['probability'] ?? null);
            $name = $it['sequence_name'] ?? ($it['name'] ?? null);

            // 將非標量值轉為可用標量
            if (is_array($predictionRaw)) {
                $predictionRaw = reset($predictionRaw);
            }
            if (is_array($prob)) {
                $prob = reset($prob);
            }

            if ($predictionRaw === null || $prob === null) {
                continue;
            }

            $normalized[] = [
                'name' => is_scalar($name) ? (string) $name : null,
                'prediction' => $this->normalizeLabel($predictionRaw),
                'probability' => is_numeric($prob) ? (float) $prob : (float) (string) $prob,
            ];
        }

        return $normalized;
    }

    private function normalizeLabel($label): string
    {
        $value = is_string($label) ? strtolower(trim($label)) : $label;

        if ($value === 1 || $value === '1' || $value === 'amp') {
            return 'AMP';
        }
        if ($value === 0 || $value === '0' || $value === 'non-amp' || $value === 'non_amp' || $value === 'nonamp') {
            return 'non-AMP';
        }

        return (is_string($label) && stripos($label, 'amp') !== false) ? 'AMP' : 'non-AMP';
    }
}
