<?php

/**
 * AMP Regression V2 API 部署啟用腳本
 *
 * 此腳本幫助安全地啟用新的V2 JSON API
 */
class AmpRegressionV2Deployer
{
    private $envPath;

    private $backupPath;

    public function __construct()
    {
        $this->envPath = __DIR__.'/../.env';
        $this->backupPath = __DIR__.'/../.env.backup.'.date('Y-m-d_H-i-s');
    }

    public function deploy()
    {
        echo "🚀 開始AMP Regression V2 API部署流程...\n\n";

        // 1. 檢查環境
        if (! $this->checkEnvironment()) {
            return false;
        }

        // 2. 備份配置
        if (! $this->backupEnvironment()) {
            return false;
        }

        // 3. 測試微服務連接
        if (! $this->testMicroserviceConnection()) {
            return false;
        }

        // 4. 啟用V2 API
        if (! $this->enableV2Api()) {
            return false;
        }

        // 5. 最終驗證
        if (! $this->finalValidation()) {
            return false;
        }

        echo "🎉 AMP Regression V2 API部署成功！\n";
        echo "✅ 新的JSON API現在已啟用，將在下次AmPEP任務中使用\n";
        echo "📋 備份文件: {$this->backupPath}\n";
        echo "🔄 如需回退，運行: php scripts/rollback_amp_regression_v2.php\n";

        return true;
    }

    private function checkEnvironment()
    {
        echo "1️⃣ 檢查環境配置...\n";

        if (! file_exists($this->envPath)) {
            echo "❌ .env文件不存在\n";

            return false;
        }

        if (! is_writable($this->envPath)) {
            echo "❌ .env文件不可寫入\n";

            return false;
        }

        echo "✅ 環境配置檢查通過\n\n";

        return true;
    }

    private function backupEnvironment()
    {
        echo "2️⃣ 備份環境配置...\n";

        if (! copy($this->envPath, $this->backupPath)) {
            echo "❌ 備份.env文件失敗\n";

            return false;
        }

        echo "✅ 環境配置備份完成: {$this->backupPath}\n\n";

        return true;
    }

    private function testMicroserviceConnection()
    {
        echo "3️⃣ 測試微服務連接...\n";

        // 使用curl測試健康檢查
        $healthUrl = $this->getBaseUrl().'/health';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $healthUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HEADER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            echo "❌ 微服務健康檢查失敗 (HTTP {$httpCode})\n";
            echo "   請確認AMP Regression微服務正在運行於: {$healthUrl}\n";

            return false;
        }

        $healthData = json_decode($response, true);
        if (! $healthData || ! isset($healthData['status']) || $healthData['status'] !== 'healthy') {
            echo "❌ 微服務健康狀態異常\n";

            return false;
        }

        echo "✅ 微服務連接測試通過\n";
        echo "   服務: {$healthData['service']}\n";
        echo "   版本: {$healthData['version']}\n";
        echo "   狀態: {$healthData['status']}\n\n";

        return true;
    }

    private function enableV2Api()
    {
        echo "4️⃣ 啟用V2 API...\n";

        $envContent = file_get_contents($this->envPath);

        // 檢查是否已存在配置
        if (strpos($envContent, 'USE_AMP_REGRESSION_V2_API') !== false) {
            // 更新現有配置
            $envContent = preg_replace(
                '/^USE_AMP_REGRESSION_V2_API=.*$/m',
                'USE_AMP_REGRESSION_V2_API=true',
                $envContent
            );
        } else {
            // 添加新配置
            $envContent .= "\n# AMP Regression V2 API Configuration\n";
            $envContent .= "USE_AMP_REGRESSION_V2_API=true\n";
        }

        if (! file_put_contents($this->envPath, $envContent)) {
            echo "❌ 更新.env文件失敗\n";

            return false;
        }

        echo "✅ V2 API配置已啟用\n\n";

        return true;
    }

    private function finalValidation()
    {
        echo "5️⃣ 最終驗證...\n";

        // 重新讀取.env文件檢查配置
        $envContent = file_get_contents($this->envPath);
        if (strpos($envContent, 'USE_AMP_REGRESSION_V2_API=true') === false) {
            echo "❌ V2 API配置驗證失敗\n";

            return false;
        }

        echo "✅ 最終驗證通過\n\n";

        return true;
    }

    private function getBaseUrl()
    {
        $envContent = file_get_contents($this->envPath);
        if (preg_match('/^AMP_REGRESSION_EC_SA_PREDICT_BASE_URL=(.*)$/m', $envContent, $matches)) {
            return trim($matches[1]);
        }

        return 'http://127.0.0.1:8889'; // 默認值
    }

    public function rollback()
    {
        echo "🔄 開始回退AMP Regression V2 API...\n\n";

        $backupFiles = glob(__DIR__.'/../.env.backup.*');
        if (empty($backupFiles)) {
            echo "❌ 未找到備份文件\n";

            return false;
        }

        // 使用最新的備份文件
        sort($backupFiles);
        $latestBackup = end($backupFiles);

        if (! copy($latestBackup, $this->envPath)) {
            echo "❌ 回退失敗：無法復制備份文件\n";

            return false;
        }

        echo "✅ 成功回退到V1 API\n";
        echo "📋 使用備份文件: {$latestBackup}\n";

        return true;
    }
}

// 檢查命令行參數
if ($argc > 1 && $argv[1] === 'rollback') {
    $deployer = new AmpRegressionV2Deployer;
    $success = $deployer->rollback();
    exit($success ? 0 : 1);
} else {
    $deployer = new AmpRegressionV2Deployer;
    $success = $deployer->deploy();
    exit($success ? 0 : 1);
}
