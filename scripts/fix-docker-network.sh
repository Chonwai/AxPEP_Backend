#!/bin/bash

################################################################################
# Docker 網絡配置修復 - 一鍵部署腳本
#
# 用途：修復 Docker 容器無法訪問宿主機微服務的問題
# 日期：2026-01-07
# 作者：Technical Team
################################################################################

set -e  # 遇到錯誤立即退出

# 顏色定義
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 日誌函數
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# 檢查是否在正確的目錄
if [ ! -f "artisan" ]; then
    log_error "請在 Laravel 項目根目錄執行此腳本！"
    exit 1
fi

log_info "開始 Docker 網絡配置修復流程..."
echo ""

################################################################################
# 步驟 1: 備份現有配置
################################################################################
log_info "步驟 1/6: 備份現有配置文件..."

BACKUP_DIR="backups/network_fix_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

if [ -f ".env" ]; then
    cp .env "$BACKUP_DIR/.env"
    log_success "已備份 .env 到 $BACKUP_DIR/"
fi

if [ -f "config/services.php" ]; then
    cp config/services.php "$BACKUP_DIR/services.php"
    log_success "已備份 config/services.php 到 $BACKUP_DIR/"
fi

echo ""

################################################################################
# 步驟 2: 檢查 docker-compose.yml 配置
################################################################################
log_info "步驟 2/6: 檢查 docker-compose.yml 網絡配置..."

COMPOSE_FILE="docker/docker-compose.yml"
if ! grep -q "host.docker.internal:host-gateway" "$COMPOSE_FILE"; then
    log_warning "docker-compose.yml 缺少 extra_hosts 配置！"
    log_info "建議在 app 和 queue-worker 服務中添加："
    echo ""
    echo "    extra_hosts:"
    echo "      - \"host.docker.internal:host-gateway\""
    echo ""
    read -p "是否繼續？(y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_error "已取消操作"
        exit 1
    fi
else
    log_success "docker-compose.yml 配置正確"
fi

echo ""

################################################################################
# 步驟 3: 檢查並更新 .env 配置
################################################################################
log_info "步驟 3/6: 檢查 .env 微服務 URL 配置..."

ENV_FILE=".env"
NEEDS_UPDATE=false

# 檢查需要修復的 URL
if grep -q "127\.0\.0\.1\|172\.17\.0\.1" "$ENV_FILE" 2>/dev/null; then
    log_warning "發現使用 127.0.0.1 或 172.17.0.1 的配置"
    NEEDS_UPDATE=true

    # 顯示需要修改的行
    log_info "以下配置需要更新："
    grep -n "127\.0\.0\.1\|172\.17\.0\.1" "$ENV_FILE" | while read line; do
        echo "  $line"
    done
    echo ""

    log_warning "建議手動編輯 .env 文件，將以下 URL 改為 host.docker.internal："
    echo "  - AMPEP_MICROSERVICE_BASE_URL"
    echo "  - DEEPAMPEP30_MICROSERVICE_BASE_URL"
    echo "  - BESTOX_MICROSERVICE_BASE_URL"
    echo "  - SSL_BESTOX_MICROSERVICE_BASE_URL"
    echo "  - AMP_REGRESSION_EC_SA_PREDICT_BASE_URL"
    echo ""

    read -p "已完成手動編輯？(y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_info "可以稍後執行 'nano .env' 手動編輯"
    fi
else
    log_success ".env 配置已使用 host.docker.internal"
fi

echo ""

################################################################################
# 步驟 4: 清除 Laravel 配置緩存
################################################################################
log_info "步驟 4/6: 清除 Laravel 配置緩存..."

# 檢查容器是否運行
if docker ps | grep -q "axpep-app"; then
    log_info "清除應用緩存..."
    docker exec axpep-app php artisan config:clear || log_warning "config:clear 失敗"
    docker exec axpep-app php artisan cache:clear || log_warning "cache:clear 失敗"
    docker exec axpep-app php artisan route:clear || log_warning "route:clear 失敗"

    log_info "重新生成配置緩存..."
    docker exec axpep-app php artisan config:cache || log_warning "config:cache 失敗"

    log_success "緩存清理完成"
