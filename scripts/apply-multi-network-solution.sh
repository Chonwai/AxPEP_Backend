#!/bin/bash

################################################################################
# 多網絡連接方案部署腳本
#
# 用途：將 AxPEP Backend 容器加入所有微服務網絡
# 優點：容器間直接通信，無需 host.docker.internal
################################################################################

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }
log_section() { echo -e "\n${CYAN}========================================${NC}"; echo -e "${CYAN}$1${NC}"; echo -e "${CYAN}========================================${NC}"; }

################################################################################
# 檢查環境
################################################################################
check_environment() {
    if [ ! -f "artisan" ]; then
        log_error "請在 Laravel 項目根目錄執行此腳本！"
        exit 1
    fi
}

################################################################################
# 備份當前配置
################################################################################
backup_config() {
    local BACKUP_DIR="backups/multi_network_$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$BACKUP_DIR"

    if [ -f "docker/docker-compose.yml" ]; then
        cp docker/docker-compose.yml "$BACKUP_DIR/docker-compose.yml"
        log_success "已備份 docker-compose.yml → $BACKUP_DIR"
    fi

    if [ -f ".env" ]; then
        cp .env "$BACKUP_DIR/.env"
        log_success "已備份 .env → $BACKUP_DIR"
    fi

    echo "$BACKUP_DIR" > .last_backup
}

################################################################################
# 驗證外部網絡
################################################################################
verify_networks() {
    log_section "1. 驗證外部網絡"

    local networks=(
        "docker_ampep-network"
        "bestox-network"
        "docker_ssl-gcn-network"
        "amp_regression_ec_sa_fastapi_default"
        "docker_default"
    )

    local missing_networks=()

    for network in "${networks[@]}"; do
        if docker network inspect "$network" >/dev/null 2>&1; then
            log_success "✓ 網絡存在: $network"
        else
            log_warning "✗ 網絡不存在: $network"
            missing_networks+=("$network")
        fi
    done

    if [ ${#missing_networks[@]} -gt 0 ]; then
        log_warning "以下網絡不存在，將從配置中移除："
        printf '  - %s\n' "${missing_networks[@]}"
        read -p "是否繼續？(y/n) " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
}

################################################################################
# 應用多網絡配置
################################################################################
apply_config() {
    log_section "2. 應用多網絡配置"

    if [ ! -f "docker/docker-compose.multi-network.yml" ]; then
        log_error "找不到 docker-compose.multi-network.yml"
        exit 1
    fi

    # 複製配置
    cp docker/docker-compose.multi-network.yml docker/docker-compose.yml
    log_success "已應用多網絡配置"
}

################################################################################
# 更新 .env 文件
################################################################################
update_env() {
    log_section "3. 更新 .env 配置"

    log_info "將微服務 URL 更新為容器名稱..."

    # 創建臨時文件
    cp .env .env.tmp

    # 更新微服務 URL（使用容器名）
    sed -i.bak 's|AMPEP_MICROSERVICE_BASE_URL=.*|AMPEP_MICROSERVICE_BASE_URL="http://docker-ampep-microservice-1:8001"|' .env.tmp
    sed -i.bak 's|DEEPAMPEP30_MICROSERVICE_BASE_URL=.*|DEEPAMPEP30_MICROSERVICE_BASE_URL="http://deep-ampep30:8002"|' .env.tmp
    sed -i.bak 's|BESTOX_MICROSERVICE_BASE_URL=.*|BESTOX_MICROSERVICE_BASE_URL="http://bestox-api-service:8006"|' .env.tmp
    sed -i.bak 's|SSL_BESTOX_MICROSERVICE_BASE_URL=.*|SSL_BESTOX_MICROSERVICE_BASE_URL="http://ssl-gcn-toxicity-prediction:8007"|' .env.tmp
    sed -i.bak 's|AMP_REGRESSION_MICROSERVICE_BASE_URL=.*|AMP_REGRESSION_MICROSERVICE_BASE_URL="http://amp_regression_ec_sa_fastapi-amp-regression-predict-flask-1:8888"|' .env.tmp

    # 確保 Redis 使用容器名
    sed -i.bak 's|REDIS_HOST=.*|REDIS_HOST=redis|' .env.tmp

    # 顯示變更
    log_info "配置變更："
    echo ""
    grep "MICROSERVICE_BASE_URL" .env.tmp | sed 's/^/  /'
    echo ""

    read -p "確認應用這些變更？(y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        mv .env.tmp .env
        rm -f .env.tmp.bak
        log_success "已更新 .env"
    else
        rm -f .env.tmp .env.tmp.bak
        log_warning "取消更新 .env"
    fi
}

################################################################################
# 重啟容器
################################################################################
restart_containers() {
    log_section "4. 重啟容器"

    log_info "停止現有容器..."
    docker compose -f docker/docker-compose.yml down

    log_info "啟動新配置..."
    docker compose -f docker/docker-compose.yml up -d

    log_success "容器已重啟"
}

################################################################################
# 驗證網絡連接
################################################################################
verify_connectivity() {
    log_section "5. 驗證網絡連接"

    # 等待容器啟動
    log_info "等待容器啟動（10秒）..."
    sleep 10

    # 檢查容器狀態
    log_info "檢查容器狀態..."
    docker compose -f docker/docker-compose.yml ps
    echo ""

    # 測試網絡連接
    log_info "測試微服務連接..."

    local services=(
        "docker-ampep-microservice-1:8001"
        "deep-ampep30:8002"
        "bestox-api-service:8006"
        "ssl-gcn-toxicity-prediction:8007"
    )

    for service in "${services[@]}"; do
        if docker exec axpep-worker curl -s -f --max-time 5 "http://$service/health" >/dev/null 2>&1; then
            log_success "✓ 可以連接: $service"
        else
            log_warning "✗ 無法連接: $service"
        fi
    done
}

################################################################################
# 顯示後續步驟
################################################################################
show_next_steps() {
    log_section "部署完成"

    echo ""
    log_success "多網絡方案已成功部署！"
    echo ""
    log_info "下一步操作："
    echo ""
    echo "1. 查看容器日誌："
    echo "   docker logs -f axpep-worker"
    echo ""
    echo "2. 提交測試任務驗證功能"
    echo ""
    echo "3. 如果遇到問題，查看備份位置："
    if [ -f ".last_backup" ]; then
        echo "   $(cat .last_backup)"
    fi
    echo ""
    echo "4. 如果需要回退，使用備份的配置："
    echo "   cp $(cat .last_backup)/docker-compose.yml docker/docker-compose.yml"
    echo "   cp $(cat .last_backup)/.env .env"
    echo "   docker compose down && docker compose up -d"
    echo ""
}

################################################################################
# 主程序
################################################################################
main() {
    log_section "多網絡連接方案部署"

    check_environment
    backup_config
    verify_networks
    apply_config
    update_env
    restart_containers
    verify_connectivity
    show_next_steps
}

main "$@"
