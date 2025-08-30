<?php

namespace App\Console\Commands;

use App\Services\BESToxMicroserviceClient;
use Illuminate\Console\Command;

class CheckBESToxMicroservice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bestox:health-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check BESTox microservice health status';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🔍 檢查 BESTox 微服務健康狀態...');

        $client = new BESToxMicroserviceClient;

        // 健康檢查
        $healthResult = $client->health();

        if ($healthResult && isset($healthResult['status']) && $healthResult['status'] === 'healthy') {
            $this->info('✅ BESTox 微服務健康狀態：正常');
            $this->line("   版本: {$healthResult['version']}");
            $this->line("   時間: {$healthResult['timestamp']}");

            if (isset($healthResult['model_loaded'])) {
                $modelStatus = $healthResult['model_loaded'] ? '已載入' : '未載入';
                $this->line("   模型狀態: {$modelStatus}");
            }
        } else {
            $this->error('❌ BESTox 微服務健康檢查失敗');
            if ($healthResult) {
                $this->line('響應: '.json_encode($healthResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            return 1;
        }

        // 模型信息檢查
        $this->info('📊 獲取模型信息...');
        $modelInfo = $client->modelInfo();

        if ($modelInfo && ! isset($modelInfo['status'])) {
            $this->info('✅ 模型信息獲取成功');
            $this->line("   模型名稱: {$modelInfo['model_name']}");
            $this->line("   模型版本: {$modelInfo['model_version']}");
            $this->line("   輸入格式: {$modelInfo['input_format']}");
            $this->line("   最大序列長度: {$modelInfo['max_sequence_length']}");
            if (isset($modelInfo['supported_features'])) {
                $this->line('   支援功能: '.implode(', ', $modelInfo['supported_features']));
            }
        } else {
            $this->warn('⚠️  無法獲取模型信息');
            if (isset($modelInfo['error'])) {
                $this->line("   錯誤: {$modelInfo['error']}");
            }
        }

        // 服務狀態檢查
        $this->info('📈 獲取服務狀態...');
        $statusResult = $client->status();

        if ($statusResult && ! isset($statusResult['status']) || $statusResult['status'] === 'healthy') {
            $this->info('✅ 服務狀態獲取成功');
            if (isset($statusResult['service_name'])) {
                $this->line("   服務名稱: {$statusResult['service_name']}");
            }
            if (isset($statusResult['uptime_seconds'])) {
                $uptime = round($statusResult['uptime_seconds'] / 60, 2);
                $this->line("   運行時間: {$uptime} 分鐘");
            }
            if (isset($statusResult['total_predictions'])) {
                $this->line("   總預測次數: {$statusResult['total_predictions']}");
            }
            if (isset($statusResult['memory_usage_mb'])) {
                $this->line("   記憶體使用: {$statusResult['memory_usage_mb']} MB");
            }
        } else {
            $this->warn('⚠️  無法獲取服務狀態');
        }

        // 簡單預測測試
        $this->info('🧪 執行簡單預測測試...');

        try {
            // 測試用的 SMILES 分子
            $testSmiles = 'CC(C)=CCO'; // 3-methyl-2-buten-1-ol (簡單有機分子)

            $this->line("   測試分子: {$testSmiles}");

            // 執行單一預測測試
            $result = $client->predictSingle($testSmiles, 'health_check_molecule');

            if ($result && isset($result['success']) && $result['success']) {
                $this->info('✅ 單一預測測試成功');
                $prediction = $result['prediction'];
                $this->line("   分子 ID: {$prediction['molecule_id']}");
                $this->line("   SMILES: {$prediction['smiles']}");
                $this->line('   LD50: '.number_format($prediction['ld50'], 6).' mg/kg');
                $this->line('   Log10 LD50: '.number_format($prediction['log10_ld50'], 6));
                if (isset($prediction['processing_time_ms'])) {
                    $this->line("   處理時間: {$prediction['processing_time_ms']} ms");
                }
            } else {
                $this->error('❌ 單一預測測試失敗');
                if ($result) {
                    $this->line('響應: '.json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }

                return 1;
            }

        } catch (\Exception $e) {
            $this->error('❌ 預測測試異常: '.$e->getMessage());

            return 1;
        }

        // 批量預測測試
        $this->info('🧪 執行批量預測測試...');

        try {
            // 測試用的多個 SMILES 分子
            $testMolecules = [
                ['smiles' => 'CC(C)=CCO', 'molecule_id' => 'test_mol_1'],
                ['smiles' => 'CCO', 'molecule_id' => 'test_mol_2'],
                ['smiles' => 'C1=CC=CC=C1', 'molecule_id' => 'test_mol_3'], // 苯環
            ];

            $this->line('   測試分子數量: '.count($testMolecules));

            // 執行批量預測測試
            $result = $client->predictBatch($testMolecules, 'health_check_batch');

            if ($result && count($result) > 0) {
                $successCount = 0;
                foreach ($result as $prediction) {
                    if ($prediction['status'] === 'success') {
                        $successCount++;
                    }
                }

                $this->info('✅ 批量預測測試成功');
                $this->line("   成功預測: {$successCount}/".count($testMolecules));

                // 顯示第一個成功的預測結果
                foreach ($result as $prediction) {
                    if ($prediction['status'] === 'success') {
                        $this->line("   範例結果 - {$prediction['molecule_id']}: LD50 = ".number_format($prediction['ld50'], 6).' mg/kg');
                        break;
                    }
                }
            } else {
                $this->error('❌ 批量預測測試失敗');

                return 1;
            }

        } catch (\Exception $e) {
            $this->error('❌ 批量預測測試異常: '.$e->getMessage());

            return 1;
        }

        $this->info('🎉 所有檢查完成！BESTox 微服務運行正常。');
        $this->line('');
        $this->line('📋 環境變數配置:');
        $this->line('   BESTOX_MICROSERVICE_BASE_URL='.env('BESTOX_MICROSERVICE_BASE_URL', 'http://localhost:8006'));
        $this->line('   USE_BESTOX_MICROSERVICE='.(env('USE_BESTOX_MICROSERVICE', true) ? 'true' : 'false'));

        return 0;
    }
}
