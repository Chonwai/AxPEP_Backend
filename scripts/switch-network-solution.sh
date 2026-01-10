#!/bin/bash

################################################################################
# Docker 網絡方案切換腳本
#
# 用途：快速在三種網絡方案之間切換
# 使用：./scripts/switch-network-solution.sh [方案編號]
################################################################################

set -e

# 顏色定義
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
log_section() { echo -e "\n${CYAN}$1${NC}"; }

################################################################################
# 顯示幫助信息
################################################################################
show_help() {
    cat << EOF
${CYAN}Docker 網絡方案切換工具${NC}

${YELLOW}用法：${NC}
  ./scripts/switch-network-solution.sh [方案編號]

${YELLOW}可用方案：${NC}
  ${GREEN}1${NC} - Host Network Mode（最簡單，快速修復）
       容器使用宿主機網絡，可直接訪問 127.0.0.1

  ${GREEN}2${NC} - Fixed Gateway（過渡方案）
       使用 host.docker.internal 並正確配置

  ${GREEN}3${NC} - Full Containerized（最佳生產方案）
       所有微服務容器化，使用服務發現

  ${GREEN}diagnose${NC} - 運行網絡診斷工具

${YELLOW}示例：${NC}
  ./scripts/switch-network-solution.sh 1
  ./scripts/switch-network-solution.sh diagnose

${YELLOW}注意：${NC}
  - 切換方案前會自動備份當前配置
  - 需要在項目根目錄執行
  - 某些方案需要修改 .env 文件
EOF
}

################################################################################
# 檢查環境
################################################################################
check_environment() {
    if [ ! -f "artisan" ]; then
        log_error "請在 Laravel 項目根目錄執行此腳本！"
        exit 1
    fi

    if [ ! -d "docker" ]; then
        log_error "找不到 docker 目錄！"
        exit 1
    fi
}

################################################################################
# 備份當前配置
################################################################################
backup_config() {
    local BACKUP_DIR="backups/network_switch_$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$BACKUP_DIR"

    if [ -f "docker/docker-compose.yml" ]; then
        cp docker/docker-compose.yml "$BACKUP_DIR/docker-compose.yml"
        log_success "已備份 docker-compose.yml"
    fi

    if [ -f ".env" ]; then
        cp .env "$BACKUP_DIR/.env"
        log_success "已備份 .env"
    fi

    echo "$BACKUP_DIR" > .last_backup
    log_info "備份位置: $BACKUP_DIR"
}

################################################################################
# 方案 1: Host Network Mode
################################################################################
apply_solution_1() {
    log_section "========================================="
    log_section "應用方案 1: Host Network Mode"
    log_section "========================================="

    # 複製配置文件
    if [ -f "docker/docker-compose.host-network.yml" ]; then
        cp docker/docker-compose.host-network.yml docker/docker-compose.yml
        log_success "已應用 Host Network Mode 配置"
    else
        log_error "找不到 docker-compose.host-network.yml"
        exit 1
    fi

    # 提示修改 .env
    log_warning "請確保 .env 文件中的配置如下："
    echo ""
    echo "  AMPEP_MICROSERVICE_BASE_URL=\"http://127.0.0.1:8001\""
    echo "  DEEPAMPEP30_MICROSERVICE_BASE_URL=\"http://127.0.0.1:8002\""
    echo "  BESTOX_MICROSERVICE_BASE_URL=\"http://127.0.0.1:8006\""
    echo "  SSL_BESTOX_MICROSERVICE_BASE_URL=\"http://127.0.0.1:8007\""
    echo "  REDIS_HOST=127.0.0.1"
    echo ""

    read -p "是否自動修改 .env 文件？(y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        sed -i.bak 's|http://host\.docker\.internal:|http://127.0.0.1:|g' .env
        sed -i.bak 's|REDIS_HOST=redis|REDIS_HOST=127.0.0.1|g' .env
        log_success "已自動修改 .env"
    fi

    # 重啟容器
    log_info "重啟容器..."
    docker compose -f docker/docker-compose.yml down
    docker compose -f docker/docker-compose.yml up -d

    log_success "方案 1 應用完成！"
    echo ""
    log_info "優點："
    echo "  ✅ 最簡單，立即生效"
    echo "  ✅ 可直接訪問 127.0.0.1 上的微服務"
    echo ""
    log_warning "缺點："
    echo "  ⚠️  失去網絡隔離"
    echo "  ⚠️  Worker 容器可訪問宿主機所有服務"
}

