<?php

namespace App\Console\Commands;

use App\Services\SSLGCNMicroserviceClient;
use Illuminate\Console\Command;

class TestSSLGCNMicroservice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ssl-gcn:test {--method=SR-p53}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '測試 SSL-GCN 微服務連接和功能';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🧪 開始測試 SSL-GCN 微服務整合...');

        $method = $this->option('method');
        $client = new SSLGCNMicroserviceClient;

        // 1. 測試健康檢查
        $this->info('📊 測試健康檢查...');
        try {
            $health = $client->health();
            if (isset($health['status']) && $health['status'] === 'healthy') {
                $this->info('✅ 健康檢查通過');
            } else {
                $this->warn('⚠️  健康檢查返回非正常狀態: '.json_encode($health));
            }
        } catch (\Exception $e) {
            $this->error('❌ 健康檢查失敗: '.$e->getMessage());
        }

        // 2. 測試模型資訊
        $this->info('🤖 測試模型資訊...');
        try {
            $modelInfo = $client->modelInfo();
            $this->info('✅ 模型資訊獲取成功: '.json_encode($modelInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            $this->error('❌ 模型資訊獲取失敗: '.$e->getMessage());
        }

        // 3. 測試支援的任務類型
        $this->info('📋 測試支援的任務類型...');
        try {
            $tasks = $client->getSupportedTasks();
            $this->info('✅ 支援的任務類型: '.json_encode($tasks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            $this->error('❌ 獲取支援任務類型失敗: '.$e->getMessage());
        }

        // 4. 測試單一分子預測
        $this->info('🔬 測試單一分子預測...');
        try {
            // 測試用的 SMILES（阿斯匹靈）
            $testSmiles = 'CC(=O)OC1=CC=CC=C1C(=O)O';
            $result = $client->predictSingle('aspirin_test', $testSmiles, $method);
            $this->info('✅ 單一分子預測成功: '.json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            $this->error('❌ 單一分子預測失敗: '.$e->getMessage());
        }

        // 5. 測試批量預測
        $this->info('🧬 測試批量預測...');
        try {
            $testMolecules = [
                ['molecule_id' => 'aspirin', 'smiles' => 'CC(=O)OC1=CC=CC=C1C(=O)O'],
                ['molecule_id' => 'ethanol', 'smiles' => 'CCO'],
                ['molecule_id' => 'caffeine', 'smiles' => 'CN1C=NC2=C1C(=O)N(C(=O)N2C)C'],
            ];
            $results = $client->predictBatch($testMolecules, $method);
            $this->info('✅ 批量預測成功: '.json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            $this->error('❌ 批量預測失敗: '.$e->getMessage());
        }

        // 6. 測試 FASTA 格式預測
        $this->info('📄 測試 FASTA 格式預測...');
        try {
            $fastaContent = ">aspirin\nCC(=O)OC1=CC=CC=C1C(=O)O\n>ethanol\nCCO\n>caffeine\nCN1C=NC2=C1C(=O)N(C(=O)N2C)C";
            $results = $client->predictFasta($fastaContent, $method);
            $this->info('✅ FASTA 格式預測成功: '.json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            $this->error('❌ FASTA 格式預測失敗: '.$e->getMessage());
        }

        // 7. 測試環境變數
        $this->info('⚙️  檢查環境變數配置...');
        $baseUrl = env('SSL_GCN_MICROSERVICE_BASE_URL', 'http://localhost:8007');
        $timeout = env('SSL_GCN_MICROSERVICE_TIMEOUT', 300);
        $enabled = env('USE_SSL_GCN_MICROSERVICE', true);

        $this->info("🔗 微服務 URL: {$baseUrl}");
        $this->info("⏱️  超時設定: {$timeout} 秒");
        $this->info('🔧 微服務啟用狀態: '.($enabled ? '啟用' : '停用'));

        $this->info('');
        $this->info('🎉 SSL-GCN 微服務測試完成！');

        return 0;
    }
}
