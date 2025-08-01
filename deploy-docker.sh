#!/bin/bash
set -e

echo "AxPEP Backend Docker 部署腳本"
echo "================================"

# 檢查Docker是否運行
if ! docker info > /dev/null 2>&1; then
    echo "錯誤: Docker未運行，請先啟動Docker"
    exit 1
fi

# 移動到專案根目錄
cd "$(dirname "$0")"

echo "1. 停止現有容器..."
docker compose -f docker/docker-compose.yml down 2>/dev/null || true

echo "2. 更新代碼..."
git pull

echo "3. 建構Docker映像..."
docker compose -f docker/docker-compose.yml build --no-cache

echo "4. 啟動容器..."
docker compose -f docker/docker-compose.yml up -d

# 等待容器啟動
echo "5. 等待容器就緒..."
sleep 10

echo "6. 執行Laravel命令..."
docker compose -f docker/docker-compose.yml exec -T app php artisan config:clear
docker compose -f docker/docker-compose.yml exec -T app php artisan cache:clear
docker compose -f docker/docker-compose.yml exec -T app php artisan route:clear

# 可選：執行數據庫遷移
read -p "是否要執行數據庫遷移? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "執行數據庫遷移..."
    docker compose -f docker/docker-compose.yml exec -T app php artisan migrate --force
fi

echo ""
echo "🎉 生產環境部署完成！"
echo "================================"
echo "📊 容器狀態:"
docker compose -f docker/docker-compose.yml ps

echo ""
echo "🔗 服務端點:"
echo "  🌐 HTTP API:         http://localhost (端口80)"
echo "  📱 PHP-FPM:          內部容器通信"
echo "  ⚡ Redis:            內部容器通信"
echo "  🗄️  PostgreSQL:       外部數據庫"

echo ""
echo "🛠️  監控命令:"
echo "  應用日誌:    docker compose -f docker/docker-compose.yml logs -f app"
echo "  Nginx日誌:   docker compose -f docker/docker-compose.yml logs -f nginx"
echo "  隊列日誌:    docker compose -f docker/docker-compose.yml logs -f queue-worker"
echo "  Redis日誌:   docker compose -f docker/docker-compose.yml logs -f redis"

echo ""
echo "🔍 健康檢查:"
echo "  應用健康:    curl http://localhost/health"
echo "  Nginx狀態:   curl http://localhost/nginx_status"

echo ""
echo "⚠️  注意事項:"
echo "  - 請確保防火牆允許80端口"
echo "  - 檢查外部數據庫連接"
echo "  - 監控資源使用情況"
echo ""
