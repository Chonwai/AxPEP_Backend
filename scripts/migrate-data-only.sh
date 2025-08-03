#!/bin/bash
set -e

echo "🚀 MySQL到Supabase數據遷移工具（僅數據）"
echo "========================================="
echo "此腳本只遷移數據，不會重建表結構"

# 顏色定義
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# 配置（基於之前的設置）
MYSQL_SOCKET="/Applications/MAMP/tmp/mysql/mysql.sock"
MYSQL_DB="axpep_development"
MYSQL_USER="root"
MYSQL_PASS="root"

SUPABASE_HOST="aws-0-ap-northeast-1.pooler.supabase.com"
SUPABASE_PORT="6543"
SUPABASE_USER="postgres.mykbxfdbpdjaylcvgpbq"
read -s -p "請輸入Supabase密碼: " SUPABASE_PASS
echo

# 測試連接
echo ""
echo "🔍 測試數據庫連接..."

# 測試MySQL
if [ -n "$MYSQL_PASS" ]; then
    MYSQL_TEST_CMD="mysql --socket=\"$MYSQL_SOCKET\" -u \"$MYSQL_USER\" -p\"$MYSQL_PASS\""
else
    MYSQL_TEST_CMD="mysql --socket=\"$MYSQL_SOCKET\" -u \"$MYSQL_USER\""
fi

if eval "$MYSQL_TEST_CMD -e \"SELECT 1;\" \"$MYSQL_DB\"" &>/dev/null; then
    echo -e "${GREEN}✅ MySQL連接成功${NC}"
else
    echo -e "${RED}❌ MySQL連接失敗${NC}"
    echo "   請檢查MAMP是否啟動，以及用戶名密碼是否正確"
    exit 1
fi

# 測試Supabase
if PGPASSWORD="$SUPABASE_PASS" psql -h "$SUPABASE_HOST" -p "$SUPABASE_PORT" -U "$SUPABASE_USER" -d postgres -c "SELECT 1;" &>/dev/null; then
    echo -e "${GREEN}✅ Supabase連接成功${NC}"
else
    echo -e "${RED}❌ Supabase連接失敗${NC}"
    exit 1
fi

# 定義需要遷移的表（按重要性排序）
CORE_TABLES=("codons" "tasks" "tasks_methods")
IMPORTANT_TABLES=("users" "migrations")
OPTIONAL_TABLES=("jobs" "failed_jobs" "password_resets")

# 函數：檢查表是否存在數據
check_table_data() {
    local table=$1
    if [ -n "$MYSQL_PASS" ]; then
        local count=$(mysql --socket="$MYSQL_SOCKET" -u "$MYSQL_USER" -p"$MYSQL_PASS" -e "SELECT COUNT(*) FROM $table;" "$MYSQL_DB" 2>/dev/null | tail -n1)
    else
        local count=$(mysql --socket="$MYSQL_SOCKET" -u "$MYSQL_USER" -e "SELECT COUNT(*) FROM $table;" "$MYSQL_DB" 2>/dev/null | tail -n1)
    fi
    echo "$count"
}

