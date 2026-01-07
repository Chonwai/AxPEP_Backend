# Docker 網絡連接問題修復指南

## 📋 問題描述

在 Docker 容器化環境中，Laravel 後端無法訪問宿主機上運行的 Python 微服務（AmPEP, BESTox 等）。

### 錯誤日志示例
```
cURL error 28: Failed to connect to 172.17.0.1 port 8001 after 134938 ms: 
Could not connect to server
```

---

## 🔍 根本原因分析（第一性原理）

### 問題核心
**Docker 容器有獨立的網絡命名空間，與宿主機網絡隔離。**

### 網絡地址含義對比

| IP 地址 | 實際含義 | 容器內訪問 | 宿主機訪問 |
|---------|---------|-----------|-----------|
| **127.0.0.1** | Loopback（迴環地址） | ❌ 只能訪問容器內服務 | ✅ 訪問宿主機服務 |
| **172.17.0.1** | Docker bridge 網關 | ❌ 訪問的是網關，非宿主機 | ✅ 可訪問（但意義不大） |
| **host.docker.internal** | 特殊 DNS 映射到宿主機 | ✅ **正確方式** | ✅ 也可用 |

### 為什麼宿主機能訪問但容器不能？

```
宿主機上執行：
curl http://127.0.0.1:8001  ✅ 成功
curl http://172.17.0.1:8001 ✅ 成功（bridge 網關轉發）

Docker 容器內：
curl http://127.0.0.1:8001        ❌ 訪問容器自己的 8001 端口（不存在）
curl http://172.17.0.1:8001       ❌ 訪問 Docker 網關（無服務監聽）
curl http://host.docker.internal:8001 ✅ 成功訪問宿主機服務
```

---

## ✅ 解決方案

### 1. 配置文件修復（已完成）

#### 修改 `config/services.php`
```php
// ❌ 錯誤配置
'ampep' => [
    'url' => env('AMPEP_MICROSERVICE_BASE_URL', 'http://172.17.0.1:8001'),
],

// ✅ 正確配置
'ampep' => [
    'url' => env('AMPEP_MICROSERVICE_BASE_URL', 'http://host.docker.internal:8001'),
],
```

**影響的微服務：**
- AmPEP (8001)
- DeepAmPEP30 (8002)
- BESTox (8006)
- SSL-GCN (8007)
- AMP Regression (8889)
- BERT-HemoPep60 (9001)

#### 修改 `.env` 文件
```bash
# ❌ 錯誤
AMPEP_MICROSERVICE_BASE_URL="http://127.0.0.1:8001"

# ✅ 正確
AMPEP_MICROSERVICE_BASE_URL="http://host.docker.internal:8001"
```

---

## 🚀 生產環境部署步驟

### 步驟 1: 備份現有配置
```bash
cd ~/AxPEP_Backend
cp .env .env.backup.$(date +%Y%m%d_%H%M%S)
cp config/services.php config/services.php.backup
```

### 步驟 2: 更新配置文件

**方案 A：使用 Git 拉取（推薦）**
```bash
git pull origin main
```

**方案 B：手動編輯（如果不用 Git）**
```bash
# 編輯 .env 文件
nano .env

# 將所有微服務 URL 從 127.0.0.1 或 172.17.0.1 改為 host.docker.internal
# 例如：
AMPEP_MICROSERVICE_BASE_URL="http://host.docker.internal:8001"
DEEPAMPEP30_MICROSERVICE_BASE_URL="http://host.docker.internal:8002"
BESTOX_MICROSERVICE_BASE_URL="http://host.docker.internal:8006"
SSL_BESTOX_MICROSERVICE_BASE_URL="http://host.docker.internal:8007"
AMP_REGRESSION_EC_SA_PREDICT_BASE_URL=http://host.docker.internal:8889
```

### 步驟 3: 清除 Laravel 配置緩存
```bash
# 進入容器
docker exec -it axpep-app bash

# 清除緩存
php artisan config:clear
php artisan cache:clear

# 重新生成配置緩存
php artisan config:cache

# 退出容器
exit
```

### 步驟 4: 重啟 Docker 容器
```bash
# 方案 A：重啟所有容器（推薦）
docker compose -f docker/docker-compose.yml restart

# 方案 B：僅重啟應用和 Worker
docker restart axpep-app axpep-worker
```

### 步驟 5: 驗證修復結果
```bash
# 檢查容器內能否訪問微服務
docker exec -it axpep-app bash
curl http://host.docker.internal:8001/health
curl http://host.docker.internal:8002/health
exit

# 查看實時日誌
docker logs -f axpep-worker
```

---

## 🧪 測試驗證

### 1. 健康檢查測試
```bash
# 從容器內測試
docker exec axpep-app curl -s http://host.docker.internal:8001/health | jq
docker exec axpep-app curl -s http://host.docker.internal:8002/health | jq
docker exec axpep-app curl -s http://host.docker.internal:8006/health | jq
```

