<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * AMP Regression EC SA Predict 微服務客戶端 V2
 *
 * 使用現代化的JSON API設計，支持直接傳遞序列數據
 * 遵循系統中AmPEPMicroserviceClient和BERTHemoPep60MicroserviceClient的成功模式
 */
class AmpRegressionECSAPredictMicroserviceClientV2
{
    private $client;

    private $baseUrl;

    public function __construct()
    {
        $this->client = new Client;
        $this->baseUrl = env('AMP_REGRESSION_EC_SA_PREDICT_BASE_URL', 'http://127.0.0.1:8889');
    }

    /**
     * 發送序列預測請求到AMP Regression微服務 (JSON API)
     *
     * @param  string  $taskId  任務ID
     * @param  array  $sequences  序列數據陣列 [['id' => 'AC_1', 'sequence' => 'ALWK...'], ...]
     * @return array 預測結果
     *
     * @throws \Exception 當API調用失敗時
     */
    public function predictSequences($taskId, $sequences)
    {
        try {
            Log::info("開始調用AMP Regression V2 微服務，TaskID: {$taskId}，序列數量: ".count($sequences));

            // 發送JSON請求，遵循現有超時設置
            $response = $this->client->request('POST', $this->baseUrl.'/predict/sequences', [
                'json' => [
                    'task_id' => $taskId,
                    'sequences' => $sequences,
                ],
                'timeout' => 3600, // 保持與現有實現一致的超時時間
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            // 驗證API響應，遵循現有錯誤處理模式
            if (! isset($result['status']) || $result['status'] !== true) {
                throw new \Exception('API返回非成功狀態: '.json_encode($result));
            }

            // 驗證預測結果格式
            if (! isset($result['predictions']) || ! is_array($result['predictions'])) {
                throw new \Exception('API響應格式錯誤：缺少predictions字段');
            }

            Log::info("成功獲取AMP Regression V2 微服務響應，TaskID: {$taskId}，預測結果數量: ".count($result['predictions']));

            return $result;

        } catch (RequestException $e) {
            Log::error("AMP Regression V2 微服務調用失敗，TaskID: {$taskId}, Error: ".$e->getMessage());
            throw new \Exception('微服務調用失敗: '.$e->getMessage());
        } catch (\Exception $e) {
            Log::error("AMP Regression V2 數據處理錯誤，TaskID: {$taskId}, Error: ".$e->getMessage());
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
            Log::error('AMP Regression V2 微服務健康檢查失敗: '.$e->getMessage());

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
            Log::error('AMP Regression V2 服務信息獲取失敗: '.$e->getMessage());

            return;
        }
    }
}
