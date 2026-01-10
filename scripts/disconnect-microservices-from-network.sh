#!/bin/bash

################################################################################
# 微服務網絡斷開腳本（回退工具）
#
# 目的：斷開所有微服務容器與 docker_axpep-network 的連接
# 用途：回退到原始網絡配置
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

# 目標網絡
TARGET_NETWORK="docker_axpep-network"

# 微服務容器列表
declare -A MICROSERVICES=(
    ["docker-ampep-microservice-1"]="AmPEP 預測服務"
    ["deep-ampep30"]="DeepAmPEP30 預測服務"
    ["bestox-api-service"]="BESTox 毒性預測服務"
    ["ssl-gcn-toxicity-prediction"]="SSL-GCN 毒性預測服務"
    ["amp_regression_ec_sa_fastapi-amp-regression-predict-flask-1"]="AMP Regression 預測服務"
    ["docker-xdeep-acpep-api-1"]="xDeep AcPEP 服務"
    ["docker-xdeep-acpep-classification-api-1"]="xDeep AcPEP 分類服務"
    ["docker-api-1"]="Codon API 服務"
    ["ecotoxicology_fastapi-ecotoxicology-predict-fastapi-1"]="生態毒理學預測服務"
)

################################################################################
# 檢查容器是否連接到網絡
################################################################################
is_container_connected() {
    local container=$1
    local network=$2

    local networks=$(docker inspect "$container" --format '{{range $net, $conf := .NetworkSettings.Networks}}{{$net}} {{end}}' 2>/dev/null)

    if echo "$networks" | grep -q "$network"; then
        return 0
    else
        return 1
    fi
}

################################################################################
# 斷開容器與網絡的連接
################################################################################
disconnect_container() {
    local container=$1
    local description=$2

    log_info "處理容器: $container ($description)"

    # 檢查容器是否存在
    if ! docker ps -a --format '{{.Names}}' | grep -q "^${container}$"; then
        log_warning "  容器不存在，跳過"
        return 0
    fi

    # 檢查是否已連接
    if ! is_container_connected "$container" "$TARGET_NETWORK"; then
        log_success "  未連接到 $TARGET_NETWORK，無需斷開"
        return 0
    fi

    # 斷開連接
    log_info "  正在斷開與 $TARGET_NETWORK 的連接..."
    if docker network disconnect "$TARGET_NETWORK" "$container" 2>/dev/null; then
        log_success "  斷開成功！"
        return 0
    else
        log_error "  斷開失敗"
        return 1
    fi
}

################################################################################
# 斷開所有微服務
################################################################################
disconnect_all_microservices() {
    log_section "斷開微服務網絡連接"

    local success_count=0
    local skip_count=0
    local fail_count=0

    for container in "${!MICROSERVICES[@]}"; do
        if disconnect_container "$container" "${MICROSERVICES[$container]}"; then
            if docker ps -a --format '{{.Names}}' | grep -q "^${container}$"; then
                if is_container_connected "$container" "$TARGET_NETWORK"; then
                    ((fail_count++))
                else
                    ((success_count++))
                fi
            else
                ((skip_count++))
            fi
        else
            ((fail_count++))
        fi
    done

    echo ""
    log_info "斷開結果統計："
    log_success "  成功: $success_count"
    log_warning "  跳過: $skip_count (容器不存在)"
    if [ $fail_count -gt 0 ]; then
        log_error "  失敗: $fail_count"
    fi

    return $fail_count
}

################################################################################
# 確認操作
################################################################################
confirm_operation() {
    log_section "確認操作"

    log_warning "此操作將斷開所有微服務容器與 docker_axpep-network 的連接"
    log_warning "AxPEP Backend 將無法通過容器名訪問微服務"
    echo ""

    read -p "確認要繼續嗎？(y/n) " -n 1 -r
    echo

    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_info "操作已取消"
        exit 0
    fi
}

################################################################################
# 顯示後續步驟
################################################################################
show_next_steps() {
    log_section "完成"

    echo ""
    log_success "網絡連接已斷開！"
    echo ""
    log_info "如果需要恢復使用微服務，有以下選擇："
    echo ""
    echo "1. 重新連接微服務到網絡："
    echo "   bash scripts/connect-microservices-to-network.sh"
    echo ""
    echo "2. 使用 host.docker.internal 方案："
    echo "   - 更新 .env 使用 host.docker.internal"
    echo "   - 確保微服務監聽 0.0.0.0"
    echo ""
    echo "3. 使用 host network mode："
    echo "   bash scripts/switch-network-solution.sh 1"
    echo ""
}

################################################################################
# 主程序
################################################################################
main() {
    log_section "微服務網絡斷開工具"

    # 確認操作
    confirm_operation

    # 執行斷開
    if disconnect_all_microservices; then
        log_success "所有操作完成！"
    else
        log_error "部分操作失敗"
    fi

    show_next_steps
}

main "$@"
