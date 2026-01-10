#!/bin/bash

################################################################################
# Docker 網絡問題診斷工具
# 
# 用途：在生產服務器上診斷 Docker 容器無法訪問宿主機服務的問題
# 使用：在服務器上執行 ./scripts/diagnose-docker-network.sh
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
log_success() { echo -e "${GREEN}[✓]${NC} $1"; }
log_fail() { echo -e "${RED}[✗]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[!]${NC} $1"; }
log_section() { echo -e "\n${CYAN}========================================${NC}"; echo -e "${CYAN}$1${NC}"; echo -e "${CYAN}========================================${NC}"; }

################################################################################
# 1. 系統環境檢查
################################################################################
log_section "1. 系統環境檢查"

log_info "檢查操作系統..."
uname -a
lsb_release -a 2>/dev/null || cat /etc/os-release || log_warning "無法獲取系統版本"

echo ""
log_info "檢查 Docker 版本..."
docker --version
docker-compose --version || docker compose version

echo ""
log_info "檢查 Docker 網絡驅動..."
docker network ls

################################################################################
# 2. 檢查容器運行狀態
################################################################################
log_section "2. 容器運行狀態"

log_info "當前運行的容器："
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

echo ""
if docker ps | grep -q "axpep-app"; then
    log_success "axpep-app 容器正在運行"
else
    log_fail "axpep-app 容器未運行！"
fi

if docker ps | grep -q "axpep-worker"; then
    log_success "axpep-worker 容器正在運行"
else
    log_fail "axpep-worker 容器未運行！"
fi

################################################################################
# 3. 網絡配置檢查
################################################################################
log_section "3. Docker 網絡配置"

log_info "檢查 axpep-network 網絡..."
docker network inspect axpep-network 2>/dev/null | grep -A 20 "IPAM" || log_warning "找不到 axpep-network"

echo ""
log_info "檢查容器的網絡連接..."
if docker ps | grep -q "axpep-app"; then
    APP_NETWORK=$(docker inspect axpep-app --format='{{range $net,$v := .NetworkSettings.Networks}}{{$net}} {{end}}')
    log_info "axpep-app 連接的網絡: $APP_NETWORK"
fi

if docker ps | grep -q "axpep-worker"; then
    WORKER_NETWORK=$(docker inspect axpep-worker --format='{{range $net,$v := .NetworkSettings.Networks}}{{$net}} {{end}}')
    log_info "axpep-worker 連接的網絡: $WORKER_NETWORK"
fi

################################################################################
# 4. host.docker.internal 檢查
################################################################################
log_section "4. host.docker.internal 解析檢查"

if docker ps | grep -q "axpep-worker"; then
    log_info "檢查 worker 容器的 /etc/hosts..."
    docker exec axpep-worker cat /etc/hosts | grep -E "(host\.docker\.internal|gateway)" || log_warning "未找到 host.docker.internal 配置"
    
    echo ""
    log_info "測試 DNS 解析..."
    if docker exec axpep-worker getent hosts host.docker.internal; then
        RESOLVED_IP=$(docker exec axpep-worker getent hosts host.docker.internal | awk '{print $1}')
        log_success "host.docker.internal 解析為: $RESOLVED_IP"
    else
        log_fail "host.docker.internal 無法解析！"
    fi
    
    echo ""
    log_info "測試 ping..."
    if docker exec axpep-worker ping -c 2 host.docker.internal 2>/dev/null; then
        log_success "可以 ping 通 host.docker.internal"
    else
        log_fail "無法 ping host.docker.internal"
    fi
fi

################################################################################
# 5. 宿主機端口監聽檢查
################################################################################
log_section "5. 宿主機微服務端口檢查"

log_info "檢查宿主機上的服務端口..."
echo ""

check_port() {
    local port=$1
    local service=$2
    
    if netstat -tlnp 2>/dev/null | grep ":$port " || ss -tlnp 2>/dev/null | grep ":$port "; then
        log_success "$service (Port $port) 正在監聽"
        
        # 檢查監聽地址
        LISTEN_ADDR=$(netstat -tlnp 2>/dev/null | grep ":$port " | awk '{print $4}' || ss -tlnp 2>/dev/null | grep ":$port " | awk '{print $4}')
        if echo "$LISTEN_ADDR" | grep -q "0.0.0.0:$port"; then
            log_success "  監聽所有接口 (0.0.0.0) ✓"
        elif echo "$LISTEN_ADDR" | grep -q "127.0.0.1:$port"; then
            log_fail "  僅監聽本地 (127.0.0.1) - 這會導致 Docker 容器無法訪問！"
            echo "    修復方法：修改微服務配置，監聽 0.0.0.0 而非 127.0.0.1"
        fi
    else
        log_fail "$service (Port $port) 未運行或未監聽"
    fi
}

check_port 8001 "AmPEP"
check_port 8002 "DeepAmPEP30"
check_port 8006 "BESTox"
check_port 8007 "SSL-GCN"
check_port 8889 "AMP Regression"

################################################################################
# 6. 從宿主機測試微服務
################################################################################
log_section "6. 從宿主機測試微服務連接"

