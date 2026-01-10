# Docker 網絡問題 - 三種解決方案對比

## 🎯 問題本質（第一性原理）

你遇到的核心問題是：**Docker 容器的網絡隔離導致無法訪問宿主機服務**

### 為什麼 `host.docker.internal` 失敗了？

在 Linux 系統上，可能有以下原因：

1. **微服務只監聽 127.0.0.1**
   - Python 微服務可能配置為 `host='127.0.0.1'`
   - Docker 容器無法訪問宿主機的 `127.0.0.1`
   - 必須改為 `host='0.0.0.0'`

2. **host-gateway 解析問題**
   - Docker 版本 < 20.10 不支持 `host-gateway`
   - 某些 Linux 發行版的網絡配置問題

3. **防火牆或 iptables 規則**
   - 阻止了容器訪問宿主機端口

---

## 🔍 先執行診斷腳本

在服務器上執行以下命令來診斷問題：

```bash
cd ~/AxPEP_Backend
./scripts/diagnose-docker-network.sh > network-diagnosis.log 2>&1

# 查看診斷結果
cat network-diagnosis.log
```

這個腳本會檢查：
- Docker 版本和網絡配置
- 容器的網絡連接
- host.docker.internal 是否正確解析
- 微服務是否監聽在正確的地址
- 從容器內是否能訪問微服務

---

## 📊 三種解決方案對比

| 特性 | 方案 1: network_mode: host | 方案 2: 修復 host.docker.internal | 方案 3: 統一容器化 |
|------|---------------------------|----------------------------------|------------------|
| **實施難度** | ⭐ 最簡單 | ⭐⭐ 中等 | ⭐⭐⭐ 較複雜 |
| **網絡隔離** | ❌ 無隔離 | ✅ 有隔離 | ✅✅ 完全隔離 |
| **可移植性** | ⚠️ 依賴宿主機 | ⚠️ 依賴宿主機 | ✅ 完全可移植 |
| **擴展性** | ❌ 難以擴展 | ⚠️ 有限擴展 | ✅ 易於擴展 |
| **維護性** | ⚠️ 混合部署 | ⚠️ 混合部署 | ✅ 統一管理 |
| **推薦場景** | 快速修復 | 過渡方案 | **生產環境** |

---

## 方案 1: 使用 network_mode: host（最快速修復）

### 原理
容器直接使用宿主機的網絡棧，相當於在宿主機上直接運行。

### 優點
- ✅ **最簡單**：只需修改 docker-compose.yml
- ✅ **無需修改代碼**：可以直接訪問 `127.0.0.1:8001`
- ✅ **立即生效**：重啟容器即可

### 缺點
- ❌ **失去網絡隔離**：容器和宿主機共享網絡
- ❌ **端口衝突**：容器端口不能與宿主機衝突
- ❌ **安全性降低**：容器可訪問宿主機所有服務

### 實施步驟

#### 1. 修改 docker-compose.yml

```yaml
# docker/docker-compose.yml
services:
  queue-worker:
    build:
      context: ..
      dockerfile: docker/Dockerfile.worker
    container_name: axpep-worker
    restart: unless-stopped
    # 使用宿主機網絡模式
    network_mode: "host"
    volumes:
      - ../storage:/var/www/html/storage
      - ../.env:/var/www/html/.env
    environment:
      - REDIS_HOST=127.0.0.1  # 改為 127.0.0.1
      - APP_ENV=production
    # 移除 networks 和 extra_hosts（使用 host 模式時不需要）
```

#### 2. 修改 .env 文件

```bash
# 使用 127.0.0.1（因為共享宿主機網絡）
AMPEP_MICROSERVICE_BASE_URL="http://127.0.0.1:8001"
DEEPAMPEP30_MICROSERVICE_BASE_URL="http://127.0.0.1:8002"
BESTOX_MICROSERVICE_BASE_URL="http://127.0.0.1:8006"
SSL_BESTOX_MICROSERVICE_BASE_URL="http://127.0.0.1:8007"
REDIS_HOST=127.0.0.1
```

#### 3. 重啟容器

```bash
docker compose -f docker/docker-compose.yml down
docker compose -f docker/docker-compose.yml up -d queue-worker
```

### ⚠️ 注意事項

- **僅用於 queue-worker**：建議只對需要訪問微服務的容器使用 host 模式
- **app 和 nginx 可保持原配置**：它們不需要訪問微服務

---

## 方案 2: 修復 host.docker.internal（推薦過渡方案）

### 原理
確保微服務正確監聽，並修復容器內的 host.docker.internal 解析。

### 優點
- ✅ 保持網絡隔離
- ✅ 相對安全
- ✅ 配置清晰

### 缺點
- ⚠️ 需要修改微服務配置
- ⚠️ 依賴 Docker 版本
- ⚠️ 仍有宿主機依賴

### 實施步驟

#### 1. 確認微服務監聽地址

**關鍵問題**：Python 微服務必須監聽 `0.0.0.0` 而不是 `127.0.0.1`

檢查微服務配置：

```python
# ❌ 錯誤：僅監聽本地
app.run(host='127.0.0.1', port=8001)

# ✅ 正確：監聽所有接口
app.run(host='0.0.0.0', port=8001)
```

檢查實際監聽地址：

```bash
# 在服務器上執行
netstat -tlnp | grep 8001

# 應該看到：
# tcp  0.0.0.0:8001  LISTEN  ✅ 正確
# tcp  127.0.0.1:8001  LISTEN  ❌ 錯誤
```

#### 2. 如果微服務監聽錯誤，修復它們

對於 Flask/FastAPI 微服務：

```python
# Flask
if __name__ == '__main__':
    app.run(host='0.0.0.0', port=8001)

# FastAPI with uvicorn
uvicorn.run(app, host='0.0.0.0', port=8001)
```

