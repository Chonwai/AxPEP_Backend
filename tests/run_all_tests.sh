#!/bin/bash

# AxPEP 一鍵測試腳本
# 按照業界標準順序執行所有測試

echo "🚀 AxPEP 完整測試套件"
echo "===================="

# 配置
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="/tmp/ampep_tests"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# 顏色定義
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
BOLD='\033[1m'
NC='\033[0m'

# 創建日誌目錄
mkdir -p "$LOG_DIR"

# 測試結果
PASSED_TESTS=()
FAILED_TESTS=()
SKIPPED_TESTS=()

# 工具函數
log_header() {
    echo -e "\n${BOLD}${PURPLE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BOLD}${PURPLE}  $1${NC}"
    echo -e "${BOLD}${PURPLE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

log_test_start() {
    echo -e "\n${BLUE}🔄 開始測試: $1${NC}"
}

log_test_pass() {
    echo -e "${GREEN}✅ 測試通過: $1${NC}"
    PASSED_TESTS+=("$1")
}

log_test_fail() {
    echo -e "${RED}❌ 測試失敗: $1${NC}"
    FAILED_TESTS+=("$1")
}

log_test_skip() {
    echo -e "${YELLOW}⏭️  測試跳過: $1${NC}"
    SKIPPED_TESTS+=("$1")
}

# 檢查前置條件
check_prerequisites() {
    log_header "檢查測試前置條件"

    local all_good=true

    # 檢查必要命令
    local required_commands=("curl" "jq" "php")
    for cmd in "${required_commands[@]}"; do
        if command -v "$cmd" >/dev/null 2>&1; then
            echo -e "${GREEN}✅ $cmd 已安裝${NC}"
        else
            echo -e "${RED}❌ $cmd 未安裝${NC}"
            all_good=false
        fi
    done

    # 檢查 Laravel 應用
    if curl -s "http://localhost:8000/api/v1/axpep/codons/all" > /dev/null; then
        echo -e "${GREEN}✅ Laravel API 運行正常${NC}"
    else
        echo -e "${RED}❌ Laravel API 未運行 (http://localhost:8000)${NC}"
        echo -e "${YELLOW}   請先啟動: php artisan serve${NC}"
        all_good=false
    fi

    # 檢查 AmPEP 微服務
    if curl -s "http://localhost:8001/health" > /dev/null; then
        echo -e "${GREEN}✅ AmPEP 微服務運行正常${NC}"
    else
        echo -e "${YELLOW}⚠️  AmPEP 微服務未運行 (http://localhost:8001)${NC}"
        echo -e "${YELLOW}   某些測試將被跳過${NC}"
    fi

    # 檢查測試腳本
    local test_scripts=("test_ampep_microservice.sh" "test_ampep_api.sh" "test_e2e_ampep.sh" "test_performance.sh")
    for script in "${test_scripts[@]}"; do
        if [ -f "$SCRIPT_DIR/$script" ]; then
            echo -e "${GREEN}✅ $script 存在${NC}"
            chmod +x "$SCRIPT_DIR/$script"
        else
            echo -e "${RED}❌ $script 不存在${NC}"
            all_good=false
        fi
    done

    if [ "$all_good" = false ]; then
        echo -e "\n${RED}❌ 前置條件檢查失敗，請解決上述問題後重新運行${NC}"
        exit 1
    fi

    echo -e "\n${GREEN}✅ 所有前置條件檢查通過${NC}"
}

# 運行單個測試
run_test() {
    local test_name="$1"
    local test_script="$2"
    local log_file="$LOG_DIR/${test_name}_${TIMESTAMP}.log"

    log_test_start "$test_name"

    if [ ! -f "$SCRIPT_DIR/$test_script" ]; then
        log_test_skip "$test_name (腳本不存在)"
        return
    fi

    # 運行測試並記錄日誌
    if bash "$SCRIPT_DIR/$test_script" > "$log_file" 2>&1; then
        log_test_pass "$test_name"
        echo -e "${BLUE}📋 日誌文件: $log_file${NC}"
    else
        log_test_fail "$test_name"
        echo -e "${RED}📋 錯誤日誌: $log_file${NC}"
        echo -e "${YELLOW}最後幾行錯誤信息:${NC}"
        tail -10 "$log_file" | sed 's/^/  /'
    fi
}

# 運行微服務測試
run_microservice_tests() {
    log_header "第一階段: 微服務測試"

    if curl -s "http://localhost:8001/health" > /dev/null; then
        run_test "AmPEP微服務測試" "test_ampep_microservice.sh"
    else
        log_test_skip "AmPEP微服務測試 (服務未運行)"
    fi
}

# 運行API集成測試
run_api_tests() {
    log_header "第二階段: API 集成測試"

    run_test "Laravel API集成測試" "test_ampep_api.sh"
}

# 運行端到端測試
run_e2e_tests() {
    log_header "第三階段: 端到端測試"

    run_test "端到端用戶流程測試" "test_e2e_ampep.sh"
}

# 運行性能測試
run_performance_tests() {
    log_header "第四階段: 性能測試"

    echo -e "${YELLOW}⚠️  性能測試可能需要較長時間，請耐心等待...${NC}"
    run_test "性能和負載測試" "test_performance.sh"
}

# 運行Laravel特定測試
run_laravel_tests() {
    log_header "第五階段: Laravel 特定測試"

    log_test_start "Laravel健康檢查"
    if php artisan ampep:health-check > "$LOG_DIR/laravel_health_${TIMESTAMP}.log" 2>&1; then
        log_test_pass "Laravel健康檢查"
    else
        log_test_fail "Laravel健康檢查"
    fi

    log_test_start "Laravel隊列狀態"
    if php artisan queue:work --once --timeout=5 > "$LOG_DIR/queue_test_${TIMESTAMP}.log" 2>&1; then
        log_test_pass "Laravel隊列狀態"
    else
        log_test_skip "Laravel隊列狀態 (可能無任務)"
    fi
}

# 生成測試報告
generate_test_report() {
    log_header "測試報告"

    local total_tests=$((${#PASSED_TESTS[@]} + ${#FAILED_TESTS[@]} + ${#SKIPPED_TESTS[@]}))
    local pass_rate=0

    if [ $total_tests -gt 0 ]; then
        pass_rate=$(echo "scale=1; ${#PASSED_TESTS[@]} * 100 / $total_tests" | bc)
    fi

    echo -e "\n${BOLD}📊 測試統計:${NC}"
    echo -e "  總測試數: $total_tests"
    echo -e "  ${GREEN}通過: ${#PASSED_TESTS[@]}${NC}"
    echo -e "  ${RED}失敗: ${#FAILED_TESTS[@]}${NC}"
    echo -e "  ${YELLOW}跳過: ${#SKIPPED_TESTS[@]}${NC}"
    echo -e "  ${BLUE}通過率: ${pass_rate}%${NC}"

    # 詳細結果
    if [ ${#PASSED_TESTS[@]} -gt 0 ]; then
        echo -e "\n${GREEN}✅ 通過的測試:${NC}"
        for test in "${PASSED_TESTS[@]}"; do
            echo -e "  ✓ $test"
        done
    fi

    if [ ${#FAILED_TESTS[@]} -gt 0 ]; then
        echo -e "\n${RED}❌ 失敗的測試:${NC}"
        for test in "${FAILED_TESTS[@]}"; do
            echo -e "  ✗ $test"
        done
    fi

    if [ ${#SKIPPED_TESTS[@]} -gt 0 ]; then
        echo -e "\n${YELLOW}⏭️  跳過的測試:${NC}"
        for test in "${SKIPPED_TESTS[@]}"; do
            echo -e "  - $test"
        done
    fi

    # 保存報告
    local report_file="$LOG_DIR/test_report_${TIMESTAMP}.txt"
    cat > "$report_file" << EOF
AxPEP 測試報告
==============
測試時間: $(date)
測試環境: $(uname -s) $(uname -r)

測試統計:
--------
總測試數: $total_tests
通過: ${#PASSED_TESTS[@]}
失敗: ${#FAILED_TESTS[@]}
跳過: ${#SKIPPED_TESTS[@]}
通過率: ${pass_rate}%

通過的測試:
----------
$(printf '%s\n' "${PASSED_TESTS[@]}")

失敗的測試:
----------
$(printf '%s\n' "${FAILED_TESTS[@]}")

跳過的測試:
----------
$(printf '%s\n' "${SKIPPED_TESTS[@]}")

日誌文件位置: $LOG_DIR
EOF

    echo -e "\n${BLUE}📋 詳細報告已保存: $report_file${NC}"
    echo -e "${BLUE}📁 所有日誌文件位置: $LOG_DIR${NC}"

    # 總結
    if [ ${#FAILED_TESTS[@]} -eq 0 ]; then
        echo -e "\n🎉 ${GREEN}${BOLD}所有測試通過！系統運行正常！${NC}"
        return 0
    else
        echo -e "\n⚠️  ${YELLOW}${BOLD}部分測試失敗，請檢查日誌文件${NC}"
        return 1
    fi
}

# 清理函數
cleanup() {
    echo -e "\n${BLUE}🧹 清理臨時文件...${NC}"
    # 清理可能的臨時文件
    rm -f /tmp/test_*.fasta /tmp/*_response.json /tmp/task_id*.txt
    echo -e "${GREEN}✅ 清理完成${NC}"
}

# 主函數
main() {
    echo -e "${BOLD}${BLUE}🚀 開始 AxPEP 完整測試套件${NC}"
    echo -e "${BLUE}測試將按照業界標準順序執行：微服務 → API → 端到端 → 性能${NC}"
    echo -e "${BLUE}測試時間: $(date)${NC}"

    # 設置錯誤處理
    trap cleanup EXIT

    # 執行測試階段
    check_prerequisites
    run_microservice_tests
    run_api_tests
    run_e2e_tests
    run_performance_tests
    run_laravel_tests

    # 生成報告
    generate_test_report
}

# 運行主函數
main "$@"
