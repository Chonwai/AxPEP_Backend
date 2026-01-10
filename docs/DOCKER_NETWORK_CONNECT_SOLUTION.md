# Docker 網絡連接解決方案

## 📋 問題概述

### 問題現象
```
cURL error 6: Could not resolve host: docker-ampep-microservice-1
```

### 根本原因
經過第一性原理分析，問題本質是：
- **所有微服務都已經是容器化的**（不是運行在宿主機上）
- axpep-worker 在 `docker_axpep-network` 網絡
- 各微服務在各自獨立的網絡中
- Docker 的網絡隔離機制阻止了跨網絡的 DNS 解析

### 解決方案
**將所有微服務容器連接到 `docker_axpep-network`**，使它們可以通過容器名互相訪問。

## 🎯 優勢

✅ **不修改任何 docker-compose.yml**  
✅ **不破壞現有架構**  
✅ **運行時動態連接，立即生效**  
✅ **可隨時回退**  
✅ **容器間直接通信，性能最優**  

## 🚀 快速部署

### 第一步：Push 到 GitHub

```bash
cd ~/AxPEP_Backend

git add scripts/connect-microservices-to-network.sh \
        scripts/disconnect-microservices-from-network.sh \
        scripts/update-env-for-container-names.sh \
        docs/DOCKER_NETWORK_CONNECT_SOLUTION.md

git commit -m "feat: add dynamic network connection solution

- Connect microservices to axpep-network without modifying compose files
- Use docker network connect for runtime configuration
- Maintain existing architecture integrity
- Add rollback capability"

git push origin main
```

### 第二步：在服務器上部署

```bash
# 1. 拉取代碼
cd ~/AxPEP_Backend
git pull origin main

# 2. 賦予執行權限
chmod +x scripts/connect-microservices-to-network.sh
chmod +x scripts/disconnect-microservices-from-network.sh
chmod +x scripts/update-env-for-container-names.sh

# 3. 連接所有微服務到網絡（一鍵完成）
bash scripts/connect-microservices-to-network.sh

# 4. 確認 .env 配置正確（如果需要）
bash scripts/update-env-for-container-names.sh

# 5. 重啟 worker 使配置生效
docker restart axpep-worker

# 6. 查看日誌驗證
docker logs -f axpep-worker
```

## 📊 腳本功能說明

### 1. `connect-microservices-to-network.sh`
**主要功能：**
- 自動發現所有微服務容器
- 檢查容器運行狀態
- 檢查是否已連接（冪等性）
- 逐個連接到 docker_axpep-network
- 驗證 DNS 解析
- 測試 HTTP 連接
- 顯示網絡拓撲

**執行效果：**
```
✓ docker-ampep-microservice-1 已連接
✓ deep-ampep30 已連接
✓ bestox-api-service 已連接
✓ ssl-gcn-toxicity-prediction 已連接
✓ DNS 解析成功
✓ HTTP 連接測試通過
```

### 2. `disconnect-microservices-from-network.sh`
**回退工具：**
- 斷開所有微服務與 axpep-network 的連接
- 恢復到原始網絡配置
- 需要確認操作（防止誤操作）

**使用場景：**
- 需要回退到原始配置
- 測試其他解決方案
- 排查網絡問題

### 3. `update-env-for-container-names.sh`
**配置管理：**
- 自動備份當前 .env
- 更新所有微服務 URL 為容器名
- 驗證配置完整性
- 顯示變更對比

**更新內容：**
```env
AMPEP_MICROSERVICE_BASE_URL="http://docker-ampep-microservice-1:8001"
DEEPAMPEP30_MICROSERVICE_BASE_URL="http://deep-ampep30:8002"
BESTOX_MICROSERVICE_BASE_URL="http://bestox-api-service:8006"
SSL_BESTOX_MICROSERVICE_BASE_URL="http://ssl-gcn-toxicity-prediction:8007"
AMP_REGRESSION_MICROSERVICE_BASE_URL="http://amp_regression_ec_sa_fastapi-amp-regression-predict-flask-1:8888"
```

## 🏗️ 架構說明

### 網絡拓撲（部署後）

```
docker_axpep-network (bridge)
├─ axpep-app
├─ axpep-worker
├─ axpep-nginx
├─ axpep-redis
└─ [新連接的微服務] ↓
    ├─ docker-ampep-microservice-1 (同時在 docker_ampep-network)
    ├─ deep-ampep30 (同時在 docker_default)
    ├─ bestox-api-service (同時在 bestox-network)
    ├─ ssl-gcn-toxicity-prediction (同時在 docker_ssl-gcn-network)
    └─ amp_regression_ec_sa_fastapi-amp-regression-predict-flask-1 (同時在 amp_regression_ec_sa_fastapi_default)
```

### 關鍵特性

1. **多網絡連接**
   - 一個容器可以連接多個網絡
   - 微服務保留原有網絡（不影響原有功能）
   - 同時加入 axpep-network（提供新的訪問路徑）

2. **DNS 解析機制**
   - Docker 內建 DNS 服務器（127.0.0.11）
   - 同一網絡的容器可以通過容器名互相解析
   - 解析結果是容器在該網絡中的 IP 地址

3. **性能優勢**
   - 容器間直接通信，不經過宿主機
   - 沒有端口映射開銷
   - 使用 Linux bridge，性能接近原生網絡

## 🔍 驗證步驟

### 1. 檢查網絡連接
```bash
# 查看 axpep-network 中的所有容器
docker network inspect docker_axpep-network --format '{{range .Containers}}{{.Name}}: {{.IPv4Address}}{{"\n"}}{{end}}'
```

### 2. 測試 DNS 解析
```bash
# 在 worker 容器內測試
docker exec axpep-worker getent hosts docker-ampep-microservice-1
docker exec axpep-worker getent hosts deep-ampep30
docker exec axpep-worker getent hosts bestox-api-service
```