#### 3. 驗證 host.docker.internal 解析

```bash
# 進入容器
docker exec -it axpep-worker bash

# 檢查 /etc/hosts
cat /etc/hosts | grep host.docker.internal
# 應該看到類似：
# 172.17.0.1  host.docker.internal

# 測試連接
curl http://host.docker.internal:8001/health
```

#### 4. 如果解析失敗，手動添加 IP

找到 Docker 網關 IP：

```bash
docker network inspect axpep-network | grep Gateway
```

然後在 docker-compose.yml 中明確指定：

```yaml
extra_hosts:
  - "host.docker.internal:172.17.0.1"  # 使用實際的網關 IP
```

---

## 方案 3: 統一容器化（最佳生產方案）✅

### 原理
將所有微服務也放入 Docker 容器，在同一個 Docker 網絡中通信。

### 優點
- ✅ **完全容器化**：統一管理
- ✅ **服務發現**：使用服務名稱通信
- ✅ **易於擴展**：可以橫向擴展微服務
- ✅ **環境一致**：開發/生產環境完全一致
- ✅ **網絡隔離**：安全性最高

### 缺點
- ⚠️ 實施複雜度較高
- ⚠️ 需要為微服務創建 Dockerfile

### 實施步驟

#### 1. 為微服務創建 Dockerfile

```dockerfile
# 例如：AmPEP 微服務的 Dockerfile
FROM python:3.9-slim

WORKDIR /app

# 安裝依賴
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# 複製代碼
COPY . .

# 暴露端口
EXPOSE 8001

# 啟動服務（監聽 0.0.0.0）
CMD ["python", "app.py"]
```

#### 2. 擴展 docker-compose.yml

```yaml
# docker/docker-compose.yml
services:
  # 現有服務...
  app:
    # ...保持不變
  
  nginx:
    # ...保持不變
  
  queue-worker:
    # ...保持不變
    # 移除 extra_hosts，使用服務名稱
  
  redis:
    # ...保持不變
  
  # 新增微服務
  ampep-service:
    build:
      context: ../AmPEP  # 微服務代碼路徑
      dockerfile: Dockerfile
    container_name: ampep-service
    restart: unless-stopped
    networks:
      - axpep-network
    ports:
      - "8001:8001"  # 如果需要從外部訪問
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8001/health"]
      interval: 30s
      timeout: 10s
      retries: 3
  
  deepampep30-service:
    build:
      context: ../DeepAmPEP30
      dockerfile: Dockerfile
    container_name: deepampep30-service
    restart: unless-stopped
    networks:
      - axpep-network
    ports:
      - "8002:8002"
  
  bestox-service:
    build:
      context: ../BESTox
      dockerfile: Dockerfile
    container_name: bestox-service
    restart: unless-stopped
    networks:
      - axpep-network
    ports:
      - "8006:8006"

networks:
  axpep-network:
    driver: bridge
```

#### 3. 修改 .env 配置

```bash
# 使用 Docker 服務名稱（不需要 host.docker.internal）
AMPEP_MICROSERVICE_BASE_URL="http://ampep-service:8001"
DEEPAMPEP30_MICROSERVICE_BASE_URL="http://deepampep30-service:8002"
BESTOX_MICROSERVICE_BASE_URL="http://bestox-service:8006"
SSL_BESTOX_MICROSERVICE_BASE_URL="http://ssl-gcn-service:8007"
```

#### 4. 啟動所有服務

```bash
docker compose -f docker/docker-compose.yml up -d
```

---

## 🎯 推薦實施路線

### 階段 1: 緊急修復（今天）
使用**方案 1**快速恢復服務：
```bash
# 修改 docker-compose.yml，queue-worker 使用 network_mode: host
# 修改 .env，微服務 URL 改為 127.0.0.1
# 重啟容器
```

### 階段 2: 短期優化（本週）
實施**方案 2**，確保配置正確：
```bash
# 修改微服務監聽 0.0.0.0
# 驗證 host.docker.internal 解析
# 改回 bridge 網絡模式
```

### 階段 3: 長期架構（下個版本）
實施**方案 3**，完全容器化：
```bash
# 為所有微服務創建 Dockerfile
# 統一管理在 docker-compose.yml
# 使用服務發現機制
```

---

## 🔧 立即可用的配置文件

我已經為你準備了三個版本的配置：

1. **docker-compose.host-network.yml** - 方案 1
2. **docker-compose.fixed-gateway.yml** - 方案 2
3. **docker-compose.full-containerized.yml** - 方案 3

選擇其中一個複製為 `docker/docker-compose.yml` 即可使用。

---

## 📝 調試命令速查

```bash
# 1. 診斷腳本
./scripts/diagnose-docker-network.sh

# 2. 檢查微服務監聽地址
netstat -tlnp | grep 800

# 3. 測試容器內連接
docker exec axpep-worker curl -v http://host.docker.internal:8001/health

# 4. 查看容器網絡
docker inspect axpep-worker | grep -A 20 "Networks"

# 5. 查看日誌
docker logs axpep-worker --tail 100 | grep ERROR
```

---

## ✅ 下一步行動

1. **立即執行診斷**
   ```bash
   ./scripts/diagnose-docker-network.sh
   ```

2. **根據診斷結果選擇方案**
   - 如果急需恢復 → 方案 1
   - 如果有時間調試 → 方案 2
   - 如果重構架構 → 方案 3

3. **驗證修復**
   ```bash
   # 提交測試任務
   # 監控日誌
   docker logs -f axpep-worker
   ```

選擇哪個方案取決於你的優先級：速度 vs 架構優雅 vs 長期維護。
