#!/bin/bash
set -e

echo "🚀 AxPEP Backend 本地開發環境啟動"
echo "=================================="

# 檢查Docker是否運行
if ! docker info > /dev/null 2>&1; then
    echo "❌ 錯誤: Docker未運行，請先啟動Docker Desktop"
    exit 1
fi

# 移動到專案根目錄
cd "$(dirname "$0")"

echo "📋 準備環境配置..."

# 檢查並創建本地環境文件
if [ ! -f ".env.local" ]; then
    echo "📄 創建本地環境文件..."
    cp docker/env.local.example .env.local
    echo "✅ 請編輯 .env.local 文件並設置 APP_KEY"
    echo "   您可以運行以下命令生成APP_KEY:"
    echo "   docker run --rm -v \$(pwd):/app -w /app php:8.1-cli php artisan key:generate --env=local"
fi

echo "🛑 停止現有容器..."
docker compose -f docker/docker-compose.local.yml down 2>/dev/null || true

echo "🏗️  建構Docker映像..."
docker compose -f docker/docker-compose.local.yml build

echo "🚀 啟動服務..."
docker compose -f docker/docker-compose.local.yml up -d

echo "⏳ 等待Redis服務就緒..."
sleep 10

echo "🔧 初始化Laravel應用..."

# 生成APP_KEY（如果還沒有）
if ! grep -q "APP_KEY=base64:" .env.local; then
    echo "🔑 生成應用密鑰..."
    docker compose -f docker/docker-compose.local.yml exec -T app php artisan key:generate
fi

# 清除緩存
echo "🧹 清除緩存..."
docker compose -f docker/docker-compose.local.yml exec -T app php artisan config:clear
docker compose -f docker/docker-compose.local.yml exec -T app php artisan cache:clear
docker compose -f docker/docker-compose.local.yml exec -T app php artisan route:clear

# 檢查外部數據庫連接並執行遷移
echo "🗄️  測試外部數據庫連接..."
if docker compose -f docker/docker-compose.local.yml exec -T app php artisan migrate:status >/dev/null 2>&1; then
    echo "✅ 外部數據庫連接成功"
    echo "🔄 執行數據庫遷移..."
    docker compose -f docker/docker-compose.local.yml exec -T app php artisan migrate --force
else
    echo "❌ 無法連接到外部數據庫"
    echo "📝 請確認.env.local中的數據庫配置："
    echo "   DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD"
    echo ""
    echo "⚠️  跳過數據庫遷移，您可以稍後手動執行："
    echo "   docker compose -f docker/docker-compose.local.yml exec app php artisan migrate"
fi

echo ""
echo "🎉 本地開發環境啟動完成！"
echo "=================================="
echo ""
echo "📊 服務狀態:"
docker compose -f docker/docker-compose.local.yml ps
echo ""
echo "🔗 服務端點:"
echo "  🌐 HTTP API:         http://localhost:8000"
echo "  📱 PHP-FPM:          內部容器通信"
echo "  ⚡ Redis:            localhost:6379"
echo "  🗄️  PostgreSQL:       外部Supabase數據庫"
echo ""
echo "🛠️  常用命令:"
echo "  查看應用日誌:    docker compose -f docker/docker-compose.local.yml logs -f app"
echo "  查看隊列日誌:    docker compose -f docker/docker-compose.local.yml logs -f queue-worker"
echo "  進入應用容器:    docker compose -f docker/docker-compose.local.yml exec app bash"
echo "  執行artisan:     docker compose -f docker/docker-compose.local.yml exec app php artisan [command]"
echo "  停止服務:        docker compose -f docker/docker-compose.local.yml down"
echo ""
echo "📝 下一步："
echo "  1. 使用前端應用連接到 http://localhost:8000"
echo "  2. 測試API端點：curl http://localhost:8000/api/v1/axpep/codons/all"
echo "  3. 檢查容器日誌：docker compose -f docker/docker-compose.local.yml logs nginx"
echo ""