### 3. 測試 HTTP 連接
```bash
# 測試微服務端點
docker exec axpep-worker curl -v http://docker-ampep-microservice-1:8001/health
docker exec axpep-worker curl -v http://deep-ampep30:8002/health
```

### 4. 查看應用日誌
```bash
# 提交測試任務後查看日誌
docker logs -f axpep-worker | grep "微服務"
```

**成功標誌：**
```
production.INFO: 開始調用AmPEP微服務，TaskID: xxx
production.INFO: AmPEP微服務調用成功，TaskID: xxx
```

## ⚠️ 注意事項

### 1. 容器重啟後的行為
- ✅ 網絡連接會**保持**（持久化到容器配置）
- ✅ 容器重啟後自動重新加入網絡
- ✅ 不需要重新執行腳本

### 2. 新增微服務
如果將來添加新的微服務容器，需要：
```bash
# 手動連接新容器
docker network connect docker_axpep-network <新容器名>

# 或重新執行連接腳本（冪等的）
bash scripts/connect-microservices-to-network.sh
```

### 3. 微服務重新創建
如果微服務容器被刪除並重新創建：
```bash
# 重新執行連接腳本
bash scripts/connect-microservices-to-network.sh
```

### 4. 安全考量
- 連接到同一網絡後，所有容器可以互相訪問
- 確保微服務有適當的認證機制
- 考慮使用防火牆規則限制不必要的連接

## 🆘 故障排查

### 問題：DNS 解析仍然失敗
```bash
# 1. 檢查容器是否真的連接到網絡
docker inspect axpep-worker --format '{{range $net, $conf := .NetworkSettings.Networks}}{{$net}}{{"\n"}}{{end}}'

# 2. 檢查微服務容器是否連接到網絡
docker inspect docker-ampep-microservice-1 --format '{{range $net, $conf := .NetworkSettings.Networks}}{{$net}}{{"\n"}}{{end}}'

# 3. 重啟 Docker DNS
docker network disconnect docker_axpep-network axpep-worker
docker network connect docker_axpep-network axpep-worker
```

### 問題：連接超時
```bash
# 1. 檢查微服務是否真的在監聽
docker exec docker-ampep-microservice-1 netstat -tlnp | grep 8001

# 2. 測試從 worker 到微服務的網絡連通性
docker exec axpep-worker ping -c 3 docker-ampep-microservice-1

# 3. 檢查防火牆規則
sudo iptables -L DOCKER -n -v
```

### 問題：需要完全回退
```bash
# 1. 斷開所有微服務
bash scripts/disconnect-microservices-from-network.sh

# 2. 恢復 .env（從備份）
cp backups/env_update_YYYYMMDD_HHMMSS/.env .env

# 3. 重啟 worker
docker restart axpep-worker
```

## 📚 技術原理

### Docker 網絡基礎
1. **Bridge 網絡**
   - 默認網絡類型
   - 使用 Linux bridge 設備
   - 容器通過 veth pair 連接到 bridge

2. **DNS 服務**
   - Docker 內建 DNS 服務器（127.0.0.11）
   - 解析同網絡容器名到 IP
   - 支持服務發現

3. **多網絡連接**
   - 容器可以連接多個網絡
   - 每個網絡分配一個 IP
   - 優先使用第一個連接的網絡

### 與其他方案的對比

| 方案 | 優點 | 缺點 | 適用場景 |
|------|------|------|----------|
| **網絡連接** | 不修改配置、性能最優、可回退 | 需要手動連接 | ✅ 生產環境 |
| Host Network | 最簡單、立即生效 | 失去隔離、安全風險 | 緊急修復 |
| External Network | 聲明式配置 | 需要修改 compose、可能失效 | 新項目 |

## 🎓 設計模式應用

此解決方案遵循以下原則（不過度設計）：

1. **單一職責原則（SRP）**
   - 每個腳本專注一個功能
   - connect：連接網絡
   - disconnect：斷開網絡
   - update-env：更新配置

2. **開閉原則（OCP）**
   - 對擴展開放：容易添加新微服務
   - 對修改封閉：不修改現有架構

3. **最小知識原則（Law of Demeter）**
   - 腳本只操作必要的對象
   - 不深入容器內部實現

4. **KISS 原則**
   - 保持簡單，不過度設計
   - 沒有使用複雜的 Design Pattern
   - 直接解決問題

## 📈 性能指標

### 預期改善
- DNS 解析延遲：< 1ms（本地解析）
- 網絡延遲：< 0.1ms（容器間直連）
- 吞吐量：接近宿主機原生網絡

### 監控建議
```bash
# 查看網絡統計
docker stats --no-stream

# 測試延遲
docker exec axpep-worker time curl -s http://docker-ampep-microservice-1:8001/health
```

## 🔄 持續集成建議

將網絡連接集成到部署流程：

```bash
# deploy.sh
#!/bin/bash

# 1. 拉取最新代碼
git pull origin main

# 2. 構建鏡像
docker compose build

# 3. 啟動服務
docker compose up -d

# 4. 連接微服務網絡
bash scripts/connect-microservices-to-network.sh

# 5. 驗證健康狀態
bash scripts/health-check.sh
```

## 📞 支持

遇到問題？查看日誌：
```bash
# 應用日誌
docker logs axpep-worker --tail 100

# 網絡診斷
bash scripts/diagnose-docker-network.sh

# 完整診斷報告
docker network inspect docker_axpep-network
docker inspect axpep-worker
docker inspect docker-ampep-microservice-1
```

---

**最後更新：** 2026-01-10  
**版本：** 1.0.0  
**狀態：** ✅ 生產就緒
