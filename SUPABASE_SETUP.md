# Supabase PostgreSQL 配置指南

## 🎯 概述

本指南將幫助您配置AxPEP Backend以連接到Supabase PostgreSQL數據庫。

## 📋 前置要求

1. 有效的Supabase專案
2. Supabase數據庫密碼
3. 已完成Docker環境設置

## 🔧 配置步驟

### 方式一：使用自動配置腳本 (推薦)

```bash
./setup-supabase.sh
```

該腳本將：
- ✅ 自動配置PostgreSQL連接
- ✅ 提示輸入Supabase密碼
- ✅ 更新環境配置文件
- ✅ 可選擇立即啟動環境

### 方式二：手動配置

1. **編輯環境配置文件**：
   ```bash
   cp docker/env.local.example .env.local
   nano .env.local  # 或使用您喜歡的編輯器
   ```

2. **更新數據庫配置**：
   ```env
   # Supabase PostgreSQL配置
   DB_CONNECTION=pgsql
   DB_HOST=aws-0-ap-northeast-1.pooler.supabase.com
   DB_PORT=6543
   DB_DATABASE=postgres
   DB_USERNAME=postgres.mykbxfdbpdjaylcvgpbq
   DB_PASSWORD=your_actual_supabase_password
   ```

3. **重新啟動環境**：
   ```bash
   ./reset-local.sh
   ./start-local.sh
   ```

## 🔗 Supabase連接選項

### Transaction Pooler (推薦用於Docker)
```env
DB_HOST=aws-0-ap-northeast-1.pooler.supabase.com
DB_PORT=6543
DB_USERNAME=postgres.mykbxfdbpdjaylcvgpbq
```

**優點**：
- ✅ 適合短時間連接
- ✅ IPv4兼容
- ✅ 適合Docker容器環境
- ✅ 自動連接池管理

### Direct Connection
```env
DB_HOST=db.mykbxfdbpdjaylcvgpbq.supabase.co
DB_PORT=5432
DB_USERNAME=postgres
```

**優點**：
- ✅ 適合持久連接
- ✅ 支持所有PostgreSQL功能
- ❌ 需要IPv6網絡（或購買IPv4插件）

## 🛠️ 驗證配置

### 1. 檢查容器狀態
```bash
docker compose -f docker/docker-compose.local.yml ps
```

### 2. 測試數據庫連接
```bash
./test-local.sh
```

### 3. 手動測試遷移
```bash
docker compose -f docker/docker-compose.local.yml exec app php artisan migrate:status
```

### 4. 執行數據庫遷移
```bash
docker compose -f docker/docker-compose.local.yml exec app php artisan migrate
```

## 🔍 故障排除

### 連接被拒絕
```
could not find driver
```
**解決方案**：重新建構Docker映像
```bash
./reset-local.sh
./start-local.sh
```

### 認證失敗
```
FATAL: password authentication failed
```
**解決方案**：
1. 確認Supabase密碼正確
2. 檢查用戶名格式：`postgres.mykbxfdbpdjaylcvgpbq`

### 主機無法解析
```
could not translate host name
```
**解決方案**：
1. 檢查網絡連接
2. 確認主機名拼寫正確
3. 嘗試使用Direct Connection

### SSL連接問題
```
SSL connection error
```
**解決方案**：在.env.local中添加：
```env
DB_SSLMODE=require
```

## 📊 連接池設置

對於高並發應用，您可以調整連接池設置：

```env
# 在.env.local中添加
DB_MAX_CONNECTIONS=20
DB_POOL_SIZE=10
```

## 🚀 性能優化建議

1. **使用Transaction Pooler**：
   - 適合大多數Web應用
   - 自動處理連接池

2. **調整隊列配置**：
   ```env
   QUEUE_CONNECTION=redis
   REDIS_HOST=redis
   ```

3. **啟用查詢緩存**：
   ```env
   CACHE_DRIVER=redis
   ```

## 📝 Laravel遷移注意事項

Supabase使用PostgreSQL，某些Laravel遷移可能需要調整：

### 字符串長度
PostgreSQL對字符串長度處理與MySQL不同，如遇到問題，可在遷移中指定長度：
```php
$table->string('email', 191);
```

### 自增ID
PostgreSQL使用序列(sequences)，通常不需要調整，但如有自定義ID邏輯需要注意。

### JSON字段
PostgreSQL原生支持JSON，使用方式：
```php
$table->json('metadata');
```

## 🎯 生產環境配置

生產環境建議使用Direct Connection：
```env
DB_HOST=db.mykbxfdbpdjaylcvgpbq.supabase.co
DB_PORT=5432
DB_USERNAME=postgres
DB_SSLMODE=require
```

## 📞 需要協助？

如果遇到問題：

1. **檢查Supabase狀態**：https://status.supabase.com/
2. **查看詳細錯誤**：`docker compose -f docker/docker-compose.local.yml logs app`
3. **測試網絡連接**：`telnet aws-0-ap-northeast-1.pooler.supabase.com 6543`
4. **重置環境**：`./reset-local.sh && ./start-local.sh`

## 🔐 安全提醒

- ❌ 永遠不要將數據庫密碼提交到版本控制
- ✅ 使用環境變數管理敏感信息
- ✅ 定期輪換數據庫密碼
- ✅ 監控數據庫連接和使用情況