# 函數：遷移單個表的數據
migrate_table_data() {
    local table=$1
    echo "   📊 遷移表: $table"

    # 檢查源表數據量
    local source_count=$(check_table_data "$table")
    echo "      源數據: $source_count 條記錄"

    if [ "$source_count" -eq 0 ]; then
        echo -e "${YELLOW}      ⚠️ 表 $table 沒有數據，跳過${NC}"
        return 0
    fi

    # 導出表數據（僅數據，不包含結構）
    local temp_file="${table}_data_$(date +%Y%m%d_%H%M%S).sql"

    if [ -n "$MYSQL_PASS" ]; then
        mysqldump --socket="$MYSQL_SOCKET" -u "$MYSQL_USER" -p"$MYSQL_PASS" \
            --no-create-info \
            --complete-insert \
            --extended-insert=FALSE \
            --single-transaction \
            "$MYSQL_DB" "$table" > "$temp_file" 2>/dev/null
    else
        mysqldump --socket="$MYSQL_SOCKET" -u "$MYSQL_USER" \
            --no-create-info \
            --complete-insert \
            --extended-insert=FALSE \
            --single-transaction \
            "$MYSQL_DB" "$table" > "$temp_file" 2>/dev/null
    fi

    if [ $? -ne 0 ]; then
        echo -e "${RED}      ❌ 導出失敗${NC}"
        return 1
    fi

    # 轉換MySQL語法為PostgreSQL
    local converted_file="converted_${temp_file}"

    # 基本語法轉換
    sed -e 's/`//g' \
        -e 's/\\r\\n/\\n/g' \
        -e 's/\\0/\\\\0/g' \
        -e "s/INSERT INTO $table VALUES/INSERT INTO public.$table VALUES/g" \
        "$temp_file" > "$converted_file"

    # 導入到Supabase
    echo "      📥 導入數據到Supabase..."

    if PGPASSWORD="$SUPABASE_PASS" psql -h "$SUPABASE_HOST" -p "$SUPABASE_PORT" -U "$SUPABASE_USER" -d postgres -f "$converted_file" &>/dev/null; then
        # 檢查目標表數據量
        local target_count=$(PGPASSWORD="$SUPABASE_PASS" psql -h "$SUPABASE_HOST" -p "$SUPABASE_PORT" -U "$SUPABASE_USER" -d postgres -t -c "SELECT COUNT(*) FROM public.$table;" 2>/dev/null | tr -d ' ')

        echo -e "${GREEN}      ✅ 成功！目標數據: $target_count 條記錄${NC}"

        # 清理臨時文件
        rm -f "$temp_file" "$converted_file"

        return 0
    else
        echo -e "${RED}      ❌ 導入失敗${NC}"
        echo "      📄 臨時文件保留: $temp_file, $converted_file"
        return 1
    fi
}

# 主要遷移流程
echo ""
echo "📊 開始數據遷移..."

# 遷移核心表
echo ""
echo "🔴 遷移核心數據表..."
for table in "${CORE_TABLES[@]}"; do
    migrate_table_data "$table"
done

# 遷移重要表
echo ""
echo "🟡 遷移重要數據表..."
for table in "${IMPORTANT_TABLES[@]}"; do
    migrate_table_data "$table"
done

# 詢問是否遷移可選表
echo ""
read -p "🟢 是否遷移可選表（jobs, failed_jobs, password_resets）？(y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "🟢 遷移可選數據表..."
    for table in "${OPTIONAL_TABLES[@]}"; do
        migrate_table_data "$table"
    done
fi

# 最終驗證
echo ""
echo "🔍 最終數據驗證..."
echo "表名                 | MySQL數據 | Supabase數據 | 狀態"
echo "-------------------|----------|-------------|----"

all_tables=("${CORE_TABLES[@]}" "${IMPORTANT_TABLES[@]}" "${OPTIONAL_TABLES[@]}")

for table in "${all_tables[@]}"; do
    mysql_count=$(check_table_data "$table" 2>/dev/null || echo "0")
    supabase_count=$(PGPASSWORD="$SUPABASE_PASS" psql -h "$SUPABASE_HOST" -p "$SUPABASE_PORT" -U "$SUPABASE_USER" -d postgres -t -c "SELECT COUNT(*) FROM public.$table;" 2>/dev/null | tr -d ' ' || echo "0")

    printf "%-18s | %8s | %11s | " "$table" "$mysql_count" "$supabase_count"

    if [ "$mysql_count" = "$supabase_count" ] && [ "$mysql_count" != "0" ]; then
        echo -e "${GREEN}✅ 完成${NC}"
    elif [ "$mysql_count" = "0" ] && [ "$supabase_count" = "0" ]; then
        echo -e "${YELLOW}⚪ 無數據${NC}"
    else
        echo -e "${RED}❌ 不匹配${NC}"
    fi
done

echo ""
echo "🎉 數據遷移完成！"
echo ""
echo "📝 下一步："
echo "1. 測試應用程序功能"
echo "2. 驗證數據完整性"
echo "3. 更新Laravel配置指向Supabase"
echo "4. 備份原始MySQL數據"
