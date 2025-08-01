# AxPEP Backend 本地開發指南

## 🚀 快速開始

### 前置要求
- Docker Desktop for Mac
- 可選：Nginx (用於HTTP訪問)
- 可選：MySQL客戶端工具

### 一鍵啟動
```bash
./start-local.sh
```

### 一鍵測試
```bash
./test-local.sh
```

### 停止服務
```bash
./stop-local.sh
```

## 📋 詳細設置步驟

### 1. 環境配置
首次運行時，腳本會自動創建 `.env.local` 文件：
```bash
cp docker/env.local.example .env.local
```

### 2. 生成應用密鑰
```bash
docker compose -f docker/docker-compose.local.yml exec app php artisan key:generate
```

### 3. 啟動服務
```bash
docker compose -f docker/docker-compose.local.yml up -d
```

## 🌐 訪問服務

### 直接服務端口
- **PHP-FPM**: `localhost:9000` (需要通過FastCGI協議)
- **Redis**: `localhost:6379`
- **MySQL**: 外部數據庫 (請參考 EXTERNAL_DATABASE.md)

### 通過Nginx訪問API
1. 安裝Nginx：
   ```bash
   # macOS
   brew install nginx
   
   # Ubuntu/Debian
   sudo apt install nginx
   ```

2. 複製Nginx配置：
   ```bash
   # macOS (Homebrew)
   cp docker/nginx.local.conf /usr/local/etc/nginx/servers/axpep.conf
   
   # 修改配置中的root路徑為您的實際路徑
   # 然後重啟Nginx
   brew services restart nginx
   ```

3. 訪問應用：
   ```
   http://localhost:8000
   ```

## 🛠️ 開發工具

### 常用Docker命令
```bash
# 查看容器狀態
docker compose -f docker/docker-compose.local.yml ps

# 查看應用日誌
docker compose -f docker/docker-compose.local.yml logs -f app

# 查看隊列工作器日誌
docker compose -f docker/docker-compose.local.yml logs -f queue-worker

# 進入應用容器
docker compose -f docker/docker-compose.local.yml exec app bash

# 執行Artisan命令
docker compose -f docker/docker-compose.local.yml exec app php artisan [command]

# 執行Composer命令
docker compose -f docker/docker-compose.local.yml exec app composer [command]
```

### Laravel常用命令
```bash
# 查看路由列表
docker compose -f docker/docker-compose.local.yml exec app php artisan route:list

# 清除所有緩存
docker compose -f docker/docker-compose.local.yml exec app php artisan optimize:clear

# 查看隊列狀態
docker compose -f docker/docker-compose.local.yml exec app php artisan queue:failed

# 重置數據庫
docker compose -f docker/docker-compose.local.yml exec app php artisan migrate:fresh

# 填充測試數據
docker compose -f docker/docker-compose.local.yml exec app php artisan db:seed
```

## 🧪 測試API

### 使用Postman或cURL測試
```bash
# 測試基本API端點（需要先配置Nginx）
curl -X GET http://localhost:8000/api/health

# 測試任務API
curl -X POST http://localhost:8000/api/tasks \
  -H "Content-Type: application/json" \
  -d '{"name": "test_task"}'
```

### 直接測試PHP-FPM
如果您想跳過Nginx直接測試PHP-FPM，可以使用`cgi-fcgi`工具：
```bash
# 安裝fcgi
brew install fcgi  # macOS
sudo apt install libfcgi-dev  # Ubuntu

# 直接測試PHP-FPM
echo -e "SCRIPT_FILENAME=/var/www/html/public/index.php\nREQUEST_METHOD=GET\nREQUEST_URI=/\n" | cgi-fcgi -bind -connect localhost:9000
```

## 🗄️ 數據庫管理

### 外部數據庫配置
本環境使用外部MySQL數據庫，請參考 `EXTERNAL_DATABASE.md` 進行配置。

### MySQL CLI連接
```bash
# 使用您的實際連接信息
mysql -h your_external_host -P 3306 -u your_username -p your_database

# 或通過應用容器連接
docker compose -f docker/docker-compose.local.yml exec app php artisan tinker
```

### Laravel數據庫命令
```bash
# 測試數據庫連接
docker compose -f docker/docker-compose.local.yml exec app php artisan migrate:status

# 執行遷移
docker compose -f docker/docker-compose.local.yml exec app php artisan migrate

# 重置並重新運行遷移
docker compose -f docker/docker-compose.local.yml exec app php artisan migrate:fresh
```

## 🔧 故障排除

### 常見問題

1. **容器啟動失敗**
   ```bash
   # 查看詳細錯誤
   docker compose -f docker/docker-compose.local.yml logs
   
   # 重新建構映像
   docker compose -f docker/docker-compose.local.yml build --no-cache
   ```

2. **端口被占用**
   ```bash
   # 檢查端口使用情況
   lsof -i :9000
   lsof -i :3306
   lsof -i :6379
   
   # 修改docker-compose.local.yml中的端口映射
   ```

3. **權限問題**
   ```bash
   # 修復storage目錄權限
   docker compose -f docker/docker-compose.local.yml exec app chown -R www-data:www-data storage bootstrap/cache
   ```

4. **數據庫連接失敗**
   ```bash
   # 檢查數據庫是否就緒
   docker compose -f docker/docker-compose.local.yml exec mysql mysqladmin ping
   
   # 重新執行遷移
   docker compose -f docker/docker-compose.local.yml exec app php artisan migrate
   ```

### 重置環境
```bash
# 完全重置（會刪除所有數據）
./stop-local.sh
docker system prune -a
./start-local.sh
```

## 📂 檔案結構

```
AxPEP_Backend/
├── docker/
│   ├── docker-compose.local.yml    # 本地開發配置
│   ├── env.local.example          # 環境變數範例
│   └── nginx.local.conf           # 本地Nginx配置
├── start-local.sh                 # 啟動腳本
├── test-local.sh                  # 測試腳本
├── stop-local.sh                  # 停止腳本
└── .env.local                     # 本地環境配置
```

## 🚨 注意事項

1. **僅用於開發環境**：此配置不適用於生產環境
2. **資料持久性**：數據存儲在Docker卷中，除非使用 `-v` 選項刪除
3. **性能考量**：本地開發配置優先考慮便利性而非性能
4. **安全性**：使用默認密碼，不適用於生產環境

## 📞 需要幫助？

如果遇到問題：
1. 查看容器日誌：`docker compose -f docker/docker-compose.local.yml logs`
2. 執行測試腳本：`./test-local.sh`
3. 重置環境並重新開始
