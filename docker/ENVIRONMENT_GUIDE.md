# AxPEP Backend 環境配置指南

## 🎯 環境概覽

本專案支援兩種標準化的Docker環境：**開發環境(Development)** 和 **生產環境(Production)**，每個環境都經過專業優化以符合業界最佳實踐。

## 📚 環境對比表

| 特性 | 開發環境 | 生產環境 |
|------|----------|----------|
| **配置檔案** | `docker/docker-compose.local.yml` | `docker/docker-compose.yml` |
| **環境變數** | `.env.local` | `.env` |
| **Nginx配置** | `docker/nginx/nginx.conf` | `docker/nginx/nginx.prod.conf` |
| **端口暴露** | 8000 (HTTP), 6379 (Redis) | 80 (HTTP only) |
| **代碼掛載** | 完整代碼目錄 (即時更新) | 僅必要目錄 (性能優化) |
| **快取策略** | 開發模式 (短期快取) | 生產模式 (長期快取) |
| **日誌等級** | DEBUG | ERROR |
| **安全標頭** | 基本 | 完整安全標頭 |
| **資源限制** | 較寬鬆 | 嚴格限制 |
| **健康檢查** | 簡單 | 完整監控 |

## 🔧 開發環境 (Development)

### 啟動方式
```bash
./start-local.sh
```

### 特點
- **即時代碼更新**：掛載完整代碼目錄，修改立即生效
- **開放端口**：Redis對外暴露便於調試
- **寬鬆安全**：CORS允許所有來源，便於前端開發
- **詳細日誌**：DEBUG等級，完整錯誤資訊
- **快速重啟**：優化開發工作流程

### 服務結構
```
前端 → localhost:8000 → nginx容器 → app容器(PHP-FPM) → 外部DB
                                   ↓
                        redis容器 ← queue-worker容器
```

### 管理命令
```bash
# 查看狀態
docker compose -f docker/docker-compose.local.yml ps

# 查看日誌
docker compose -f docker/docker-compose.local.yml logs -f app

# 進入容器
docker compose -f docker/docker-compose.local.yml exec app bash

# 重啟服務
./reset-local.sh && ./start-local.sh
```

## 🚀 生產環境 (Production)

### 啟動方式
```bash
./start-production.sh
```

### 特點
- **性能優化**：僅掛載必要目錄，啟用所有快取
- **安全加固**：完整安全標頭，嚴格CORS政策
- **資源控制**：CPU和記憶體限制，防止資源耗盡
- **監控完備**：健康檢查、狀態端點、結構化日誌
- **高可用性**：自動重啟、依賴檢查

### 服務結構
```
前端 → localhost:80 → nginx容器 → app容器(PHP-FPM) → 外部DB
                               ↓
                    redis容器 ← queue-worker容器
```

### 管理命令
```bash
# 部署更新
./deploy-docker.sh

# 查看狀態
docker compose -f docker/docker-compose.yml ps

# 監控服務
curl http://localhost/health
curl http://localhost/nginx_status

# 查看資源使用
docker stats
```

## ⚙️ 配置檔案詳解

### 開發環境配置
```yaml
# docker/docker-compose.local.yml
volumes:
  - ../:/var/www/html              # 完整代碼掛載
  - ../.env.local:/var/www/html/.env
ports:
  - "8000:80"                      # HTTP
  - "6379:6379"                    # Redis (調試用)
```

### 生產環境配置
```yaml
# docker/docker-compose.yml
volumes:
  - ../storage:/var/www/html/storage  # 僅必要目錄
  - ../.env:/var/www/html/.env
ports:
  - "80:80"                          # 僅HTTP
healthcheck:                         # 健康檢查
  test: ["CMD", "php-fpm", "-t"]
deploy:                              # 資源限制
  resources:
    limits:
      memory: 2G
```

## 🔐 安全性差異

### 開發環境
- CORS: `*` (允許所有來源)
- 錯誤顯示: 詳細錯誤訊息
- 服務暴露: 多端口對外開放

### 生產環境
- CORS: 特定域名白名單
- 錯誤處理: 通用錯誤頁面
- 服務暴露: 僅HTTP端口
- 安全標頭: 完整CSP, XSS保護等

## 📊 監控和日誌

### 開發環境監控
```bash
# 即時日誌
docker compose -f docker/docker-compose.local.yml logs -f

# 容器狀態
docker compose -f docker/docker-compose.local.yml ps
```

### 生產環境監控
```bash
# 應用健康檢查
curl http://localhost/health

# Nginx狀態
curl http://localhost/nginx_status

# 資源監控
docker stats

# 結構化日誌
docker compose -f docker/docker-compose.yml logs --since 1h
```

## 🚀 部署工作流程

### 開發環境工作流程
1. `./start-local.sh` - 啟動開發環境
2. 修改代碼 - 即時生效
3. 測試功能 - http://localhost:8000
4. `./reset-local.sh` - 重置環境（如需要）

### 生產環境部署流程
1. 創建 `.env` 檔案（從 `docker/env.prod.example` 複製）
2. 配置生產環境變數
3. `./start-production.sh` - 初次部署
4. `./deploy-docker.sh` - 後續更新部署

## ⚡ 效能調優

### 開發環境
- 較小的容器限制
- 關閉不必要的快取
- 即時編譯和重載

### 生產環境
- 嚴格的資源限制
- 啟用所有層級快取
- Gzip壓縮
- 靜態資源長期快取
- Redis記憶體優化

## 🔧 疑難排解

### 常見問題

1. **端口衝突**
   - 開發環境: 修改 `docker-compose.local.yml` 中的端口
   - 生產環境: 修改 `docker-compose.yml` 中的端口

2. **權限問題**
   ```bash
   docker compose exec app chown -R www-data:www-data /var/www/html/storage
   ```

3. **快取清除**
   ```bash
   # 開發環境
   docker compose -f docker/docker-compose.local.yml exec app php artisan cache:clear
   
   # 生產環境
   docker compose -f docker/docker-compose.yml exec app php artisan config:cache
   ```

4. **數據庫連接**
   - 檢查環境變數檔案中的資料庫配置
   - 確認外部資料庫可達性

## 📝 最佳實踐

1. **永遠在開發環境測試**後才部署到生產環境
2. **定期備份**生產環境的持久化數據
3. **監控資源使用**，適時調整container限制
4. **保持環境變數安全**，使用密鑰管理系統
5. **定期更新**Docker映像和依賴項
