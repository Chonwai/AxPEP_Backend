<?php

namespace App\Console\Commands;

use App\Services\AmPEPMicroserviceClient;
use Illuminate\Console\Command;

class CheckAmPEPMicroservice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ampep:health-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check AmPEP microservice health status';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🔍 檢查 AmPEP 微服務健康狀態...');

        $client = new AmPEPMicroserviceClient;

        // 健康檢查
        $healthResult = $client->healthCheck();

        if ($healthResult && isset($healthResult['status']) && $healthResult['status'] === 'healthy') {
            $this->info('✅ AmPEP 微服務健康狀態：正常');
            $this->line("   版本: {$healthResult['version']}");
            $this->line("   時間: {$healthResult['timestamp']}");

            if (isset($healthResult['model_loaded'])) {
                $modelStatus = $healthResult['model_loaded'] ? '已載入' : '未載入';
                $this->line("   模型狀態: {$modelStatus}");
            }
        } else {
            $this->error('❌ AmPEP 微服務健康檢查失敗');
            if ($healthResult) {
                $this->line('響應: '.json_encode($healthResult, JSON_PRETTY_PRINT));
            }

            return 1;
        }

        // 服務信息檢查
        $this->info('📊 獲取服務信息...');
        $infoResult = $client->getServiceInfo();

        if ($infoResult) {
            $this->info('✅ 服務信息獲取成功');
            $this->line("   服務名稱: {$infoResult['service_name']}");
            $this->line("   描述: {$infoResult['description']}");
            $this->line('   支援方法: '.implode(', ', $infoResult['supported_methods']));
        } else {
            $this->warn('⚠️  無法獲取服務信息');
        }

        // 簡單預測測試
        $this->info('🧪 執行簡單預測測試...');

        try {
            // 創建測試FASTA內容
            $testFasta = ">test_sequence\nALWKTMLKKLGTMALHAGKAALGAAADTISQGTQ";

            // 創建臨時文件
            $tempDir = storage_path('app/temp');
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $tempFile = $tempDir.'/test_'.time().'.fasta';
            file_put_contents($tempFile, $testFasta);

            // 執行預測
            $relativePath = str_replace(storage_path('app/'), '', $tempFile);
            $result = $client->predict("storage/app/{$relativePath}", 'health_check');

            // 清理臨時文件
            unlink($tempFile);

            if ($result && $result['status'] === 'success') {
                $this->info('✅ 預測測試成功');
                $sequenceCount = count($result['data']);
                $this->line("   處理序列數: {$sequenceCount}");
            } else {
                $this->error('❌ 預測測試失敗');
                if ($result) {
                    $this->line('響應: '.json_encode($result, JSON_PRETTY_PRINT));
                }

                return 1;
            }

        } catch (\Exception $e) {
            $this->error('❌ 預測測試異常: '.$e->getMessage());

            return 1;
        }

        $this->info('🎉 所有檢查完成！AmPEP 微服務運行正常。');

        return 0;
    }
}