################################################################################
# 方案 2: Fixed Gateway
################################################################################
apply_solution_2() {
    log_section "========================================="
    log_section "應用方案 2: Fixed Gateway"
    log_section "========================================="

    # 檢查 Docker 版本
    DOCKER_VERSION=$(docker --version | grep -oP '\d+\.\d+' | head -1)
    MAJOR_VERSION=$(echo $DOCKER_VERSION | cut -d. -f1)
    MINOR_VERSION=$(echo $DOCKER_VERSION | cut -d. -f2)

    if [ "$MAJOR_VERSION" -lt 20 ] || ([ "$MAJOR_VERSION" -eq 20 ] && [ "$MINOR_VERSION" -lt 10 ]); then
        log_warning "Docker 版本 < 20.10，host-gateway 可能不工作"
        log_info "當前版本: $DOCKER_VERSION"
        read -p "是否繼續？(y/n) " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi

    # 複製配置文件
    if [ -f "docker/docker-compose.fixed-gateway.yml" ]; then
        cp docker/docker-compose.fixed-gateway.yml docker/docker-compose.yml
        log_success "已應用 Fixed Gateway 配置"
    else
        log_error "找不到 docker-compose.fixed-gateway.yml"
        exit 1
    fi

    # 提示修改 .env
    log_warning "請確保 .env 文件中的配置如下："
    echo ""
    echo "  AMPEP_MICROSERVICE_BASE_URL=\"http://host.docker.internal:8001\""
    echo "  DEEPAMPEP30_MICROSERVICE_BASE_URL=\"http://host.docker.internal:8002\""
    echo "  BESTOX_MICROSERVICE_BASE_URL=\"http://host.docker.internal:8006\""
    echo "  SSL_BESTOX_MICROSERVICE_BASE_URL=\"http://host.docker.internal:8007\""
    echo "  REDIS_HOST=redis"
    echo ""

    read -p "是否自動修改 .env 文件？(y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        sed -i.bak 's|http://127\.0\.0\.1:|http://host.docker.internal:|g' .env
        sed -i.bak 's|REDIS_HOST=127\.0\.0\.1|REDIS_HOST=redis|g' .env
        log_success "已自動修改 .env"
    fi

    # 重啟容器
    log_info "重啟容器..."
    docker compose -f docker/docker-compose.yml down
    docker compose -f docker/docker-compose.yml up -d

    # 等待啟動
    sleep 5

    # 測試連接
    log_info "測試 host.docker.internal 解析..."
    if docker exec axpep-worker cat /etc/hosts | grep -q "host.docker.internal"; then
        log_success "host.docker.internal 已正確配置"
        docker exec axpep-worker cat /etc/hosts | grep "host.docker.internal"
    else
        log_error "host.docker.internal 配置失敗！"
    fi

    log_info "測試微服務連接..."
    if docker exec axpep-worker curl -s -f --max-time 3 "http://host.docker.internal:8001/health" > /dev/null 2>&1; then
        log_success "可以連接到 AmPEP 微服務"
    else
        log_error "無法連接到 AmPEP 微服務"
        log_warning "可能需要："
        echo "  1. 確保微服務監聽 0.0.0.0 而非 127.0.0.1"
        echo "  2. 檢查防火牆設置"
        echo "  3. 手動指定 Gateway IP（參考文檔）"
    fi

    log_success "方案 2 應用完成！"
}

