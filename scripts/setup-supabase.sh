#!/bin/bash
set -e

echo "🔧 Supabase PostgreSQL 配置工具"
echo "================================"

# 檢查.env.local是否存在
if [ ! -f ".env.local" ]; then
    echo "📄 創建環境配置文件..."
    cp docker/env.local.example .env.local
fi

# 提示用戶輸入Supabase密碼
echo ""
echo "📝 請提供您的Supabase數據庫密碼"
echo "   （可在Supabase Dashboard > Settings > Database找到）"
echo ""
read -s -p "🔑 輸入Supabase密碼: " supabase_password
echo ""

if [ -z "$supabase_password" ]; then
    echo "❌ 密碼不能為空"
    exit 1
fi

# 更新.env.local文件中的密碼
echo "📝 更新環境配置..."
sed -i.bak "s/your_supabase_password/$supabase_password/g" .env.local
rm .env.local.bak 2>/dev/null || true

echo "✅ Supabase配置完成"
echo ""
echo "📋 當前配置："
echo "   數據庫: PostgreSQL (Supabase)"
echo "   連接方式: Transaction Pooler"
echo "   主機: aws-0-ap-northeast-1.pooler.supabase.com"
echo "   端口: 6543"
echo ""
echo "🚀 現在可以運行以下命令啟動環境："
echo "   ./start-local.sh"
echo ""

# 可選：立即啟動
read -p "🤔 是否立即重新啟動Docker環境? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "🔄 重新啟動Docker環境..."
    ./reset-local.sh
    ./start-local.sh
fi
