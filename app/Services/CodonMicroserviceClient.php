<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class CodonMicroserviceClient
{
    private Client $client;

    private string $baseUrl;

    public function __construct()
    {
        $this->client = new Client;
        // 預設依照 Genome Codon 團隊文檔，開發環境在 8005
        $this->baseUrl = env('CODON_MICROSERVICE_BASE_URL', 'http://localhost:8005');
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
            Log::error('Codon /health failed: '.$e->getMessage());

            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * 批次 ORF 抽取，輸入核酸 FASTA 文字，回傳 { count, fasta }
     * - codonTable: NCBI 遺傳密碼表（預設 1）
     * - minLen / maxLen: 肽段長度（不含起始 M）
     * - onlyStandardAminoAcids: 僅 20 種標準胺基酸
     */
    public function predictBatchFasta(
        string $fastaText,
        int $codonTable = 1,
        int $minLen = 5,
        int $maxLen = 250,
        bool $onlyStandardAminoAcids = true
    ): array {
        try {
            $resp = $this->client->request('POST', rtrim($this->baseUrl, '/').'/predict/batch', [
                'json' => [
                    'fasta' => $fastaText,
                    'codon_table' => $codonTable,
                    'min_len' => $minLen,
                    'max_len' => $maxLen,
                    'only_standard_amino_acids' => $onlyStandardAminoAcids,
                ],
                'timeout' => (int) env('CODON_MICROSERVICE_TIMEOUT', 300),
            ]);

            $raw = (string) $resp->getBody()->getContents();
            $body = json_decode($raw, true);
            if (! is_array($body)) {
                throw new \RuntimeException('Codon response is not a valid JSON object');
            }

            // 期望為 { count, fasta }
            if (! array_key_exists('fasta', $body)) {
                Log::error('Codon /predict/batch returned without fasta. Raw body (truncated): '.substr($raw, 0, 500));
                throw new \RuntimeException('Codon predict returned without fasta');
            }

            return [
                'count' => $body['count'] ?? null,
                'fasta' => (string) $body['fasta'],
            ];
        } catch (\Throwable $e) {
            Log::error('Codon /predict/batch failed: '.$e->getMessage());
            throw $e;
        }
    }
}