################################################################################
# 方案 3: Full Containerized
################################################################################
apply_solution_3() {
    log_section "========================================="
    log_section "應用方案 3: Full Containerized"
    log_section "========================================="

    log_warning "此方案需要為每個微服務創建 Dockerfile"
    log_info "請確保以下目錄存在並包含 Dockerfile："
    echo ""
    echo "  ../../AmPEP/Dockerfile"
    echo "  ../../DeepAmPEP30/Dockerfile"
    echo "  ../../BESTox/Dockerfile"
    echo "  ../../SSL-GCN/Dockerfile"
    echo "  ../../AMP_Regression/Dockerfile"
    echo ""

    read -p "是否已準備好所有 Dockerfile？(y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_info "請先為微服務創建 Dockerfile，參考文檔中的模板"
        exit 0
    fi

    # 複製配置文件
    if [ -f "docker/docker-compose.full-containerized.yml" ]; then
        cp docker/docker-compose.full-containerized.yml docker/docker-compose.yml
        log_success "已應用 Full Containerized 配置"
    else
        log_error "找不到 docker-compose.full-containerized.yml"
        exit 1
    fi

    # 提示修改 .env
    log_warning "請確保 .env 文件中使用服務名稱："
    echo ""
    echo "  AMPEP_MICROSERVICE_BASE_URL=\"http://ampep-service:8001\""
    echo "  DEEPAMPEP30_MICROSERVICE_BASE_URL=\"http://deepampep30-service:8002\""
    echo "  BESTOX_MICROSERVICE_BASE_URL=\"http://bestox-service:8006\""
    echo "  SSL_BESTOX_MICROSERVICE_BASE_URL=\"http://ssl-gcn-service:8007\""
    echo "  REDIS_HOST=redis"
    echo ""

    read -p "是否自動修改 .env 文件？(y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        sed -i.bak 's|http://[^:]*:8001|http://ampep-service:8001|g' .env
        sed -i.bak 's|http://[^:]*:8002|http://deepampep30-service:8002|g' .env
        sed -i.bak 's|http://[^:]*:8006|http://bestox-service:8006|g' .env
        sed -i.bak 's|http://[^:]*:8007|http://ssl-gcn-service:8007|g' .env
        sed -i.bak 's|http://[^:]*:8889|http://amp-regression-service:8889|g' .env
        log_success "已自動修改 .env"
    fi

    # 構建並啟動
    log_info "構建所有服務鏡像（這可能需要一些時間）..."
    docker compose -f docker/docker-compose.yml build

    log_info "啟動所有服務..."
    docker compose -f docker/docker-compose.yml up -d

    log_success "方案 3 應用完成！"
}

################################################################################
# 運行診斷
################################################################################
run_diagnosis() {
    log_section "========================================="
    log_section "運行網絡診斷"
    log_section "========================================="

    if [ -f "scripts/diagnose-docker-network.sh" ]; then
        bash scripts/diagnose-docker-network.sh
    else
        log_error "找不到診斷腳本！"
        exit 1
    fi
}

################################################################################
# 主程序
################################################################################
main() {
    check_environment

    # 如果沒有參數，顯示幫助
    if [ $# -eq 0 ]; then
        show_help
        exit 0
    fi

    local SOLUTION=$1

    case $SOLUTION in
        1)
            backup_config
            apply_solution_1
            ;;
        2)
            backup_config
            apply_solution_2
            ;;
        3)
            backup_config
            apply_solution_3
            ;;
        diagnose)
            run_diagnosis
            ;;
        help|--help|-h)
            show_help
            ;;
        *)
            log_error "無效的方案編號: $SOLUTION"
            show_help
            exit 1
            ;;
    esac

    # 顯示下一步
    echo ""
    log_section "========================================="
    log_section "下一步操作"
    log_section "========================================="
    echo ""
    echo "1. 查看容器狀態："
    echo "   docker compose -f docker/docker-compose.yml ps"
    echo ""
    echo "2. 查看日誌："
    echo "   docker logs -f axpep-worker"
    echo ""
    echo "3. 提交測試任務並驗證"
    echo ""
    echo "4. 如果需要恢復，備份位置："
    if [ -f ".last_backup" ]; then
        cat .last_backup
    fi
    echo ""
}

main "$@"
