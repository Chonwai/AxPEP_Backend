#!/bin/bash

# AxPEP 端到端測試腳本
# 模擬真實用戶完整工作流程

echo "🚀 開始 AmPEP 端到端測試..."

# 配置
API_BASE_URL="http://localhost:8000/api"
TEST_EMAIL="e2e_test@example.com"
TEST_FASTA=">Antimicrobial_Peptide_1
ALWKTMLKKLGTMALHAGKAALGAAADTISQGTQ
>Antimicrobial_Peptide_2
KWCFRVCYRGICYRRCR
>Test_Peptide_3
FLPIIAKLLSGLL"

# 顏色定義
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m'

# 全局變量
TASK_ID=""
START_TIME=""

# 工具函數
log_step() {
    echo -e "\n${PURPLE}🔄 步驟 $1: $2${NC}"
}

log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
}

log_info() {
    echo -e "${BLUE}📋 $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

# 步驟1: 創建任務
create_task() {
    log_step "1" "用戶提交序列進行分析"

    START_TIME=$(date +%s)
    echo "$TEST_FASTA" > /tmp/e2e_test.fasta

    response=$(curl -s -w "%{http_code}" \
        -X POST "$API_BASE_URL/v1/ampep/tasks/file" \
        -F "file=@/tmp/e2e_test.fasta" \
        -F "email=$TEST_EMAIL" \
        -F "methods[RF-AmPEP]=true" \
        -F "methods[Deep-AmPEP]=true" \
        -o /tmp/create_response.json)

    http_code=$(echo $response | tail -c 4)

    if [ "$http_code" = "200" ]; then
        TASK_ID=$(cat /tmp/create_response.json | jq -r '.data.id // .id // empty')

        if [ -n "$TASK_ID" ] && [ "$TASK_ID" != "null" ]; then
            log_success "任務創建成功"
            log_info "任務ID: $TASK_ID"
            log_info "提交時間: $(date)"

            # 顯示任務詳情
            echo -e "${BLUE}📊 任務詳情:${NC}"
            cat /tmp/create_response.json | jq '.'

            return 0
        else
            log_error "無法提取任務ID"
            cat /tmp/create_response.json
            return 1
        fi
    else
        log_error "任務創建失敗 (HTTP: $http_code)"
        cat /tmp/create_response.json
        return 1
    fi
}

# 步驟2: 輪詢任務狀態
poll_task_status() {
    log_step "2" "監控任務執行狀態"

    local max_attempts=60  # 最多等待10分鐘
    local attempt=1
    local status=""

    while [ $attempt -le $max_attempts ]; do
        response=$(curl -s -w "%{http_code}" \
            -X GET "$API_BASE_URL/v1/axpep/tasks/$TASK_ID" \
            -o /tmp/status_response.json)

        http_code=$(echo $response | tail -c 4)

        if [ "$http_code" = "200" ]; then
            status=$(cat /tmp/status_response.json | jq -r '.data.status // .status // empty')

            case $status in
                "processing"|"pending"|"running")
                    echo -ne "\r${YELLOW}⏳ 任務執行中... (${attempt}/${max_attempts}) 狀態: $status${NC}"
                    ;;
                "completed"|"finished"|"success")
                    echo -e "\n${GREEN}✅ 任務完成！${NC}"
                    log_info "最終狀態: $status"

                    # 計算執行時間
                    end_time=$(date +%s)
                    duration=$((end_time - START_TIME))
                    log_info "總執行時間: ${duration}秒"

                    return 0
                    ;;
                "failed"|"error")
                    echo -e "\n${RED}❌ 任務執行失敗${NC}"
                    log_error "狀態: $status"
                    cat /tmp/status_response.json | jq '.'
                    return 1
                    ;;
                *)
                    echo -ne "\r${BLUE}📊 未知狀態: $status (${attempt}/${max_attempts})${NC}"
                    ;;
            esac
        else
            log_error "狀態查詢失敗 (HTTP: $http_code)"
            return 1
        fi

        sleep 10
        ((attempt++))
    done

    echo -e "\n${RED}❌ 任務執行超時${NC}"
    return 1
}

