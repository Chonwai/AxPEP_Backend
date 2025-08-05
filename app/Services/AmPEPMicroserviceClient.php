<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AmPEPMicroserviceClient
{
    private $client;

    private $baseUrl;

    public function __construct()
    {
        $this->client = new Client;
        $this->baseUrl = env('AMPEP_MICROSERVICE_BASE_URL', 'http://localhost:8001');
    }

    /**
     * 發送預測請求到AmPEP微服務
     *
     * @param  string  $fastaPath  FASTA文件路徑
     * @param  string  $taskId  任務ID
     * @return array 預測結果
     *
     * @throws \Exception 當API調用失敗時
     */
    public function predict($fastaPath, $taskId = null)
    {
        try {
            Log::info("開始調用AmPEP微服務，TaskID: {$taskId}");

            // 讀取FASTA內容，遵循現有模式
            $relativePath = str_replace('storage/app/', '', $fastaPath);
            if (! Storage::exists($relativePath)) {
                throw new \Exception("File not found: {$relativePath}");
            }
            $fastaContent = Storage::get($relativePath);

            // 發送請求，遵循現有超時設置
            $response = $this->client->request('POST', $this->baseUrl.'/api/predict', [
                'json' => [
                    'fasta' => $fastaContent,
                ],
                'timeout' => 3600, // 保持與現有R腳本一致的超時時間
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            // 驗證API響應，遵循現有錯誤處理模式
            if (! isset($result['status']) || $result['status'] !== 'success') {
                throw new \Exception('API返回非成功狀態: '.json_encode($result));
            }

            Log::info("成功獲取AmPEP微服務響應，TaskID: {$taskId}");

            return $result;

        } catch (RequestException $e) {
            Log::error("AmPEP微服務調用失敗，TaskID: {$taskId}, Error: ".$e->getMessage());
            throw new \Exception('微服務調用失敗: '.$e->getMessage());
        } catch (\Exception $e) {
            Log::error("AmPEP數據處理錯誤，TaskID: {$taskId}, Error: ".$e->getMessage());
            throw $e;
        }
    }

    /**
     * 檢查微服務健康狀態
     */
    public function healthCheck()
    {
        try {
            $response = $this->client->request('GET', $this->baseUrl.'/health', [
                'timeout' => 10,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('AmPEP微服務健康檢查失敗: '.$e->getMessage());

            return;
        }
    }

    /**
     * 獲取服務信息
     */
    public function getServiceInfo()
    {
        try {
            $response = $this->client->request('GET', $this->baseUrl.'/api/info', [
                'timeout' => 10,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('AmPEP服務信息獲取失敗: '.$e->getMessage());

            return;
        }
    }
}
