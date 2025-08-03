#!/bin/bash
set -e

echo "🚀 AxPEP Backend 生產環境啟動"
echo "================================"

# 檢查必要檔案
echo "📋 檢查環境配置..."
if [ ! -f ".env" ]; then
    echo "❌ 找不到 .env 檔案"
    echo "📄 請先創建 .env 檔案："
    echo "  cp docker/env.prod.example .env"
    echo "  然後編輯 .env 設置生產環境配置"
    exit 1
fi

# 檢查APP_KEY
if ! grep -q "APP_KEY=base64:" .env; then
    echo "⚠️  APP_KEY未設置，正在生成..."
    echo "APP_KEY=" >> .env
fi

echo "🏗️  建構Production映像..."
docker compose -f docker/docker-compose.yml build --no-cache

echo "🚀 啟動生產環境服務..."
docker compose -f docker/docker-compose.yml up -d

echo "⏳ 等待服務就緒..."
sleep 30

echo "🔧 初始化Laravel應用..."
# 生成APP_KEY（如果需要）
if ! grep -q "APP_KEY=base64:" .env; then
    echo "🔑 生成應用密鑰..."
    docker compose -f docker/docker-compose.yml exec -T app php artisan key:generate --force
fi

echo "🧹 清除緩存..."
docker compose -f docker/docker-compose.yml exec -T app php artisan config:cache
docker compose -f docker/docker-compose.yml exec -T app php artisan route:cache
docker compose -f docker/docker-compose.yml exec -T app php artisan view:cache

echo "🗄️  檢查數據庫連接..."
if docker compose -f docker/docker-compose.yml exec -T app php artisan migrate:status >/dev/null 2>&1; then
    echo "✅ 數據庫連接成功"

    # 詢問是否執行遷移
    read -p "是否執行數據庫遷移? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo "🔄 執行數據庫遷移..."
        docker compose -f docker/docker-compose.yml exec -T app php artisan migrate --force
    fi
else
    echo "❌ 數據庫連接失敗 - 請檢查.env配置"
    echo "   - DB_HOST, DB_PORT, DB_DATABASE"
    echo "   - DB_USERNAME, DB_PASSWORD"
fi

echo ""
echo "🎉 生產環境啟動完成！"
echo "================================"

echo ""
echo "📊 服務狀態:"
docker compose -f docker/docker-compose.yml ps

echo ""
echo "🔗 服務端點:"
echo "  🌐 HTTP API:         http://localhost"
echo "  📱 PHP-FPM:          內部容器通信"
echo "  ⚡ Redis:            內部容器通信"
echo "  🗄️  PostgreSQL:       外部數據庫"

echo ""
echo "🛠️  管理命令:"
echo "  查看應用日誌:    docker compose -f docker/docker-compose.yml logs -f app"
echo "  查看Nginx日誌:   docker compose -f docker/docker-compose.yml logs -f nginx"
echo "  查看隊列日誌:    docker compose -f docker/docker-compose.yml logs -f queue-worker"
echo "  進入應用容器:    docker compose -f docker/docker-compose.yml exec app bash"
echo "  執行artisan:     docker compose -f docker/docker-compose.yml exec app php artisan [command]"
echo "  停止服務:        docker compose -f docker/docker-compose.yml down"

echo ""
echo "🔍 健康檢查:"
echo "  應用健康:        curl http://localhost/health"
echo "  API測試:         curl http://localhost/api/v1/axpep/codons/all"

echo ""
echo "📝 下一步："
echo "  1. 配置域名和SSL證書"
echo "  2. 設置監控和日誌收集"
echo "  3. 備份策略設置"
echo "  4. 效能優化"
echo ""
