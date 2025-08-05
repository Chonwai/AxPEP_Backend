#!/bin/bash

# AxPEP 性能和壓力測試腳本
# 業界標準的API性能測試

echo "🚀 開始 AmPEP 性能測試..."

# 配置
API_BASE_URL="http://localhost:8000/api"
MICROSERVICE_URL="http://localhost:8001"
CONCURRENT_USERS=5
TEST_DURATION=60  # 秒
TEST_EMAIL="perf_test@example.com"

# 測試序列 (不同長度)
SHORT_FASTA=">short_peptide
ALWKTMLKKLGTMALHAGKAALGAAADTISQGTQ"

MEDIUM_FASTA=">medium_peptide_1
ALWKTMLKKLGTMALHAGKAALGAAADTISQGTQ
>medium_peptide_2
KWCFRVCYRGICYRRCR
>medium_peptide_3
FLPIIAKLLSGLL"

LONG_FASTA=">long_peptide_1
ALWKTMLKKLGTMALHAGKAALGAAADTISQGTQ
>long_peptide_2
KWCFRVCYRGICYRRCR
>long_peptide_3
FLPIIAKLLSGLL
>long_peptide_4
GLLKRIKTLL
>long_peptide_5
RRWCFRVCYRGICYRRCR"

# 顏色定義
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m'

# 性能測試結果
RESULTS_FILE="/tmp/ampep_performance_results.txt"

# 初始化結果文件
init_results() {
    cat > $RESULTS_FILE << EOF
AxPEP 性能測試報告
==================
測試時間: $(date)
併發用戶數: $CONCURRENT_USERS
測試持續時間: $TEST_DURATION 秒

EOF
}

# 測試微服務直接性能
test_microservice_performance() {
    echo -e "\n${PURPLE}🔬 測試 1: 微服務直接性能${NC}"

    local test_cases=("短序列" "中等序列" "長序列")
    local test_data=("$SHORT_FASTA" "$MEDIUM_FASTA" "$LONG_FASTA")

    echo "微服務性能測試結果:" >> $RESULTS_FILE
    echo "==================" >> $RESULTS_FILE

    for i in {0..2}; do
        echo -e "\n${BLUE}📊 測試 ${test_cases[$i]}...${NC}"

        local total_time=0
        local success_count=0
        local test_count=10

        for j in $(seq 1 $test_count); do
            local start_time=$(date +%s.%N)

            response=$(curl -s -w "%{http_code}" \
                -X POST "$MICROSERVICE_URL/api/predict" \
                -H "Content-Type: application/json" \
                -d "{\"fasta\": \"${test_data[$i]}\", \"task_id\": \"perf_test_${i}_${j}\"}" \
                -o /dev/null)

            local end_time=$(date +%s.%N)
            local duration=$(echo "$end_time - $start_time" | bc)

            if [ "${response: -3}" = "200" ]; then
                total_time=$(echo "$total_time + $duration" | bc)
                ((success_count++))
            fi
        done

        if [ $success_count -gt 0 ]; then
            local avg_time=$(echo "scale=3; $total_time / $success_count" | bc)
            echo -e "${GREEN}✅ ${test_cases[$i]}: 平均 ${avg_time}秒 (成功率: $success_count/$test_count)${NC}"

            echo "${test_cases[$i]}: ${avg_time}秒 (成功率: $success_count/$test_count)" >> $RESULTS_FILE
        else
            echo -e "${RED}❌ ${test_cases[$i]}: 全部失敗${NC}"
            echo "${test_cases[$i]}: 全部失敗" >> $RESULTS_FILE
        fi
    done

    echo "" >> $RESULTS_FILE
}

