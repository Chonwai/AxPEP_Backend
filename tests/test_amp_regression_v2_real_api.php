<?php

/**
 * AMP Regression V2 真實API測試腳本
 * 測試與已實現的微服務API的兼容性
 */

require_once __DIR__.'/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class AmpRegressionV2RealApiTest
{
    private $client;

    private $baseUrl;

    public function __construct()
    {
        $this->client = new Client;
        $this->baseUrl = 'http://localhost:8889'; // 真實的微服務地址
    }

    /**
     * 測試健康檢查
     */
    public function testHealthCheck()
    {
        echo "🔍 測試微服務健康檢查...\n";

        try {
            $response = $this->client->request('GET', $this->baseUrl.'/health', [
                'timeout' => 10,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            echo '✅ 健康檢查成功: '.json_encode($result)."\n";

            return true;

        } catch (\Exception $e) {
            echo '❌ 健康檢查失敗: '.$e->getMessage()."\n";

            return false;
        }
    }

    /**
     * 測試服務信息
     */
    public function testServiceInfo()
    {
        echo "📋 測試服務信息獲取...\n";

        try {
            $response = $this->client->request('GET', $this->baseUrl.'/api/info', [
                'timeout' => 10,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            echo '✅ 服務信息獲取成功: '.json_encode($result)."\n";

            return true;

        } catch (\Exception $e) {
            echo '⚠️ 服務信息獲取失敗: '.$e->getMessage()."\n";

            return false;
        }
    }

    /**
     * 測試V2 JSON API預測
     */
    public function testV2JsonApiPrediction()
    {
        echo "🧪 測試V2 JSON API序列預測...\n";

        // 使用來自command line測試的數據
        $testData = [
            'task_id' => 'test_from_laravel_'.uniqid(),
            'sequences' => [
                [
                    'id' => 'seq1',
                    'sequence' => 'MKTVRQERLKSIVRILERSKEPVSGAQLAEELSVSRQVIVQDIAYLRSLGYNIVATPRGYVLAGG',
                ],
            ],
        ];

        try {
            $response = $this->client->request('POST', $this->baseUrl.'/predict/sequences', [
                'json' => $testData,
                'timeout' => 60,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            // 驗證響應格式
            assert(isset($result['status']), '響應應該包含status');
            assert($result['status'] === true, '狀態應該為true');
            assert(isset($result['task_id']), '響應應該包含task_id');
            assert(isset($result['predictions']), '響應應該包含predictions');
            assert(isset($result['total_sequences']), '響應應該包含total_sequences');
            assert($result['total_sequences'] === 1, '序列總數應該為1');

            // 驗證預測結果格式
            $prediction = $result['predictions'][0];
            assert(isset($prediction['id']), '預測應該包含id');
            assert(isset($prediction['sequence']), '預測應該包含sequence');
            assert(isset($prediction['ec_predicted_MIC_μM']), '預測應該包含ec_predicted_MIC_μM');
            assert(isset($prediction['sa_predicted_MIC_μM']), '預測應該包含sa_predicted_MIC_μM');

            echo "✅ V2 JSON API測試成功！\n";
            echo "📊 響應詳情:\n";
            echo "   - Task ID: {$result['task_id']}\n";
            echo "   - 序列數量: {$result['total_sequences']}\n";
            echo "   - EC預測值: {$prediction['ec_predicted_MIC_μM']}\n";
            echo "   - SA預測值: {$prediction['sa_predicted_MIC_μM']}\n";

            return $result;

        } catch (RequestException $e) {
            echo '❌ V2 API調用失敗: '.$e->getMessage()."\n";
            if ($e->hasResponse()) {
                echo '   響應內容: '.$e->getResponse()->getBody()."\n";
            }

            return false;
        } catch (\Exception $e) {
            echo '❌ 測試過程出錯: '.$e->getMessage()."\n";

            return false;
        }
    }

    /**
     * 測試多序列預測
     */
    public function testMultipleSequencesPrediction()
    {
        echo "🧪 測試多序列V2 JSON API預測...\n";

        // 使用實際任務中的FASTA數據
        $testData = [
            'task_id' => 'test_multiple_'.uniqid(),
            'sequences' => [
                [
                    'id' => 'AC_1',
                    'sequence' => 'ALWKTMLKKLGTMALHAGKAALGAAADTISQGTQ',
                ],
                [
                    'id' => 'AC_2',
                    'sequence' => 'AWKKWAKAWKWAKAKWWAKAA',
                ],
            ],
        ];

        try {
            $response = $this->client->request('POST', $this->baseUrl.'/predict/sequences', [
                'json' => $testData,
                'timeout' => 60,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            // 驗證多序列響應
            assert($result['total_sequences'] === 2, '序列總數應該為2');
            assert(count($result['predictions']) === 2, '預測結果數量應該為2');

            echo "✅ 多序列V2 JSON API測試成功！\n";
            echo "📊 多序列響應詳情:\n";
            echo "   - Task ID: {$result['task_id']}\n";
            echo "   - 序列數量: {$result['total_sequences']}\n";

            foreach ($result['predictions'] as $i => $prediction) {
                echo '   - 序列'.($i + 1)." ({$prediction['id']}):\n";
                echo "     * EC預測值: {$prediction['ec_predicted_MIC_μM']}\n";
                echo "     * SA預測值: {$prediction['sa_predicted_MIC_μM']}\n";
            }

            return $result;

        } catch (\Exception $e) {
            echo '❌ 多序列測試失敗: '.$e->getMessage()."\n";

            return false;
        }
    }

    /**
     * 測試CSV結果生成（模擬TaskUtils邏輯）
     */
    public function testCsvResultGeneration($predictions)
    {
        echo "📝 測試CSV結果生成...\n";

        $csvData = "sequence_id,ec_predicted_MIC_μM,sa_predicted_MIC_μM\n";

        foreach ($predictions as $prediction) {
            $csvData .= "{$prediction['id']},{$prediction['ec_predicted_MIC_μM']},{$prediction['sa_predicted_MIC_μM']}\n";
        }

        $testFile = sys_get_temp_dir().'/test_amp_regression_v2_result.csv';
        file_put_contents($testFile, $csvData);

        // 驗證CSV內容
        $content = file_get_contents($testFile);
        assert(strpos($content, 'sequence_id,ec_predicted_MIC_μM,sa_predicted_MIC_μM') !== false, 'CSV應該包含正確的標題');

        echo "✅ CSV結果生成測試成功！\n";
        echo "📄 CSV內容預覽:\n";
        echo substr($content, 0, 200)."...\n";

        // 清理測試文件
        unlink($testFile);

        return true;
    }

    public function runAllTests()
    {
        echo "🚀 開始AMP Regression V2 真實API測試...\n\n";

        try {
            // 1. 健康檢查
            if (! $this->testHealthCheck()) {
                echo "⚠️ 微服務不可用，請確認服務正在運行於 http://localhost:8889\n";

                return false;
            }

            echo "\n";

            // 2. 服務信息
            $this->testServiceInfo();
            echo "\n";

            // 3. 單序列預測測試
            $singleResult = $this->testV2JsonApiPrediction();
            if (! $singleResult) {
                return false;
            }

            echo "\n";

            // 4. 多序列預測測試
            $multiResult = $this->testMultipleSequencesPrediction();
            if (! $multiResult) {
                return false;
            }

            echo "\n";

            // 5. CSV生成測試
            $this->testCsvResultGeneration($multiResult['predictions']);

            echo "\n🎉 所有真實API測試通過！\n";
            echo "✅ AMP Regression V2 JSON API完全兼容並可以投入使用！\n";

            return true;

        } catch (Exception $e) {
            echo "\n❌ 測試失敗: ".$e->getMessage()."\n";
            echo 'Stack trace: '.$e->getTraceAsString()."\n";

            return false;
        }
    }
}

// 運行測試
$test = new AmpRegressionV2RealApiTest;
$success = $test->runAllTests();

if ($success) {
    echo "\n🏆 恭喜！您可以立即啟用V2 API：\n";
    echo "   在.env中設置: USE_AMP_REGRESSION_V2_API=true\n";
    exit(0);
} else {
    echo "\n❌ 測試失敗，請檢查微服務狀態\n";
    exit(1);
}