test_from_host() {
    local port=$1
    local service=$2
    
    log_info "測試 $service (127.0.0.1:$port)..."
    if curl -s -f --max-time 3 "http://127.0.0.1:$port/health" > /dev/null 2>&1; then
        log_success "$service 在宿主機上可訪問"
    else
        log_fail "$service 在宿主機上無法訪問"
    fi
}

test_from_host 8001 "AmPEP"
test_from_host 8002 "DeepAmPEP30"
test_from_host 8006 "BESTox"

################################################################################
# 7. 從容器測試微服務
################################################################################
log_section "7. 從 Docker 容器測試微服務連接"

if docker ps | grep -q "axpep-worker"; then
    test_from_container() {
        local port=$1
        local service=$2
        
        log_info "測試 $service (host.docker.internal:$port)..."
        if docker exec axpep-worker curl -s -f --max-time 3 "http://host.docker.internal:$port/health" > /dev/null 2>&1; then
            log_success "$service 從容器可訪問"
        else
            log_fail "$service 從容器無法訪問"
            
            # 嘗試其他地址
            log_info "  嘗試使用 172.17.0.1..."
            if docker exec axpep-worker curl -s -f --max-time 3 "http://172.17.0.1:$port/health" > /dev/null 2>&1; then
                log_warning "  使用 172.17.0.1 可以訪問（但不推薦）"
            fi
        fi
    }
    
    test_from_container 8001 "AmPEP"
    test_from_container 8002 "DeepAmPEP30"
    test_from_container 8006 "BESTox"
else
    log_warning "axpep-worker 容器未運行，跳過容器內測試"
fi

################################################################################
# 8. 防火牆檢查
################################################################################
log_section "8. 防火牆和 iptables 規則"

log_info "檢查防火牆狀態..."
if command -v ufw &> /dev/null; then
    ufw status || log_info "ufw 未啟用"
elif command -v firewall-cmd &> /dev/null; then
    firewall-cmd --state || log_info "firewalld 未運行"
fi

echo ""
log_info "檢查 Docker iptables 規則..."
iptables -L DOCKER -n | head -20 || log_warning "需要 root 權限查看 iptables"

################################################################################
# 9. Docker Compose 配置檢查
################################################################################
log_section "9. Docker Compose 配置檢查"

COMPOSE_FILE="docker/docker-compose.yml"
if [ -f "$COMPOSE_FILE" ]; then
    log_info "檢查 extra_hosts 配置..."
    if grep -q "host.docker.internal:host-gateway" "$COMPOSE_FILE"; then
        log_success "extra_hosts 配置正確"
        grep -A 2 "extra_hosts" "$COMPOSE_FILE" | head -6
    else
        log_fail "extra_hosts 配置缺失或不正確"
    fi
    
    echo ""
    log_info "檢查網絡配置..."
    grep -A 5 "networks:" "$COMPOSE_FILE" | tail -10
else
    log_warning "找不到 $COMPOSE_FILE"
fi

################################################################################
# 10. 環境變量檢查
################################################################################
log_section "10. 環境變量配置檢查"

if docker ps | grep -q "axpep-worker"; then
    log_info "檢查容器內的微服務 URL 配置..."
    docker exec axpep-worker printenv | grep -E "(AMPEP|BESTOX|SSL|DEEPAMPEP).*URL" || log_warning "未找到微服務 URL 配置"
fi

################################################################################
# 總結與建議
################################################################################
log_section "診斷完成 - 問題總結"

echo ""
log_info "根據以上診斷結果，可能的問題："
echo ""
echo "1. 如果微服務僅監聽 127.0.0.1："
echo "   問題：Docker 容器無法訪問宿主機的 127.0.0.1"
echo "   解決：修改微服務配置，監聽 0.0.0.0"
echo ""
echo "2. 如果 host.docker.internal 無法解析："
echo "   問題：Docker 版本過舊或 extra_hosts 未生效"
echo "   解決：升級 Docker 到 20.10+ 或使用替代方案"
echo ""
echo "3. 如果防火牆阻擋："
echo "   問題：iptables 或 ufw 阻止了容器訪問"
echo "   解決：允許 Docker 網絡訪問相應端口"
echo ""

log_section "推薦的解決方案"
echo ""
echo "方案 1：使用 network_mode: host（最簡單）"
echo "  - 容器直接使用宿主機網絡"
echo "  - 可以直接訪問 127.0.0.1:8001"
echo "  - 缺點：失去網絡隔離"
echo ""
echo "方案 2：將微服務也容器化（最佳）"
echo "  - 所有服務在同一個 Docker 網絡"
echo "  - 使用服務名稱通信（如 ampep-service:8001）"
echo "  - 完全的容器化和隔離"
echo ""
echo "方案 3：修復 host.docker.internal（當前方案）"
echo "  - 確保微服務監聽 0.0.0.0"
echo "  - 確保 Docker 版本 >= 20.10"
echo "  - 確保容器重啟後配置生效"
echo ""

log_info "查看詳細日誌："
echo "  docker logs axpep-worker --tail 50"
echo ""
log_info "手動測試連接："
echo "  docker exec axpep-worker curl -v http://host.docker.internal:8001/health"
echo ""