# 測試Laravel API性能
test_api_performance() {
    echo -e "\n${PURPLE}🔬 測試 2: Laravel API 性能${NC}"

    echo "Laravel API 性能測試結果:" >> $RESULTS_FILE
    echo "========================" >> $RESULTS_FILE

    # 測試任務創建性能
    echo -e "\n${BLUE}📊 測試任務創建性能...${NC}"

    local total_time=0
    local success_count=0
    local test_count=20

    for i in $(seq 1 $test_count); do
        local start_time=$(date +%s.%N)

        response=$(curl -s -w "%{http_code}" \
            -X POST "$API_BASE_URL/v1/ampep/tasks/textarea" \
            -H "Content-Type: application/json" \
            -d "{
                \"fasta\": \"$SHORT_FASTA\",
                \"email\": \"perf_${i}@example.com\",
                \"methods\": {\"RF-AmPEP\": true}
            }" \
            -o /dev/null)

        local end_time=$(date +%s.%N)
        local duration=$(echo "$end_time - $start_time" | bc)

        if [ "${response: -3}" = "200" ]; then
            total_time=$(echo "$total_time + $duration" | bc)
            ((success_count++))
        fi
    done

    if [ $success_count -gt 0 ]; then
        local avg_time=$(echo "scale=3; $total_time / $success_count" | bc)
        echo -e "${GREEN}✅ 任務創建: 平均 ${avg_time}秒 (成功率: $success_count/$test_count)${NC}"
        echo "任務創建: ${avg_time}秒 (成功率: $success_count/$test_count)" >> $RESULTS_FILE
    else
        echo -e "${RED}❌ 任務創建: 全部失敗${NC}"
        echo "任務創建: 全部失敗" >> $RESULTS_FILE
    fi

    echo "" >> $RESULTS_FILE
}

# 併發測試
test_concurrent_load() {
    echo -e "\n${PURPLE}🔬 測試 3: 併發負載測試${NC}"

    echo "併發負載測試結果:" >> $RESULTS_FILE
    echo "=================" >> $RESULTS_FILE

    # 創建併發測試腳本
    cat > /tmp/concurrent_test.sh << 'EOF'
#!/bin/bash
API_BASE_URL="$1"
USER_ID="$2"
TEST_FASTA="$3"

success_count=0
total_requests=10

for i in $(seq 1 $total_requests); do
    response=$(curl -s -w "%{http_code}" \
        -X POST "$API_BASE_URL/v1/ampep/tasks/textarea" \
        -H "Content-Type: application/json" \
        -d "{
            \"fasta\": \"$TEST_FASTA\",
            \"email\": \"concurrent_${USER_ID}_${i}@example.com\",
            \"methods\": {\"RF-AmPEP\": true}
        }" \
        -o /dev/null)

    if [ "${response: -3}" = "200" ]; then
        ((success_count++))
    fi

    sleep 1
done

echo "$USER_ID:$success_count:$total_requests"
EOF

    chmod +x /tmp/concurrent_test.sh

    echo -e "${BLUE}📊 啟動 $CONCURRENT_USERS 個併發用戶...${NC}"

    local start_time=$(date +%s)
    local pids=()

    # 啟動併發測試
    for user_id in $(seq 1 $CONCURRENT_USERS); do
        /tmp/concurrent_test.sh "$API_BASE_URL" "$user_id" "$SHORT_FASTA" > /tmp/result_$user_id.txt &
        pids+=($!)
    done

    # 等待所有測試完成
    for pid in "${pids[@]}"; do
        wait $pid
    done

    local end_time=$(date +%s)
    local total_duration=$((end_time - start_time))

    # 統計結果
    local total_success=0
    local total_requests=0

    for user_id in $(seq 1 $CONCURRENT_USERS); do
        if [ -f /tmp/result_$user_id.txt ]; then
            local result=$(cat /tmp/result_$user_id.txt)
            local user_success=$(echo $result | cut -d: -f2)
            local user_total=$(echo $result | cut -d: -f3)

            total_success=$((total_success + user_success))
            total_requests=$((total_requests + user_total))
        fi
    done

    local success_rate=$(echo "scale=2; $total_success * 100 / $total_requests" | bc)
    local throughput=$(echo "scale=2; $total_requests / $total_duration" | bc)

    echo -e "${GREEN}✅ 併發測試完成${NC}"
    echo -e "${BLUE}📊 總請求數: $total_requests${NC}"
    echo -e "${BLUE}📊 成功請求數: $total_success${NC}"
    echo -e "${BLUE}📊 成功率: ${success_rate}%${NC}"
    echo -e "${BLUE}📊 總耗時: ${total_duration}秒${NC}"
    echo -e "${BLUE}📊 吞吐量: ${throughput} 請求/秒${NC}"

    cat >> $RESULTS_FILE << EOF
併發用戶數: $CONCURRENT_USERS
總請求數: $total_requests
成功請求數: $total_success
成功率: ${success_rate}%
總耗時: ${total_duration}秒
吞吐量: ${throughput} 請求/秒

EOF

    # 清理
    rm -f /tmp/result_*.txt /tmp/concurrent_test.sh
}