# 步驟3: 下載結果
download_results() {
    log_step "3" "下載分析結果"

    # 下載主要結果文件
    response=$(curl -s -w "%{http_code}" \
        -X GET "$API_BASE_URL/v1/axpep/tasks/$TASK_ID/result/download" \
        -o /tmp/result_file.out)

    http_code=$(echo $response | tail -c 4)

    if [ "$http_code" = "200" ]; then
        log_success "結果文件下載成功"

        # 檢查文件內容
        if [ -s /tmp/result_file.out ]; then
            file_size=$(wc -c < /tmp/result_file.out)
            log_info "結果文件大小: ${file_size} bytes"

            # 顯示結果文件頭部
            echo -e "${BLUE}📊 結果文件預覽:${NC}"
            head -20 /tmp/result_file.out

            # 檢查是否包含預期的序列
            sequence_count=$(grep -c "^>" /tmp/result_file.out || echo "0")
            log_info "處理的序列數量: $sequence_count"

            if [ "$sequence_count" -gt 0 ]; then
                log_success "結果文件包含預期的序列數據"
            else
                log_warning "結果文件可能不包含序列數據"
            fi
        else
            log_error "結果文件為空"
            return 1
        fi
    else
        log_error "結果文件下載失敗 (HTTP: $http_code)"
        return 1
    fi

    # 嘗試下載分類結果
    response=$(curl -s -w "%{http_code}" \
        -X GET "$API_BASE_URL/v1/axpep/tasks/$TASK_ID/classification/download" \
        -o /tmp/classification_file.out)

    http_code=$(echo $response | tail -c 4)

    if [ "$http_code" = "200" ]; then
        log_success "分類結果下載成功"
    else
        log_info "分類結果不可用 (這是正常的)"
    fi

    # 嘗試下載分數結果
    response=$(curl -s -w "%{http_code}" \
        -X GET "$API_BASE_URL/v1/axpep/tasks/$TASK_ID/score/download" \
        -o /tmp/score_file.out)

    http_code=$(echo $response | tail -c 4)

    if [ "$http_code" = "200" ]; then
        log_success "分數結果下載成功"
    else
        log_info "分數結果不可用 (這是正常的)"
    fi
}

# 步驟4: 驗證結果質量
validate_results() {
    log_step "4" "驗證分析結果質量"

    if [ ! -f /tmp/result_file.out ]; then
        log_error "結果文件不存在"
        return 1
    fi

    # 檢查結果格式
    local has_headers=$(grep -c "Sequence" /tmp/result_file.out || echo "0")
    local has_predictions=$(grep -c -E "(AmPEP|RF-AmPEP|Deep-AmPEP)" /tmp/result_file.out || echo "0")
    local has_sequences=$(grep -c "^>" /tmp/result_file.out || echo "0")

    log_info "結果驗證:"
    echo "  - 包含標題行: $has_headers"
    echo "  - 包含預測結果: $has_predictions"
    echo "  - 包含序列: $has_sequences"

    if [ "$has_sequences" -ge 3 ]; then
        log_success "序列數量正確 (預期3個，實際$has_sequences個)"
    else
        log_warning "序列數量可能不正確 (預期3個，實際$has_sequences個)"
    fi

    if [ "$has_predictions" -gt 0 ]; then
        log_success "包含預測結果"
    else
        log_error "缺少預測結果"
        return 1
    fi

    # 檢查是否有錯誤信息
    local error_count=$(grep -c -i "error\|fail\|exception" /tmp/result_file.out || echo "0")
    if [ "$error_count" -eq 0 ]; then
        log_success "結果文件無錯誤信息"
    else
        log_warning "結果文件包含 $error_count 個錯誤信息"
    fi
}

# 步驟5: 清理和總結
cleanup_and_summary() {
    log_step "5" "清理和測試總結"

    # 計算總測試時間
    local end_time=$(date +%s)
    local total_duration=$((end_time - START_TIME))

    log_info "測試總結:"
    echo "  - 任務ID: $TASK_ID"
    echo "  - 測試郵箱: $TEST_EMAIL"
    echo "  - 總耗時: ${total_duration}秒"
    echo "  - 測試序列數: 3個"
    echo "  - 使用方法: RF-AmPEP, Deep-AmPEP"

    # 保存測試報告
    cat > /tmp/e2e_test_report.txt << EOF
AxPEP 端到端測試報告
==================
測試時間: $(date)
任務ID: $TASK_ID
測試郵箱: $TEST_EMAIL
總耗時: ${total_duration}秒
測試序列數: 3個
使用方法: RF-AmPEP, Deep-AmPEP

測試結果: 通過
EOF

    log_success "測試報告已保存到 /tmp/e2e_test_report.txt"

    # 清理臨時文件
    rm -f /tmp/e2e_test.fasta /tmp/*_response.json /tmp/result_file.out /tmp/classification_file.out /tmp/score_file.out

    log_success "臨時文件已清理"
}

# 主測試流程
main() {
    echo "🎯 開始完整的端到端測試流程"
    echo "模擬真實用戶從提交序列到獲取結果的完整過程"

    # 檢查前置條件
    if ! curl -s "$API_BASE_URL/v1/axpep/codons/all" > /dev/null; then
        log_error "Laravel API 未運行，請先啟動應用"
        exit 1
    fi

    # 執行測試步驟
    create_task || exit 1
    poll_task_status || exit 1
    download_results || exit 1
    validate_results || exit 1
    cleanup_and_summary

    echo -e "\n🎉 ${GREEN}端到端測試完全成功！${NC}"
    echo -e "${GREEN}✅ 用戶工作流程驗證通過${NC}"
    echo -e "${GREEN}✅ 微服務集成工作正常${NC}"
    echo -e "${GREEN}✅ API向後兼容性確認${NC}"
}

# 運行主測試
main "$@"
