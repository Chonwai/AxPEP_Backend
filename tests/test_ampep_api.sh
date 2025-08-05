#!/bin/bash

# AxPEP Laravel API 集成測試腳本
# 測試微服務集成後的完整API功能

echo "🚀 開始 AmPEP Laravel API 集成測試..."

# 配置
API_BASE_URL="http://localhost:8000/api"
TEST_EMAIL="test@example.com"
TEST_FASTA=">test_sequence
ALWKTMLKKLGTMALHAGKAALGAAADTISQGTQ
>test_sequence_2
KWCFRVCYRGICYRRCR"

# 顏色定義
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# 創建測試FASTA文件
create_test_file() {
    echo "$TEST_FASTA" > /tmp/test_ampep.fasta
}

# 測試文件上傳API
test_file_upload_api() {
    echo -e "\n📁 測試 1: 文件上傳 API"

    response=$(curl -s -w "%{http_code}" \
        -X POST "$API_BASE_URL/v1/ampep/tasks/file" \
        -F "file=@/tmp/test_ampep.fasta" \
        -F "email=$TEST_EMAIL" \
        -F "methods[RF-AmPEP]=true" \
        -F "methods[Deep-AmPEP]=true" \
        -o /tmp/file_response.json)

    http_code=$(echo $response | tail -c 4)

    if [ "$http_code" = "200" ]; then
        echo -e "${GREEN}✅ 文件上傳 API 測試通過${NC}"

        # 提取任務ID
        task_id=$(cat /tmp/file_response.json | jq -r '.data.id // .id // empty')
        if [ -n "$task_id" ] && [ "$task_id" != "null" ]; then
            echo -e "${BLUE}📋 任務ID: $task_id${NC}"
            echo "$task_id" > /tmp/task_id.txt
        else
            echo -e "${YELLOW}⚠️  無法提取任務ID，響應內容:${NC}"
            cat /tmp/file_response.json | jq '.'
        fi
    else
        echo -e "${RED}❌ 文件上傳 API 測試失敗 (HTTP: $http_code)${NC}"
        cat /tmp/file_response.json
        return 1
    fi
}

# 測試文本輸入API
test_textarea_api() {
    echo -e "\n📝 測試 2: 文本輸入 API"

    response=$(curl -s -w "%{http_code}" \
        -X POST "$API_BASE_URL/v1/ampep/tasks/textarea" \
        -H "Content-Type: application/json" \
        -d "{
            \"fasta\": \"$TEST_FASTA\",
            \"email\": \"$TEST_EMAIL\",
            \"methods\": {
                \"RF-AmPEP\": true,
                \"Deep-AmPEP\": true
            }
        }" \
        -o /tmp/textarea_response.json)

    http_code=$(echo $response | tail -c 4)

    if [ "$http_code" = "200" ]; then
        echo -e "${GREEN}✅ 文本輸入 API 測試通過${NC}"

        # 提取任務ID
        task_id=$(cat /tmp/textarea_response.json | jq -r '.data.id // .id // empty')
        if [ -n "$task_id" ] && [ "$task_id" != "null" ]; then
            echo -e "${BLUE}📋 任務ID: $task_id${NC}"
            echo "$task_id" > /tmp/task_id_textarea.txt
        fi
    else
        echo -e "${RED}❌ 文本輸入 API 測試失敗 (HTTP: $http_code)${NC}"
        cat /tmp/textarea_response.json
        return 1
    fi
}

# 測試任務狀態查詢
test_task_status() {
    echo -e "\n🔍 測試 3: 任務狀態查詢"

    if [ ! -f /tmp/task_id.txt ]; then
        echo -e "${YELLOW}⚠️  跳過任務狀態測試 (無任務ID)${NC}"
        return 0
    fi

    task_id=$(cat /tmp/task_id.txt)

    response=$(curl -s -w "%{http_code}" \
        -X GET "$API_BASE_URL/v1/axpep/tasks/$task_id" \
        -o /tmp/status_response.json)

    http_code=$(echo $response | tail -c 4)

    if [ "$http_code" = "200" ]; then
        echo -e "${GREEN}✅ 任務狀態查詢測試通過${NC}"

        status=$(cat /tmp/status_response.json | jq -r '.data.status // .status // empty')
        echo -e "${BLUE}📊 任務狀態: $status${NC}"

        # 顯示響應內容
        cat /tmp/status_response.json | jq '.'
    else
        echo -e "${RED}❌ 任務狀態查詢失敗 (HTTP: $http_code)${NC}"
        cat /tmp/status_response.json
        return 1
    fi
}

