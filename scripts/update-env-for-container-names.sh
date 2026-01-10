#!/bin/bash

################################################################################
# .env 配置更新腳本
#
# 目的：更新 .env 文件使用容器名稱訪問微服務
# 原理：將 host.docker.internal 或 IP 地址替換為容器名
################################################################################

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[✓]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[⚠]${NC} $1"; }
log_error() { echo -e "${RED}[✗]${NC} $1"; }
log_section() {
    echo ""
    echo -e "${CYAN}========================================${NC}"
    echo -e "${CYAN}$1${NC}"
    echo -e "${CYAN}========================================${NC}"
}

ENV_FILE=".env"
BACKUP_DIR="backups/env_update_$(date +%Y%m%d_%H%M%S)"

################################################################################
# 備份 .env 文件
################################################################################
backup_env() {
    log_section "1. 備份當前配置"

    if [ ! -f "$ENV_FILE" ]; then
        log_error ".env 文件不存在！"
        exit 1
    fi

    mkdir -p "$BACKUP_DIR"
    cp "$ENV_FILE" "$BACKUP_DIR/.env"
    log_success "已備份到: $BACKUP_DIR/.env"
}

################################################################################
# 顯示當前配置
################################################################################
show_current_config() {
    log_section "2. 當前微服務配置"

    echo ""
    log_info "當前微服務 URL："
    grep "MICROSERVICE_BASE_URL" "$ENV_FILE" | sed 's/^/  /'
    echo ""
}

################################################################################
# 更新配置
################################################################################
update_config() {
    log_section "3. 更新配置"

    log_info "將更新為以下配置："
    echo ""
    echo '  AMPEP_MICROSERVICE_BASE_URL="http://docker-ampep-microservice-1:8001"'
    echo '  DEEPAMPEP30_MICROSERVICE_BASE_URL="http://deep-ampep30:8002"'
    echo '  XDEEP_ACPEP_MICROSERVICE_BASE_URL="http://docker-xdeep-acpep-api-1:8004"'
    echo '  XDEEP_ACPEP_CLASSIFICATION_MICROSERVICE_BASE_URL="http://docker-xdeep-acpep-classification-api-1:8003"'
    echo '  CODON_MICROSERVICE_BASE_URL="http://docker-api-1:8005"'
    echo '  BESTOX_MICROSERVICE_BASE_URL="http://bestox-api-service:8006"'
    echo '  SSL_BESTOX_MICROSERVICE_BASE_URL="http://ssl-gcn-toxicity-prediction:8007"'
    echo '  AMP_REGRESSION_MICROSERVICE_BASE_URL="http://amp_regression_ec_sa_fastapi-amp-regression-predict-flask-1:8888"'
    echo '  ECOTOXICOLOGY_MICROSERVICE_BASE_URL="http://ecotoxicology_fastapi-ecotoxicology-predict-fastapi-1:8888"'
    echo ""

    read -p "確認更新？(y/n) " -n 1 -r
    echo

    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_warning "操作已取消"
        exit 0
    fi

    # 創建臨時文件
    cp "$ENV_FILE" "${ENV_FILE}.tmp"

    # 更新各個微服務 URL（使用更精確的正則表達式）
    sed -i.bak 's|^AMPEP_MICROSERVICE_BASE_URL=.*|AMPEP_MICROSERVICE_BASE_URL="http://docker-ampep-microservice-1:8001"|' "${ENV_FILE}.tmp"
    sed -i.bak 's|^DEEPAMPEP30_MICROSERVICE_BASE_URL=.*|DEEPAMPEP30_MICROSERVICE_BASE_URL="http://deep-ampep30:8002"|' "${ENV_FILE}.tmp"
    sed -i.bak 's|^XDEEP_ACPEP_MICROSERVICE_BASE_URL=.*|XDEEP_ACPEP_MICROSERVICE_BASE_URL="http://docker-xdeep-acpep-api-1:8004"|' "${ENV_FILE}.tmp"
    sed -i.bak 's|^XDEEP_ACPEP_CLASSIFICATION_MICROSERVICE_BASE_URL=.*|XDEEP_ACPEP_CLASSIFICATION_MICROSERVICE_BASE_URL="http://docker-xdeep-acpep-classification-api-1:8003"|' "${ENV_FILE}.tmp"
    sed -i.bak 's|^CODON_MICROSERVICE_BASE_URL=.*|CODON_MICROSERVICE_BASE_URL="http://docker-api-1:8005"|' "${ENV_FILE}.tmp"
    sed -i.bak 's|^BESTOX_MICROSERVICE_BASE_URL=.*|BESTOX_MICROSERVICE_BASE_URL="http://bestox-api-service:8006"|' "${ENV_FILE}.tmp"
    sed -i.bak 's|^SSL_BESTOX_MICROSERVICE_BASE_URL=.*|SSL_BESTOX_MICROSERVICE_BASE_URL="http://ssl-gcn-toxicity-prediction:8007"|' "${ENV_FILE}.tmp"
    sed -i.bak 's|^AMP_REGRESSION_MICROSERVICE_BASE_URL=.*|AMP_REGRESSION_MICROSERVICE_BASE_URL="http://amp_regression_ec_sa_fastapi-amp-regression-predict-flask-1:8888"|' "${ENV_FILE}.tmp"
    sed -i.bak 's|^ECOTOXICOLOGY_MICROSERVICE_BASE_URL=.*|ECOTOXICOLOGY_MICROSERVICE_BASE_URL="http://ecotoxicology_fastapi-ecotoxicology-predict-fastapi-1:8888"|' "${ENV_FILE}.tmp"

    # 確保 Redis 使用容器名
    sed -i.bak 's|^REDIS_HOST=.*|REDIS_HOST=redis|' "${ENV_FILE}.tmp"

    # 應用更改
    mv "${ENV_FILE}.tmp" "$ENV_FILE"
    rm -f "${ENV_FILE}.tmp.bak"

    log_success ".env 已更新"
}