# 內存和資源監控
monitor_resources() {
    echo -e "\n${PURPLE}🔬 測試 4: 資源使用監控${NC}"

    echo "資源使用監控:" >> $RESULTS_FILE
    echo "=============" >> $RESULTS_FILE

    # 檢查Docker容器資源使用（如果存在）
    if command -v docker >/dev/null 2>&1; then
        echo -e "${BLUE}📊 Docker 容器資源使用:${NC}"

        local containers=$(docker ps --format "table {{.Names}}\t{{.Image}}" | grep -E "(ampep|axpep)" || echo "")

        if [ -n "$containers" ]; then
            echo "$containers"
            docker stats --no-stream --format "table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}" $(docker ps -q) | grep -E "(ampep|axpep)" || echo "無相關容器運行"

            docker stats --no-stream --format "{{.Name}}: CPU {{.CPUPerc}}, Memory {{.MemUsage}}" $(docker ps -q) | grep -E "(ampep|axpep)" >> $RESULTS_FILE || echo "無相關容器運行" >> $RESULTS_FILE
        else
            echo "無相關Docker容器運行"
            echo "無相關Docker容器運行" >> $RESULTS_FILE
        fi
    else
        echo "Docker 不可用，跳過容器監控"
        echo "Docker 不可用，跳過容器監控" >> $RESULTS_FILE
    fi

    # 系統資源使用
    echo -e "${BLUE}📊 系統資源使用:${NC}"

    local cpu_usage=$(top -l 1 -n 0 | grep "CPU usage" | awk '{print $3}' | sed 's/%//' || echo "N/A")
    local memory_usage=$(vm_stat | grep "Pages active" | awk '{print $3}' | sed 's/\.//' || echo "N/A")

    echo "CPU 使用率: ${cpu_usage}%"
    echo "內存使用: ${memory_usage} pages"

    echo "系統CPU使用率: ${cpu_usage}%" >> $RESULTS_FILE
    echo "系統內存使用: ${memory_usage} pages" >> $RESULTS_FILE
    echo "" >> $RESULTS_FILE
}

# 生成性能報告
generate_report() {
    echo -e "\n${PURPLE}📊 生成性能測試報告${NC}"

    cat >> $RESULTS_FILE << EOF
測試總結:
========
測試完成時間: $(date)
測試環境: $(uname -s) $(uname -r)
PHP版本: $(php -v | head -n1 || echo "N/A")

建議:
====
1. 如果微服務響應時間 > 5秒，考慮優化算法或增加資源
2. 如果API成功率 < 95%，檢查錯誤日誌和資源限制
3. 如果併發性能不佳，考慮增加worker進程或使用負載均衡
4. 定期監控資源使用，確保系統穩定運行

EOF

    echo -e "${GREEN}✅ 性能測試報告已生成: $RESULTS_FILE${NC}"

    # 顯示報告摘要
    echo -e "\n${BLUE}📋 測試報告摘要:${NC}"
    tail -20 $RESULTS_FILE
}

# 主測試流程
main() {
    echo "🎯 開始全面的性能測試"
    echo "測試包括微服務性能、API性能、併發負載和資源監控"

    # 檢查前置條件
    if ! curl -s "$API_BASE_URL/v1/axpep/codons/all" > /dev/null; then
        echo -e "${RED}❌ Laravel API 未運行${NC}"
        exit 1
    fi

    if ! curl -s "$MICROSERVICE_URL/health" > /dev/null; then
        echo -e "${YELLOW}⚠️  AmPEP 微服務未運行，跳過微服務性能測試${NC}"
    fi

    # 初始化並執行測試
    init_results

    if curl -s "$MICROSERVICE_URL/health" > /dev/null; then
        test_microservice_performance
    fi

    test_api_performance
    test_concurrent_load
    monitor_resources
    generate_report

    echo -e "\n🎉 ${GREEN}性能測試完成！${NC}"
    echo -e "${GREEN}✅ 查看詳細報告: $RESULTS_FILE${NC}"
}

# 運行主測試
main "$@"
