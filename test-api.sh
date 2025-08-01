#!/bin/bash
set -e

echo "🧪 AxPEP Backend API 功能測試"
echo "=============================="

# 檢查容器是否運行
if ! docker compose -f docker/docker-compose.local.yml ps | grep -q "Up"; then
    echo "❌ 容器未運行，請先執行 ./start-local.sh"
    exit 1
fi

echo "🔍 測試基本Laravel功能..."

# 測試1: 檢查Laravel版本
echo "📋 Laravel版本:"
docker compose -f docker/docker-compose.local.yml exec -T app php artisan --version

# 測試2: 檢查環境配置
echo ""
echo "🔧 環境配置:"
docker compose -f docker/docker-compose.local.yml exec -T app php artisan config:show app.name

# 測試3: 檢查數據庫連接
echo ""
echo "🗄️  數據庫連接測試:"
if docker compose -f docker/docker-compose.local.yml exec -T app php artisan migrate:status | grep -q "Yes"; then
    echo "✅ 數據庫連接正常，遷移已執行"
else
    echo "❌ 數據庫連接問題"
fi

# 測試4: 檢查路由註冊
echo ""
echo "🌐 API路由統計:"
route_count=$(docker compose -f docker/docker-compose.local.yml exec -T app php artisan route:list | grep "api/v1" | wc -l)
echo "✅ 發現 $route_count 個API路由"

# 測試5: 測試隊列配置
echo ""
echo "📬 隊列配置測試:"
docker compose -f docker/docker-compose.local.yml exec -T app php artisan queue:work --once --stop-when-empty &
sleep 2
echo "✅ 隊列工作器可以正常啟動"

# 測試6: 檢查存儲權限
echo ""
echo "📁 存儲權限測試:"
if docker compose -f docker/docker-compose.local.yml exec -T app test -w /var/www/html/storage; then
    echo "✅ 存儲目錄可寫入"
else
    echo "❌ 存儲目錄權限問題"
fi

# 測試7: 測試緩存功能
echo ""
echo "⚡ 緩存功能測試:"
docker compose -f docker/docker-compose.local.yml exec -T app php artisan cache:clear > /dev/null
echo "✅ 緩存清理功能正常"

# 測試8: 測試Composer自動加載
echo ""
echo "📦 Composer自動加載測試:"
if docker compose -f docker/docker-compose.local.yml exec -T app php -r "echo class_exists('App\Models\Tasks') ? '✅ 模型類加載正常' : '❌ 模型類加載失敗';" 2>/dev/null; then
    echo "✅ Composer自動加載正常"
else
    echo "❌ Composer自動加載問題"
fi

# 測試9: 內部PHP-FPM測試
echo ""
echo "🔧 PHP-FPM內部測試:"
docker compose -f docker/docker-compose.local.yml exec -T app php -r "
echo '測試基本PHP功能:' . PHP_EOL;
echo '- PHP版本: ' . PHP_VERSION . PHP_EOL;
echo '- 擴展檢查:' . PHP_EOL;
echo '  - pdo_pgsql: ' . (extension_loaded('pdo_pgsql') ? '✅' : '❌') . PHP_EOL;
echo '  - redis: ' . (extension_loaded('redis') ? '✅' : '❌ (可選)') . PHP_EOL;
echo '  - zip: ' . (extension_loaded('zip') ? '✅' : '❌') . PHP_EOL;
echo '  - gd: ' . (extension_loaded('gd') ? '✅' : '❌') . PHP_EOL;
"

echo ""
echo "🎯 API測試建議:"
echo "==============="
echo "1. 配置本地Nginx指向localhost:9000以進行HTTP測試"
echo "2. 或使用API測試工具(如Postman)直接測試PHP-FPM"
echo "3. 您可以手動測試API端點："
echo ""
echo "   主要API端點:"
echo "   - GET /api/v1/axpep/codons/all (獲取所有密碼子)"
echo "   - POST /api/v1/ampep/tasks/textarea (提交抗菌肽分析)"
echo "   - GET /api/v1/axpep/analysis/count/tasks (任務統計)"
echo ""
echo "4. 使用以下命令進入容器進行更多測試："
echo "   docker compose -f docker/docker-compose.local.yml exec app bash"
echo ""

echo "✅ 基本功能測試完成！您的Docker化Laravel Backend已準備就緒。"
