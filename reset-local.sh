#!/bin/bash
set -e

echo "🔄 重置AxPEP Backend本地開發環境"
echo "================================="

# 停止並移除所有容器和卷
echo "🛑 停止並清理現有環境..."
docker compose -f docker/docker-compose.local.yml down -v 2>/dev/null || true

# 清理映像
echo "🗑️  清理Docker映像..."
docker rmi axpep-backend:local axpep-backend-worker:local 2>/dev/null || true

# 清理網絡
echo "🌐 清理Docker網絡..."
docker network rm docker_axpep-network 2>/dev/null || true

# 重新創建環境配置
echo "📄 重新創建環境配置..."
cp docker/env.local.example .env.local

echo ""
echo "✅ 環境已重置"
echo ""
echo "📝 下一步："
echo "  1. 執行 ./start-local.sh 重新啟動環境"
echo "  2. 或手動編輯 .env.local 後再啟動"
echo ""
