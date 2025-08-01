# 外部數據庫配置指南

## 📋 概述

本地開發環境已配置為連接外部MySQL數據庫，而不是在Docker容器中運行MySQL服務。這提供了以下優勢：

- ✅ **資源效率**：減少本地Docker資源使用
- ✅ **數據持久性**：使用生產級數據庫環境
- ✅ **真實環境模擬**：更接近生產部署方式
- ✅ **統一數據源**：可與團隊共享同一數據庫

## 🔧 配置步驟

### 1. 準備外部MySQL數據庫

您需要一個可訪問的MySQL數據庫，可以是：
- 本地安裝的MySQL服務
- 雲端數據庫服務（AWS RDS、Google Cloud SQL等）
- 遠程服務器上的MySQL
- 已有的開發/測試數據庫

### 2. 獲取數據庫連接信息

確保您有以下信息：
```
主機地址 (Host)：       例如 localhost、192.168.1.100、db.example.com
端口 (Port)：          通常是 3306
數據庫名 (Database)：   例如 axpep_dev、axpep_test
用戶名 (Username)：     例如 axpep_user
密碼 (Password)：       對應用戶的密碼
```

### 3. 配置.env.local文件

編輯項目根目錄的`.env.local`文件：

```bash
# 外部MySQL數據庫配置
DB_CONNECTION=mysql
DB_HOST=your_actual_host           # 替換為實際主機地址
DB_PORT=3306                       # 根據需要調整端口
DB_DATABASE=your_database_name     # 替換為實際數據庫名
DB_USERNAME=your_username          # 替換為實際用戶名
DB_PASSWORD=your_password          # 替換為實際密碼
```

### 4. 確保網絡連通性

確保Docker容器能夠訪問外部數據庫：

#### 本地MySQL (同一台機器)
```bash
# 使用host.docker.internal（Mac/Windows）
DB_HOST=host.docker.internal

# 或使用實際IP地址
DB_HOST=192.168.1.xxx
```

#### 遠程MySQL
```bash
# 直接使用外部IP或域名
DB_HOST=your-remote-db-server.com
DB_HOST=123.456.789.101
```

#### 防火牆設置
確保外部數據庫服務器允許來自您IP的連接（通常是3306端口）。

### 5. 測試連接

使用啟動腳本測試連接：
```bash
./start-local.sh
```

或手動測試：
```bash
# 啟動容器
docker compose -f docker/docker-compose.local.yml up -d

# 測試數據庫連接
docker compose -f docker/docker-compose.local.yml exec app php artisan migrate:status
```

## 🛠️ 常見問題排除

### 連接被拒絕 (Connection refused)
```
SQLSTATE[HY000] [2002] Connection refused
```

**解決方案：**
1. 檢查數據庫服務是否運行
2. 確認主機地址和端口正確
3. 檢查防火牆設置

### 拒絕訪問 (Access denied)
```
SQLSTATE[HY000] [1045] Access denied for user
```

**解決方案：**
1. 確認用戶名和密碼正確
2. 確保數據庫用戶有相應權限
3. 檢查用戶是否允許從您的IP連接

### 數據庫不存在
```
SQLSTATE[HY000] [1049] Unknown database
```

**解決方案：**
1. 創建對應的數據庫
2. 確認數據庫名稱拼寫正確

### 網絡超時
```
SQLSTATE[HY000] [2002] Operation timed out
```

**解決方案：**
1. 檢查網絡連通性：`ping your_db_host`
2. 確認防火牆允許3306端口
3. 檢查數據庫服務器的bind-address設置

## 📝 配置範例

### 本地MySQL (macOS Homebrew)
```env
DB_HOST=host.docker.internal
DB_PORT=3306
DB_DATABASE=axpep_dev
DB_USERNAME=root
DB_PASSWORD=your_root_password
```

### AWS RDS
```env
DB_HOST=your-instance.random.region.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=axpep
DB_USERNAME=admin
DB_PASSWORD=your_secure_password
```

### Google Cloud SQL
```env
DB_HOST=your-project:region:instance-name
DB_PORT=3306
DB_DATABASE=axpep
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

### 本機MySQL (Linux/Ubuntu)
```env
DB_HOST=172.17.0.1    # Docker bridge gateway
DB_PORT=3306
DB_DATABASE=axpep_dev
DB_USERNAME=axpep_user
DB_PASSWORD=secure_password
```

## 🔍 驗證配置

配置完成後，執行以下命令驗證：

```bash
# 1. 測試所有服務
./test-local.sh

# 2. 查看數據庫遷移狀態
docker compose -f docker/docker-compose.local.yml exec app php artisan migrate:status

# 3. 手動執行遷移（如果需要）
docker compose -f docker/docker-compose.local.yml exec app php artisan migrate

# 4. 查看應用日誌
docker compose -f docker/docker-compose.local.yml logs app
```

## 🎯 生產環境準備

這種外部數據庫配置與生產環境部署方式一致，為將來的部署做好了準備：

- ✅ 應用容器與數據庫分離
- ✅ 通過環境變量配置連接
- ✅ 支持各種雲端數據庫服務
- ✅ 符合12-Factor App原則

## 📞 需要協助？

如果遇到連接問題：

1. **檢查連接配置**：使用MySQL客戶端工具測試連接
2. **查看詳細錯誤**：`docker compose -f docker/docker-compose.local.yml logs app`
3. **網絡診斷**：使用`telnet`或`nc`測試端口連通性
4. **權限檢查**：確保數據庫用戶有足夠權限

```bash
# 網絡連通性測試
telnet your_db_host 3306

# 或使用netcat
nc -zv your_db_host 3306
```
