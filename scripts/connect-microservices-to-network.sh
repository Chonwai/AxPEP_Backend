#!/bin/bash

################################################################################
# 微服務網絡連接腳本
#
# 目的：將所有微服務容器連接到 docker_axpep-network
# 優點：不修改任何 docker-compose.yml，運行時動態連接
# 原理：使用 docker network connect 讓容器加入多個網絡
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
# 檢查網絡是否存在
################################################################################
check_network_exists() {
    log_section "1. 檢查目標網絡"
    
    if docker network inspect "$TARGET_NETWORK" >/dev/null 2>&1; then
        log_success "目標網絡存在: $TARGET_NETWORK"
        
        # 顯示網絡信息
        local network_info=$(docker network inspect "$TARGET_NETWORK" --format '{{.Driver}} | {{.Scope}}')
        log_info "網絡類型: $network_info"
        
        return 0
    else
        log_error "目標網絡不存在: $TARGET_NETWORK"
        log_warning "請確保 AxPEP Backend 容器正在運行"
        return 1
    fi
}

################################################################################
# 檢查容器是否運行
################################################################################
check_container_running() {
    local container=$1
    if docker ps --format '{{.Names}}' | grep -q "^${container}$"; then
        return 0
    else
        return 1
    fi
}

################################################################################
# 檢查容器是否已連接到網絡
################################################################################
is_container_connected() {
    local container=$1
    local network=$2
    
    # 獲取容器連接的網絡列表
    local networks=$(docker inspect "$container" --format '{{range $net, $conf := .NetworkSettings.Networks}}{{$net}} {{end}}' 2>/dev/null)
    
    if echo "$networks" | grep -q "$network"; then
        return 0
    else
        return 1
    fi
}

################################################################################
# 連接容器到網絡
################################################################################
connect_container() {
    local container=$1
    local description=$2
    
    log_info "處理容器: $container ($description)"
    
    # 檢查容器是否運行
    if ! check_container_running "$container"; then
        log_warning "  容器未運行，跳過"
        return 0
    fi
    
    # 檢查是否已連接
    if is_container_connected "$container" "$TARGET_NETWORK"; then
        log_success "  已連接到 $TARGET_NETWORK"
        return 0
    fi
    
    # 連接到網絡
    log_info "  正在連接到 $TARGET_NETWORK..."
    if docker network connect "$TARGET_NETWORK" "$container" 2>/dev/null; then
        log_success "  連接成功！"
        return 0
    else
        log_error "  連接失敗"
        return 1
    fi
}

################################################################################
# 連接所有微服務
################################################################################
connect_all_microservices() {
    log_section "2. 連接微服務容器到網絡"
    
    local success_count=0
    local skip_count=0
    local fail_count=0
    
    for container in "${!MICROSERVICES[@]}"; do
        if connect_container "$container" "${MICROSERVICES[$container]}"; then
            if check_container_running "$container"; then
                ((success_count++))
            else
                ((skip_count++))
            fi
        else
            ((fail_count++))
        fi
    done
    
    echo ""
    log_info "連接結果統計："
    log_success "  成功: $success_count"
    log_warning "  跳過: $skip_count (容器未運行)"
    if [ $fail_count -gt 0 ]; then
        log_error "  失敗: $fail_count"
    fi
    
    return $fail_count
}

################################################################################
# 驗證 DNS 解析
################################################################################
verify_dns_resolution() {
    log_section "3. 驗證 DNS 解析"
    
    # 檢查 axpep-worker 容器
    if ! check_container_running "axpep-worker"; then
        log_error "axpep-worker 容器未運行，無法驗證"
        return 1
    fi
    
    log_info "在 axpep-worker 容器內測試 DNS 解析..."
    echo ""
    
    local test_containers=(
        "docker-ampep-microservice-1"
        "deep-ampep30"
        "bestox-api-service"
        "ssl-gcn-toxicity-prediction"
    )
    
    local success=0
    local failed=0
    
    for container in "${test_containers[@]}"; do
        if ! check_container_running "$container"; then
            continue
        fi
        
        # 測試 DNS 解析
        if docker exec axpep-worker getent hosts "$container" >/dev/null 2>&1; then
            local ip=$(docker exec axpep-worker getent hosts "$container" | awk '{print $1}')
            log_success "  $container → $ip"
            ((success++))
        else
            log_error "  $container 無法解析"
            ((failed++))
        fi
    done
    
    echo ""
    if [ $failed -eq 0 ]; then
        log_success "所有微服務 DNS 解析成功！"
        return 0
    else
        log_error "有 $failed 個微服務無法解析"
        return 1
    fi
}