################################################################################
# 顯示更新後的配置
################################################################################
show_updated_config() {
    log_section "4. 更新後配置"

    echo ""
    log_info "新的微服務 URL："
    grep "MICROSERVICE_BASE_URL" "$ENV_FILE" | sed 's/^/  /'
    echo ""
}

################################################################################
# 驗證配置
################################################################################
verify_config() {
    log_section "5. 驗證配置"

    log_info "檢查必要的環境變量..."

    local required_vars=(
        "AMPEP_MICROSERVICE_BASE_URL"
        "DEEPAMPEP30_MICROSERVICE_BASE_URL"
        "BESTOX_MICROSERVICE_BASE_URL"
        "SSL_BESTOX_MICROSERVICE_BASE_URL"
    )

    local missing=0
    for var in "${required_vars[@]}"; do
        if grep -q "^${var}=" "$ENV_FILE"; then
            log_success "  $var 已設置"
        else
            log_error "  $var 缺失"
            ((missing++))
        fi
    done

    if [ $missing -eq 0 ]; then
        log_success "所有必要配置已設置"
    else
        log_error "有 $missing 個必要配置缺失"
    fi
}

################################################################################
# 顯示後續步驟
################################################################################
show_next_steps() {
    log_section "完成"

    echo ""
    log_success ".env 配置已更新！"
    echo ""
    log_info "下一步操作："
    echo ""
    echo "1. 重啟 queue-worker 使配置生效："
    echo "   docker restart axpep-worker"
    echo ""
    echo "2. 查看日誌驗證："
    echo "   docker logs -f axpep-worker"
    echo ""
    echo "3. 如需回退到之前的配置："
    echo "   cp $BACKUP_DIR/.env .env"
    echo "   docker restart axpep-worker"
    echo ""
}

################################################################################
# 主程序
################################################################################
main() {
    log_section ".env 配置更新工具"

    # 檢查是否在項目根目錄
    if [ ! -f "artisan" ]; then
        log_error "請在 Laravel 項目根目錄執行此腳本！"
        exit 1
    fi

    backup_env
    show_current_config
    update_config
    show_updated_config
    verify_config
    show_next_steps

    log_success "所有操作完成！"
}

main "$@"
