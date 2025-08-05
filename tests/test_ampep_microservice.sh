#!/bin/bash

# AxPEP AmPEP 微服務測試腳本
# 業界標準的微服務測試方案

echo "🚀 開始 AmPEP 微服務測試..."

# 配置
MICROSERVICE_URL="http://localhost:8001"
TEST_FASTA=">test_sequence
ALWKTMLKKLGTMALHAGKAALGAAADTISQGTQ
>test_sequence_2
KWCFRVCYRGICYRRCR"

# 顏色定義
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 測試函數
test_health_check() {
    echo -e "\n📊 測試 1: 健康檢查"
    response=$(curl -s -w "%{http_code}" -o /tmp/health_response.json "$MICROSERVICE_URL/health")
    http_code=$(echo $response | tail -c 4)

    if [ "$http_code" = "200" ]; then
        echo -e "${GREEN}✅ 健康檢查通過${NC}"
        cat /tmp/health_response.json | jq '.'
    else
        echo -e "${RED}❌ 健康檢查失敗 (HTTP: $http_code)${NC}"
        return 1
    fi
}

test_service_info() {
    echo -e "\n📋 測試 2: 服務信息"
    response=$(curl -s -w "%{http_code}" -o /tmp/info_response.json "$MICROSERVICE_URL/api/info")
    http_code=$(echo $response | tail -c 4)

    if [ "$http_code" = "200" ]; then
        echo -e "${GREEN}✅ 服務信息獲取成功${NC}"
        cat /tmp/info_response.json | jq '.'
    else
        echo -e "${RED}❌ 服務信息獲取失敗 (HTTP: $http_code)${NC}"
        return 1
    fi
}

test_prediction() {
    echo -e "\n🧬 測試 3: 預測功能"

    # 創建測試文件
    echo "$TEST_FASTA" > /tmp/test.fasta

    # 測試預測API
    response=$(curl -s -w "%{http_code}" \
        -X POST "$MICROSERVICE_URL/api/predict" \
        -H "Content-Type: application/json" \
        -d "{\"fasta\": \"$TEST_FASTA\", \"task_id\": \"test_$(date +%s)\"}" \
        -o /tmp/predict_response.json)

    http_code=$(echo $response | tail -c 4)

    if [ "$http_code" = "200" ]; then
        echo -e "${GREEN}✅ 預測功能測試通過${NC}"
        cat /tmp/predict_response.json | jq '.'

        # 檢查響應格式
        status=$(cat /tmp/predict_response.json | jq -r '.status')
        if [ "$status" = "success" ]; then
            echo -e "${GREEN}✅ 預測結果格式正確${NC}"
        else
            echo -e "${YELLOW}⚠️  預測狀態: $status${NC}"
        fi
    else
        echo -e "${RED}❌ 預測功能測試失敗 (HTTP: $http_code)${NC}"
        cat /tmp/predict_response.json
        return 1
    fi
}

test_performance() {
    echo -e "\n⚡ 測試 4: 性能測試"

    start_time=$(date +%s.%N)

    for i in {1..5}; do
        curl -s -X POST "$MICROSERVICE_URL/api/predict" \
            -H "Content-Type: application/json" \
            -d "{\"fasta\": \"$TEST_FASTA\", \"task_id\": \"perf_test_$i\"}" \
            > /dev/null
    done

    end_time=$(date +%s.%N)
    duration=$(echo "$end_time - $start_time" | bc)
    avg_time=$(echo "scale=2; $duration / 5" | bc)

    echo -e "${GREEN}✅ 5次預測平均耗時: ${avg_time}秒${NC}"

    if (( $(echo "$avg_time < 10.0" | bc -l) )); then
        echo -e "${GREEN}✅ 性能測試通過 (< 10秒)${NC}"
    else
        echo -e "${YELLOW}⚠️  性能較慢，建議優化${NC}"
    fi
}

# 執行所有測試
echo "🔍 檢查微服務是否運行..."
if ! curl -s "$MICROSERVICE_URL/health" > /dev/null; then
    echo -e "${RED}❌ 微服務未運行，請先啟動 AmPEP 微服務${NC}"
    echo "啟動命令: docker-compose -f docker/docker-compose.yml up -d"
    exit 1
fi

# 運行測試
test_health_check || exit 1
test_service_info || exit 1
test_prediction || exit 1
test_performance || exit 1

echo -e "\n🎉 ${GREEN}所有微服務測試通過！${NC}"

# 清理
rm -f /tmp/health_response.json /tmp/info_response.json /tmp/predict_response.json /tmp/test.fasta

echo -e "\n📋 測試摘要:"
echo "✅ 健康檢查"
echo "✅ 服務信息"
echo "✅ 預測功能"
echo "✅ 性能測試"
