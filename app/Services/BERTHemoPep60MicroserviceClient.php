<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class BERTHemoPep60MicroserviceClient
{
    private $client;

    private $baseUrl;

    public function __construct()
    {
        $this->client = new Client;
        // 從配置中獲取URL，或使用默認值
        $this->baseUrl = env('BERT_HEMOPEP60_MICROSERVICE_BASE_URL', 'http://localhost:9001');
    }

    /**
     * 發送預測請求到BERT-HemoPep60微服務
     *
     * @param  string  $fastaContent  FASTA格式的序列
     * @return array 預測結果
     *
     * @throws \Exception 當API調用失敗時
     */
    public function predict($fastaContent)
    {
        try {
            \Log::info('開始調用BERT-HemoPep60微服務');
            $response = $this->client->request('POST', $this->baseUrl.'/api/predict', [
                'json' => [
                    'fasta' => $fastaContent,
                    'model_type' => 'BERT-HemoPep60',
                ],
                'timeout' => 300, // 5分鐘超時
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            // 驗證API響應
            if (! isset($result['status']) || $result['status'] !== 'success') {
                throw new \Exception('API返回非成功狀態: '.json_encode($result));
            }

            \Log::info('成功獲取BERT-HemoPep60微服務響應: '.substr(json_encode($result), 0, 200).'...');

            return $result;
        } catch (RequestException $e) {
            Log::error('BERT-HemoPep60微服務調用失敗: '.$e->getMessage());
            throw new \Exception('微服務調用失敗: '.$e->getMessage());
        } catch (\Exception $e) {
            Log::error('BERT-HemoPep60數據處理錯誤: '.$e->getMessage());
            throw $e;
        }
    }
}