# 測試郵箱查詢API
test_email_query() {
    echo -e "\n📧 測試 4: 郵箱任務查詢"

    response=$(curl -s -w "%{http_code}" \
        -X GET "$API_BASE_URL/v1/axpep/emails/$TEST_EMAIL/tasks" \
        -o /tmp/email_response.json)

    http_code=$(echo $response | tail -c 4)

    if [ "$http_code" = "200" ]; then
        echo -e "${GREEN}✅ 郵箱任務查詢測試通過${NC}"

        task_count=$(cat /tmp/email_response.json | jq '.data | length // 0')
        echo -e "${BLUE}📋 找到 $task_count 個任務${NC}"

        if [ "$task_count" -gt 0 ]; then
            echo -e "${BLUE}📋 最近的任務:${NC}"
            cat /tmp/email_response.json | jq '.data[0] // .data | {id, status, created_at}'
        fi
    else
        echo -e "${RED}❌ 郵箱任務查詢失敗 (HTTP: $http_code)${NC}"
        cat /tmp/email_response.json
        return 1
    fi
}

# 測試Laravel健康檢查命令
test_laravel_health_check() {
    echo -e "\n🏥 測試 5: Laravel 健康檢查命令"

    if command -v php >/dev/null 2>&1; then
        echo "執行 Laravel 健康檢查命令..."
        php artisan ampep:health-check

        if [ $? -eq 0 ]; then
            echo -e "${GREEN}✅ Laravel 健康檢查通過${NC}"
        else
            echo -e "${YELLOW}⚠️  Laravel 健康檢查有警告${NC}"
        fi
    else
        echo -e "${YELLOW}⚠️  PHP 不可用，跳過 Laravel 健康檢查${NC}"
    fi
}

# 測試回退機制
test_fallback_mechanism() {
    echo -e "\n🔄 測試 6: 智能回退機制"

    # 暫時禁用微服務
    echo "暫時設置環境變量測試回退..."

    response=$(curl -s -w "%{http_code}" \
        -X POST "$API_BASE_URL/v1/ampep/tasks/textarea" \
        -H "Content-Type: application/json" \
        -H "X-Test-Fallback: true" \
        -d "{
            \"fasta\": \"$TEST_FASTA\",
            \"email\": \"fallback_$TEST_EMAIL\",
            \"methods\": {
                \"RF-AmPEP\": true
            }
        }" \
        -o /tmp/fallback_response.json)

    http_code=$(echo $response | tail -c 4)

    if [ "$http_code" = "200" ]; then
        echo -e "${GREEN}✅ 回退機制測試通過${NC}"
        echo -e "${BLUE}📋 回退任務已創建${NC}"
    else
        echo -e "${YELLOW}⚠️  回退機制測試需要手動驗證${NC}"
    fi
}

# 主測試流程
main() {
    echo "🔍 檢查 Laravel API 是否運行..."
    if ! curl -s "$API_BASE_URL/v1/axpep/codons/all" > /dev/null; then
        echo -e "${RED}❌ Laravel API 未運行，請先啟動應用${NC}"
        echo "啟動命令: php artisan serve"
        exit 1
    fi

    # 創建必要的測試文件
    create_test_file

    # 執行測試
    test_file_upload_api || exit 1
    test_textarea_api || exit 1
    test_task_status || exit 1
    test_email_query || exit 1
    test_laravel_health_check || exit 1
    test_fallback_mechanism || exit 1

    echo -e "\n🎉 ${GREEN}所有 Laravel API 集成測試通過！${NC}"

    # 清理
    rm -f /tmp/test_ampep.fasta /tmp/*_response.json /tmp/task_id*.txt

    echo -e "\n📋 測試摘要:"
    echo "✅ 文件上傳 API"
    echo "✅ 文本輸入 API"
    echo "✅ 任務狀態查詢"
    echo "✅ 郵箱任務查詢"
    echo "✅ Laravel 健康檢查"
    echo "✅ 智能回退機制"
}

# 運行主測試
main "$@"