else
    log_warning "axpep-app 容器未運行，跳過緩存清理"
fi

echo ""

################################################################################
# 步驟 5: 重啟 Docker 容器
################################################################################
log_info "步驟 5/6: 重啟 Docker 容器..."

if [ -f "$COMPOSE_FILE" ]; then
    log_info "重啟容器中..."
    docker compose -f "$COMPOSE_FILE" restart

    log_info "等待容器啟動（10秒）..."
    sleep 10

    log_success "容器重啟完成"
else
    log_warning "找不到 $COMPOSE_FILE，請手動重啟容器"
fi

echo ""

################################################################################
# 步驟 6: 驗證修復結果
################################################################################
log_info "步驟 6/6: 驗證微服務連接..."

# 測試函數
test_microservice() {
    local name=$1
    local port=$2
    local url="http://host.docker.internal:$port/health"

    log_info "測試 $name ($port)..."

    if docker exec axpep-app curl -s -f --max-time 5 "$url" > /dev/null 2>&1; then
        log_success "$name 連接成功 ✅"
        return 0
    else
        log_error "$name 連接失敗 ❌"
        return 1
    fi
}

# 測試各個微服務
SUCCESS_COUNT=0
TOTAL_COUNT=0

for service in "AmPEP:8001" "DeepAmPEP30:8002" "BESTox:8006" "SSL-GCN:8007"; do
    TOTAL_COUNT=$((TOTAL_COUNT + 1))
    IFS=':' read -r name port <<< "$service"
    if test_microservice "$name" "$port"; then
        SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
    fi
done

echo ""

################################################################################
# 總結報告
################################################################################
log_info "============================================"
log_info "修復流程完成！"
log_info "============================================"
echo ""

log_info "微服務連接測試結果: $SUCCESS_COUNT/$TOTAL_COUNT 成功"

if [ $SUCCESS_COUNT -eq $TOTAL_COUNT ]; then
    log_success "所有微服務連接正常！🎉"
    echo ""
    log_info "建議操作："
    echo "  1. 提交一個測試任務驗證完整流程"
    echo "  2. 監控日誌：docker logs -f axpep-worker"
    echo "  3. 檢查是否還有錯誤：docker logs axpep-worker | grep ERROR"
elif [ $SUCCESS_COUNT -gt 0 ]; then
    log_warning "部分微服務連接失敗，請檢查："
    echo "  1. 確認微服務是否在宿主機上運行"
    echo "  2. 檢查防火牆設置"
    echo "  3. 確認微服務監聽在 0.0.0.0 而非 127.0.0.1"
else
    log_error "所有微服務連接失敗！"
    echo ""
    log_info "故障排查步驟："
    echo "  1. 測試 DNS 解析："
    echo "     docker exec axpep-app ping -c 2 host.docker.internal"
    echo ""
    echo "  2. 檢查 /etc/hosts："
    echo "     docker exec axpep-app cat /etc/hosts | grep host.docker.internal"
    echo ""
    echo "  3. 從宿主機測試微服務："
    echo "     curl http://127.0.0.1:8001/health"
    echo ""
    echo "  4. 查看詳細文檔："
    echo "     cat docs/DOCKER_NETWORK_FIX_GUIDE.md"
fi

echo ""
log_info "備份位置: $BACKUP_DIR"
log_info "詳細文檔: docs/DOCKER_NETWORK_FIX_GUIDE.md"
echo ""

################################################################################
# 清理與建議
################################################################################
log_info "============================================"
log_info "後續建議"
log_info "============================================"
echo ""
echo "1. 監控應用日誌："
echo "   docker logs -f --tail 100 axpep-worker"
echo ""
echo "2. 提交測試任務並檢查是否成功"
echo ""
echo "3. 如果仍有問題，請檢查："
echo "   - Python 微服務是否監聽 0.0.0.0（而非 127.0.0.1）"
echo "   - 防火牆是否阻擋了端口"
echo "   - docker-compose.yml 是否有 extra_hosts 配置"
echo ""
echo "4. 恢復備份（如果需要）："
echo "   cp $BACKUP_DIR/.env .env"
echo "   docker compose -f docker/docker-compose.yml restart"
echo ""

log_success "腳本執行完畢！"
