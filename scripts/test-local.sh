#!/bin/bash
set -e

echo "🧪 AxPEP Backend 本地測試工具"
echo "============================="

# 檢查容器是否運行
if ! docker compose -f docker/docker-compose.local.yml ps | grep -q "Up"; then
    echo "❌ 容器未運行，請先執行 ./start-local.sh"
    exit 1
fi

echo "🔍 測試服務狀態..."

# 測試PHP-FPM
echo "📱 測試PHP-FPM連接..."
if docker compose -f docker/docker-compose.local.yml exec -T app php -v > /dev/null 2>&1; then
    echo "✅ PHP-FPM 正常運行"
    docker compose -f docker/docker-compose.local.yml exec -T app php -v | head -1
else
    echo "❌ PHP-FPM 無法連接"
fi

# 測試外部數據庫連接
echo "🗄️  測試外部PostgreSQL連接..."
if docker compose -f docker/docker-compose.local.yml exec -T app php artisan migrate:status > /dev/null 2>&1; then
    echo "✅ 外部PostgreSQL (Supabase) 連接正常"
else
    echo "❌ 外部PostgreSQL 連接失敗 - 請檢查.env.local配置"
fi

# 測試Redis連接
echo "⚡ 測試Redis連接..."
if docker compose -f docker/docker-compose.local.yml exec -T redis redis-cli ping > /dev/null 2>&1; then
    echo "✅ Redis 連接正常"
else
    echo "❌ Redis 連接失敗"
fi

# 測試Laravel應用
echo "🌐 測試Laravel應用..."
if docker compose -f docker/docker-compose.local.yml exec -T app php artisan --version > /dev/null 2>&1; then
    echo "✅ Laravel 應用正常"
    docker compose -f docker/docker-compose.local.yml exec -T app php artisan --version
else
    echo "❌ Laravel 應用異常"
fi

# 測試數據庫連接
echo "🔗 測試Laravel數據庫連接..."
if docker compose -f docker/docker-compose.local.yml exec -T app php artisan migrate:status > /dev/null 2>&1; then
    echo "✅ Laravel數據庫連接正常"
else
    echo "❌ Laravel數據庫連接失敗"
fi

# 測試隊列連接
echo "📬 測試隊列連接..."
if docker compose -f docker/docker-compose.local.yml exec -T app php artisan queue:failed > /dev/null 2>&1; then
    echo "✅ 隊列系統正常"
else
    echo "❌ 隊列系統異常"
fi

echo ""
echo "🎯 快速API測試（需要配置Nginx或使用cgi-fcgi）"
echo "==================================================="
echo "如果您想直接測試API，可以："
echo ""
echo "1. 安裝nginx並配置指向localhost:9000"
echo "2. 或者使用以下命令進入容器測試："
echo "   docker compose -f docker/docker-compose.local.yml exec app bash"
echo ""
echo "3. 或者測試特定Laravel功能："
echo "   docker compose -f docker/docker-compose.local.yml exec app php artisan route:list"
echo ""

# 顯示容器狀態
echo "📊 容器狀態："
docker compose -f docker/docker-compose.local.yml ps

echo ""
echo "📝 建議下一步："
echo "  1. 確認外部數據庫連接配置正確"
echo "  2. 檢查日誌是否有錯誤"
echo "  3. 配置本地Nginx或使用API測試工具"
echo ""
