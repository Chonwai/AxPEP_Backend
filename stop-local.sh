#!/bin/bash
set -e

echo "🛑 停止AxPEP Backend本地開發環境"
echo "================================="

# 停止並移除容器
echo "📦 停止Docker容器..."
docker compose -f docker/docker-compose.local.yml down

# 可選：移除卷（資料會被刪除）
read -p "🗑️  是否要刪除數據卷（包括數據庫資料）? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "🗄️  刪除數據卷..."
    docker compose -f docker/docker-compose.local.yml down -v
fi

# 可選：清理映像
read -p "🧹 是否要清理Docker映像? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "🖼️  清理Docker映像..."
    docker rmi axpep-backend:local axpep-backend-worker:local 2>/dev/null || true
fi

echo ""
echo "✅ 本地開發環境已停止"
echo ""
echo "📝 重新啟動請執行: ./start-local.sh"
echo ""