################################################################################
# 測試 HTTP 連接
################################################################################
test_http_connectivity() {
    log_section "4. 測試 HTTP 連接"
    
    if ! check_container_running "axpep-worker"; then
        log_warning "axpep-worker 容器未運行，跳過 HTTP 測試"
        return 0
    fi
    
    log_info "測試微服務 HTTP 連接..."
    echo ""
    
    local endpoints=(
        "docker-ampep-microservice-1:8001/health"
        "deep-ampep30:8002/health"
        "bestox-api-service:8006/health"
        "ssl-gcn-toxicity-prediction:8007/health"
    )
    
    for endpoint in "${endpoints[@]}"; do
        local container=$(echo "$endpoint" | cut -d: -f1)
        
        if ! check_container_running "$container"; then
            continue
        fi
        
        if docker exec axpep-worker curl -s -f --max-time 5 "http://$endpoint" >/dev/null 2>&1; then
            log_success "  ✓ http://$endpoint"
        else
            log_warning "  ⚠ http://$endpoint (可能服務未提供 /health 端點)"
        fi
    done
}

################################################################################
# 顯示網絡拓撲
################################################################################
show_network_topology() {
    log_section "5. 網絡拓撲"
    
    log_info "docker_axpep-network 中的容器："
    echo ""
    
    docker network inspect "$TARGET_NETWORK" --format '{{range .Containers}}  - {{.Name}} ({{.IPv4Address}})
{{end}}'
}

################################################################################
# 顯示後續步驟
################################################################################
show_next_steps() {
    log_section "完成"
    
    echo ""
    log_success "微服務網絡連接配置完成！"
    echo ""
    log_info "下一步操作："
    echo ""
    echo "1. 確保 .env 文件使用容器名稱："
    echo "   AMPEP_MICROSERVICE_BASE_URL=\"http://docker-ampep-microservice-1:8001\""
    echo "   DEEPAMPEP30_MICROSERVICE_BASE_URL=\"http://deep-ampep30:8002\""
    echo "   BESTOX_MICROSERVICE_BASE_URL=\"http://bestox-api-service:8006\""
    echo "   SSL_BESTOX_MICROSERVICE_BASE_URL=\"http://ssl-gcn-toxicity-prediction:8007\""
    echo ""
    echo "2. 如果 .env 不正確，運行："
    echo "   bash scripts/update-env-for-container-names.sh"
    echo ""
    echo "3. 重啟 queue-worker 使 .env 生效："
    echo "   docker restart axpep-worker"
    echo ""
    echo "4. 查看日誌驗證："
    echo "   docker logs -f axpep-worker"
    echo ""
    echo "5. 提交測試任務進行驗證"
    echo ""
    log_info "如需斷開連接（回退），執行："
    echo "   bash scripts/disconnect-microservices-from-network.sh"
    echo ""
}

################################################################################
# 主程序
################################################################################
main() {
    log_section "微服務網絡連接工具"
    log_info "將所有微服務容器連接到 docker_axpep-network"
    log_info "這樣 AxPEP Backend 可以通過容器名直接訪問微服務"
    
    # 執行步驟
    if ! check_network_exists; then
        exit 1
    fi
    
    if ! connect_all_microservices; then
        log_warning "部分容器連接失敗，但繼續驗證..."
    fi
    
    verify_dns_resolution
    test_http_connectivity
    show_network_topology
    show_next_steps
    
    log_success "所有操作完成！"
}

main "$@"