### 2. 提交測試任務
通過 API 提交一個 AmPEP 預測任務，檢查日誌：
```bash
# 監控 Worker 日誌
docker logs -f --tail 100 axpep-worker

# 應該看到：
# [INFO] 嘗試使用AmPEP微服務，TaskID: xxx
# [INFO] 開始調用AmPEP微服務，TaskID: xxx
# [INFO] AmPEP微服務預測完成，TaskID: xxx ✅
```

---

## 🔧 進階配置說明

### docker-compose.yml 中的關鍵配置

```yaml
services:
  app:
    extra_hosts:
      - "host.docker.internal:host-gateway"  # 關鍵配置！
```

**解釋：**
- `host-gateway` 是特殊值，Docker 會自動解析為宿主機的 IP
- 這行配置將 `host.docker.internal` 添加到容器的 `/etc/hosts` 文件
- 相當於：`172.17.0.1  host.docker.internal` 在 Linux 系統上

### 為什麼需要 extra_hosts？

| 操作系統 | 默認支持 | 是否需要 extra_hosts |
|---------|---------|---------------------|
| **Docker Desktop (Mac)** | ✅ 內建支持 | ❌ 不需要 |
| **Docker Desktop (Windows)** | ✅ 內建支持 | ❌ 不需要 |
| **Linux** | ❌ 無內建支持 | ✅ **必須配置** |

---

## 🐛 故障排查

### 問題 1: 仍然連接失敗

**檢查清單：**
```bash
# 1. 確認配置已生效
docker exec axpep-app php artisan config:show | grep AMPEP

# 2. 檢查 /etc/hosts
docker exec axpep-app cat /etc/hosts | grep host.docker.internal

# 3. 測試 DNS 解析
docker exec axpep-app ping -c 2 host.docker.internal

# 4. 檢查微服務是否運行
curl http://127.0.0.1:8001/health
```

### 問題 2: host.docker.internal 無法解析

**原因：** docker-compose.yml 沒有 extra_hosts 配置

**解決：**
```yaml
# 在 docker/docker-compose.yml 中添加（所有需要訪問宿主機的服務）
services:
  app:
    extra_hosts:
      - "host.docker.internal:host-gateway"
  
  queue-worker:
    extra_hosts:
      - "host.docker.internal:host-gateway"
```

### 問題 3: 微服務監聽地址問題

**症狀：** 宿主機用 `127.0.0.1:8001` 能訪問，但 Docker 容器無法訪問

**原因：** Python 微服務只監聽 `127.0.0.1`，需要改為監聽 `0.0.0.0`

**檢查方法：**
```bash
# 查看端口監聽
netstat -tlnp | grep 8001

# 應該看到：
# tcp  0.0.0.0:8001  LISTEN  ✅ 正確（所有接口）
# tcp  127.0.0.1:8001  LISTEN  ❌ 錯誤（僅本地）
```

**修復：** 在 Python 微服務啟動時指定 host
```python
# Flask 示例
app.run(host='0.0.0.0', port=8001)

# FastAPI 示例
uvicorn.run(app, host='0.0.0.0', port=8001)
```

---

## 📊 配置對比總結

### 修復前
```bash
# config/services.php
'ampep' => ['url' => env('...', 'http://172.17.0.1:8001')]

# .env
AMPEP_MICROSERVICE_BASE_URL="http://127.0.0.1:8001"

# 結果
容器內 → 172.17.0.1:8001 → ❌ 連接失敗
```

### 修復後
```bash
# config/services.php
'ampep' => ['url' => env('...', 'http://host.docker.internal:8001')]

# .env
AMPEP_MICROSERVICE_BASE_URL="http://host.docker.internal:8001"

# docker-compose.yml
extra_hosts: ["host.docker.internal:host-gateway"]

# 結果
容器內 → host.docker.internal:8001 → 宿主機服務 → ✅ 成功
```

---

## 🎯 最佳實踐建議

1. **統一配置管理**
   - 所有微服務 URL 都使用環境變量
   - config/services.php 提供合理的默認值
   - 生產環境通過 .env 覆蓋

2. **網絡配置標準化**
   - 容器訪問宿主機：`host.docker.internal`
   - 容器間通信：使用 Docker 服務名（如 `redis:6379`）
   - 外部訪問：使用端口映射

3. **健康檢查機制**
   - 在每個微服務實現 `/health` 端點
   - Docker Compose 配置 healthcheck
   - 應用啟動時檢查依賴服務

4. **日誌監控**
   - 記錄微服務調用的 URL
   - 記錄連接失敗的詳細信息
   - 使用結構化日誌便於分析

---

## 📚 相關資源

- [Docker 網絡官方文檔](https://docs.docker.com/network/)
- [host.docker.internal 說明](https://docs.docker.com/desktop/networking/#i-want-to-connect-from-a-container-to-a-service-on-the-host)
- [Laravel 服務容器](https://laravel.com/docs/8.x/container)

---

**修復日期：** 2026-01-07  
**修復人員：** Technical Lead  
**影響範圍：** 所有微服務 HTTP 連接
