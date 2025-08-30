# BESTox 毒性預測 API 文檔

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/your-repo/bestox)
[![Python](https://img.shields.io/badge/python-3.9+-green.svg)](https://python.org)
[![FastAPI](https://img.shields.io/badge/FastAPI-0.104+-red.svg)](https://fastapi.tiangolo.com)
[![Docker](https://img.shields.io/badge/docker-ready-blue.svg)](https://docker.com)

## 📋 概述

BESTox 是一個高效能的化學分子急性毒性預測微服務，基於深度學習 CNN 模型構建。本服務提供 RESTful API 介面，可以預測 SMILES 格式化學分子的 LD50 值（半數致死劑量），支援單一分子和批量預測。

### 🎯 主要特色

- 🧬 **高精度預測**：基於深度學習 CNN 模型，預測化學分子急性毒性
- ⚡ **高效能服務**：FastAPI 框架，支援非同步處理
- 📊 **批量處理**：支援一次處理多達 100 個分子
- 🏥 **健康監控**：完整的健康檢查端點，適合容器編排
- 📚 **自動文檔**：基於 OpenAPI 3.0 的互動式 API 文檔
- 🐳 **容器化部署**：完整的 Docker 支援，一鍵部署
- 📈 **效能監控**：內建服務狀態和效能指標

### 🏗️ 系統架構

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   客戶端請求    │───→│   FastAPI       │───→│  預測服務       │
│   (SMILES)     │    │   路由層         │    │  (PyTorch)     │
└─────────────────┘    └──────────────────┘    └─────────────────┘
                              │                         │
                              ▼                         ▼
                       ┌──────────────────┐    ┌─────────────────┐
                       │   驗證 & 序列化   │    │  特徵生成       │
                       │   (Pydantic)    │    │  (RDKit)       │
                       └──────────────────┘    └─────────────────┘
```

## 🚀 快速開始

### 前置需求

- **Python**: 3.9 或更高版本
- **Docker**: 20.10+ （推薦）
- **Docker Compose**: 2.0+
- **可用端口**: 8006

### 🐳 Docker 部署（推薦）

```bash
# 1. 下載專案
git clone <your-repo-url>
cd BESTox

# 2. 進入 Docker 目錄
cd microservice/docker

# 3. 一鍵啟動服務
./start.sh up

# 或手動啟動
docker compose up --build -d
```

### 🔧 本地開發部署

```bash
# 1. 安裝依賴
pip install -r microservice/requirements.txt

# 2. 啟動服務
cd microservice
uvicorn api.app:app --host 0.0.0.0 --port 8006 --reload
```

### ✅ 驗證部署

```bash
# 檢查服務健康狀態
curl http://localhost:8006/health

# 查看互動式 API 文檔
open http://localhost:8006/docs

# 查看 ReDoc 文檔
open http://localhost:8006/redoc
```

## 📖 API 參考

### 🌐 基礎資訊

| 參數 | 值 |
|------|-----|
| **基礎 URL** | `http://localhost:8006` |
| **API 版本** | `1.0.0` |
| **內容類型** | `application/json` |
| **文檔格式** | OpenAPI 3.0 |
| **認證方式** | 無需認證 |

### 📍 API 端點總覽

| HTTP 方法 | 端點 | 功能描述 | 標籤 |
|-----------|------|----------|------|
| `GET` | `/` | 服務基本資訊 | 基本 |
| `GET` | `/health` | 基本健康檢查 | 健康檢查 |
| `GET` | `/health/ready` | 就緒檢查（K8s 適用） | 健康檢查 |
| `GET` | `/health/live` | 存活檢查（K8s 適用） | 健康檢查 |
| `GET` | `/model/info` | 模型詳細資訊 | 模型資訊 |
| `GET` | `/status` | 服務狀態監控 | 服務狀態 |
| `POST` | `/predict/single` | 單一分子毒性預測 | 預測 |
| `POST` | `/predict/batch` | 批量分子毒性預測 | 預測 |

## 🔍 詳細 API 說明

### 1. 基本資訊端點

#### `GET /`
獲取服務基本資訊

**回應範例**：
```json
{
  "service": "BESTox 毒性預測 API",
  "version": "1.0.0",
  "status": "running",
  "timestamp": "2025-01-20T10:00:00.000000"
}
```

### 2. 健康檢查端點

#### `GET /health`
基本健康檢查，返回服務和模型狀態

**回應格式**：
```json
{
  "status": "healthy",           // 服務狀態：healthy/unhealthy
  "timestamp": "2025-01-20T10:00:00.000000",
  "version": "1.0.0",
  "model_loaded": true          // 模型是否已載入
}
```

#### `GET /health/ready`
就緒檢查，適用於 Kubernetes readiness probe

**成功回應** (200)：
```json
{
  "status": "ready",
  "timestamp": "2025-01-20T10:00:00.000000",
  "version": "1.0.0",
  "model_loaded": true
}
```

**未就緒回應** (503)：
```json
{
  "detail": "服務尚未就緒，模型未載入"
}
```

#### `GET /health/live`
存活檢查，適用於 Kubernetes liveness probe

**回應格式**：
```json
{
  "status": "alive",
  "timestamp": "2025-01-20T10:00:00.000000"
}
```

### 3. 模型資訊端點

#### `GET /model/info`
獲取載入模型的詳細資訊

**回應格式**：
```json
{
  "model_name": "BESTox CNN",
  "model_version": "1.0",
  "input_format": "SMILES",
  "max_sequence_length": 300,
  "supported_features": [
    "molecular_toxicity",
    "LD50_prediction"
  ]
}
```

#### `GET /status`
獲取詳細的服務運行狀態和性能指標

**回應格式**：
```json
{
  "service_name": "BESTox Prediction Service",
  "version": "1.0.0",
  "status": "healthy",
  "uptime_seconds": 3600.5,
  "total_predictions": 150,
  "average_response_time_ms": 187.2,
  "memory_usage_mb": 347.32,
  "model_info": {
    "model_name": "BESTox CNN",
    "model_version": "1.0",
    "input_format": "SMILES",
    "max_sequence_length": 300,
    "supported_features": ["molecular_toxicity", "LD50_prediction"]
  }
}
```

## 🧬 預測 API

### 1. 單一分子預測

#### `POST /predict/single`
預測單一化學分子的急性毒性

**請求格式**：
```json
{
  "smiles": "CC(C)=CCO",              // 必需：SMILES 格式的分子結構
  "molecule_id": "test_molecule_1"    // 可選：分子識別符
}
```

**請求驗證規則**：
- `smiles`: 1-300 字符，不能為空，僅包含有效 SMILES 字符
- `molecule_id`: 可選字符串

**成功回應** (200)：
```json
{
  "success": true,
  "prediction": {
    "molecule_id": "test_molecule_1",
    "smiles": "CC(C)=CCO",
    "log10_ld50": -0.013594166375696659,
    "ld50": 1.03179677565062,          // LD50 值 (mg/kg)
    "prediction_confidence": null,      // 預測信心度（目前未實現）
    "processing_time_ms": 187.19       // 處理時間（毫秒）
  },
  "timestamp": "2025-01-20T10:00:00.000000"
}
```

**失敗回應** (200, success=false)：
```json
{
  "success": false,
  "prediction": null,
  "error_message": "無效的 SMILES 格式",
  "timestamp": "2025-01-20T10:00:00.000000"
}
```

### 2. 批量分子預測

#### `POST /predict/batch`
批量預測多個化學分子的急性毒性（最多 100 個）

**請求格式**：
```json
{
  "batch_id": "batch_001",           // 可選：批次識別符
  "molecules": [
    {
      "smiles": "CC(C)=CCO",
      "molecule_id": "mol_1"
    },
    {
      "smiles": "CCO",
      "molecule_id": "mol_2"
    }
  ]
}
```

**請求驗證規則**：
- `molecules`: 1-100 個分子的列表
- 每個分子遵循單一預測的驗證規則

**成功回應** (200)：
```json
{
  "success": true,
  "batch_id": "batch_001",
  "predictions": [
    {
      "molecule_id": "mol_1",
      "smiles": "CC(C)=CCO",
      "log10_ld50": -0.013594166375696659,
      "ld50": 1.03179677565062,
      "prediction_confidence": null,
      "processing_time_ms": 25.64
    },
    {
      "molecule_id": "mol_2",
      "smiles": "CCO",
      "log10_ld50": -0.013487898744642735,
      "ld50": 1.0315443359121192,
      "prediction_confidence": null,
      "processing_time_ms": 25.71
    }
  ],
  "failed_molecules": [],             // 失敗的分子 SMILES 列表
  "total_processed": 2,
  "total_successful": 2,
  "total_failed": 0,
  "total_processing_time_ms": 51.45,
  "timestamp": "2025-01-20T10:00:00.000000"
}
```

## 📝 使用範例

### Python 範例

```python
import requests
import json

# 服務基礎 URL
BASE_URL = "http://localhost:8006"

# 1. 檢查服務健康狀態
def check_health():
    response = requests.get(f"{BASE_URL}/health")
    print("健康檢查:", response.json())

# 2. 單一分子預測
def predict_single_molecule():
    data = {
        "smiles": "CC(C)=CCO",
        "molecule_id": "test_molecule_1"
    }
    response = requests.post(f"{BASE_URL}/predict/single", json=data)
    result = response.json()
    
    if result["success"]:
        prediction = result["prediction"]
        print(f"分子 {prediction['molecule_id']} 的 LD50: {prediction['ld50']:.2f} mg/kg")
    else:
        print(f"預測失敗: {result['error_message']}")

# 3. 批量預測
def predict_batch_molecules():
    data = {
        "batch_id": "test_batch",
        "molecules": [
            {"smiles": "CC(C)=CCO", "molecule_id": "mol_1"},
            {"smiles": "CCO", "molecule_id": "mol_2"},
            {"smiles": "C1=CC=CC=C1", "molecule_id": "mol_3"}
        ]
    }
    response = requests.post(f"{BASE_URL}/predict/batch", json=data)
    result = response.json()
    
    print(f"批量預測結果:")
    print(f"成功: {result['total_successful']}, 失敗: {result['total_failed']}")
    print(f"總處理時間: {result['total_processing_time_ms']:.2f} ms")
    
    for prediction in result["predictions"]:
        print(f"  {prediction['molecule_id']}: LD50 = {prediction['ld50']:.2f} mg/kg")

if __name__ == "__main__":
    check_health()
    predict_single_molecule()
    predict_batch_molecules()
```

### JavaScript/Node.js 範例

```javascript
const axios = require('axios');

const BASE_URL = 'http://localhost:8006';

// 1. 檢查服務健康狀態
async function checkHealth() {
    try {
        const response = await axios.get(`${BASE_URL}/health`);
        console.log('健康檢查:', response.data);
    } catch (error) {
        console.error('健康檢查失敗:', error.message);
    }
}

// 2. 單一分子預測
async function predictSingleMolecule() {
    try {
        const data = {
            smiles: "CC(C)=CCO",
            molecule_id: "test_molecule_1"
        };
        
        const response = await axios.post(`${BASE_URL}/predict/single`, data);
        const result = response.data;
        
        if (result.success) {
            const prediction = result.prediction;
            console.log(`分子 ${prediction.molecule_id} 的 LD50: ${prediction.ld50.toFixed(2)} mg/kg`);
        } else {
            console.log(`預測失敗: ${result.error_message}`);
        }
    } catch (error) {
        console.error('預測請求失敗:', error.message);
    }
}

// 3. 批量預測
async function predictBatchMolecules() {
    try {
        const data = {
            batch_id: "test_batch",
            molecules: [
                { smiles: "CC(C)=CCO", molecule_id: "mol_1" },
                { smiles: "CCO", molecule_id: "mol_2" },
                { smiles: "C1=CC=CC=C1", molecule_id: "mol_3" }
            ]
        };
        
        const response = await axios.post(`${BASE_URL}/predict/batch`, data);
        const result = response.data;
        
        console.log('批量預測結果:');
        console.log(`成功: ${result.total_successful}, 失敗: ${result.total_failed}`);
        console.log(`總處理時間: ${result.total_processing_time_ms.toFixed(2)} ms`);
        
        result.predictions.forEach(prediction => {
            console.log(`  ${prediction.molecule_id}: LD50 = ${prediction.ld50.toFixed(2)} mg/kg`);
        });
    } catch (error) {
        console.error('批量預測請求失敗:', error.message);
    }
}

// 執行範例
async function runExamples() {
    await checkHealth();
    await predictSingleMolecule();
    await predictBatchMolecules();
}

runExamples();
```

### cURL 範例

```bash
# 1. 檢查服務健康狀態
curl -X GET http://localhost:8006/health

# 2. 獲取模型資訊
curl -X GET http://localhost:8006/model/info

# 3. 單一分子預測
curl -X POST http://localhost:8006/predict/single \
  -H "Content-Type: application/json" \
  -d '{
    "smiles": "CC(C)=CCO",
    "molecule_id": "test_molecule_1"
  }'

# 4. 批量預測
curl -X POST http://localhost:8006/predict/batch \
  -H "Content-Type: application/json" \
  -d '{
    "batch_id": "test_batch",
    "molecules": [
      {"smiles": "CC(C)=CCO", "molecule_id": "mol_1"},
      {"smiles": "CCO", "molecule_id": "mol_2"}
    ]
  }'

# 5. 獲取服務狀態
curl -X GET http://localhost:8006/status
```

## ⚠️ 錯誤處理

### HTTP 狀態碼

| 狀態碼 | 說明 | 範例場景 |
|--------|------|----------|
| `200` | 成功 | 正常的 API 回應 |
| `422` | 驗證錯誤 | 無效的 SMILES 格式或請求參數 |
| `500` | 內部伺服器錯誤 | 模型預測失敗或系統錯誤 |
| `503` | 服務不可用 | 模型未載入或服務未就緒 |

### 常見錯誤

#### 1. SMILES 格式錯誤
**錯誤訊息**: `"SMILES 包含無效字符"`
**解決方案**: 檢查 SMILES 字符串是否包含有效字符，移除無效字符

#### 2. 批量請求超限
**錯誤訊息**: `"molecules 列表長度超過最大限制 100"`
**解決方案**: 將大批量請求分割為多個小批量（每批最多 100 個分子）

#### 3. 模型未載入
**錯誤訊息**: `"服務尚未就緒，模型未載入"`
**解決方案**: 等待服務完全啟動，或檢查服務日誌

#### 4. 請求驗證失敗

**錯誤回應範例**：
```json
{
  "detail": [
    {
      "loc": ["body", "smiles"],
      "msg": "SMILES 不能為空",
      "type": "value_error"
    }
  ]
}
```

## 🔧 配置與部署

### 環境變數

| 變數名稱 | 預設值 | 描述 |
|----------|--------|------|
| `MODEL_PATH` | `/app/models/` | 模型檔案路徑 |
| `LOG_LEVEL` | `INFO` | 日誌級別 |
| `MAX_WORKERS` | `1` | 工作執行緒數量 |
| `HOST` | `0.0.0.0` | 綁定主機 |
| `PORT` | `8006` | 服務端口 |

### Docker Compose 設定

```yaml
version: '3.8'
services:
  bestox-api:
    build: .
    ports:
      - "8006:8006"
    environment:
      - MODEL_PATH=/app/models/
      - LOG_LEVEL=INFO
    volumes:
      - ./models:/app/models
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8006/health"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s
```

### Kubernetes 部署範例

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: bestox-api
spec:
  replicas: 2
  selector:
    matchLabels:
      app: bestox-api
  template:
    metadata:
      labels:
        app: bestox-api
    spec:
      containers:
      - name: bestox-api
        image: bestox-api:1.0.0
        ports:
        - containerPort: 8006
        env:
        - name: LOG_LEVEL
          value: "INFO"
        readinessProbe:
          httpGet:
            path: /health/ready
            port: 8006
          initialDelaySeconds: 30
          periodSeconds: 10
        livenessProbe:
          httpGet:
            path: /health/live
            port: 8006
          initialDelaySeconds: 60
          periodSeconds: 30
---
apiVersion: v1
kind: Service
metadata:
  name: bestox-api-service
spec:
  selector:
    app: bestox-api
  ports:
  - port: 80
    targetPort: 8006
  type: LoadBalancer
```

## 📊 性能規格

### 系統需求

| 資源 | 最低需求 | 推薦配置 |
|------|----------|----------|
| **CPU** | 1 核心 | 2+ 核心 |
| **記憶體** | 2GB | 4GB+ |
| **硬碟空間** | 1GB | 2GB+ |
| **網路** | 100Mbps | 1Gbps+ |

### 效能指標

| 指標 | 值 |
|------|-----|
| **單一預測延遲** | ~180ms |
| **批量預測吞吐量** | ~50 分子/秒 |
| **併發請求支援** | 10+ 併發 |
| **記憶體使用量** | ~350MB |
| **最大批量大小** | 100 分子 |

## 🐛 故障排除

### 常見問題

#### 1. 服務啟動失敗
```bash
# 檢查 Docker 日誌
docker compose logs bestox-api

# 常見原因：端口被佔用
sudo lsof -i :8006
```

#### 2. 模型載入失敗
```bash
# 檢查模型檔案是否存在
ls -la microservice/models/

# 檢查檔案權限
chmod 644 microservice/models/*
```

#### 3. 預測回應緩慢
```bash
# 檢查系統資源使用情況
docker stats bestox-api

# 增加 Docker 記憶體限制
docker compose up --memory=4g
```

#### 4. 網路連接問題
```bash
# 檢查防火牆設定
sudo ufw status

# 測試本地連接
curl -v http://localhost:8006/health
```

### 日誌級別

- **DEBUG**: 詳細的除錯資訊
- **INFO**: 一般操作資訊（預設）
- **WARNING**: 警告訊息
- **ERROR**: 錯誤訊息
- **CRITICAL**: 嚴重錯誤

## 📚 其他資源

### 互動式文檔
- **Swagger UI**: [http://localhost:8006/docs](http://localhost:8006/docs)
- **ReDoc**: [http://localhost:8006/redoc](http://localhost:8006/redoc)
- **OpenAPI JSON**: [http://localhost:8006/openapi.json](http://localhost:8006/openapi.json)

### 相關連結
- [FastAPI 官方文檔](https://fastapi.tiangolo.com/)
- [Pydantic 文檔](https://pydantic-docs.helpmanual.io/)
- [Docker 官方文檔](https://docs.docker.com/)
- [OpenAPI 規範](https://swagger.io/specification/)

## 🤝 支援與回饋

### 技術支援

如果您在使用過程中遇到問題，請按以下步驟進行：

1. **檢查本文檔**：先查看故障排除章節
2. **查看日誌**：檢查服務日誌以獲得詳細錯誤資訊
3. **測試範例**：使用提供的範例程式碼進行測試
4. **聯繫團隊**：將問題和相關日誌發送給開發團隊

### 回饋方式

- **Bug 報告**：請提供詳細的錯誤資訊和重現步驟
- **功能建議**：歡迎提出改進建議和新功能需求
- **文檔改進**：如發現文檔錯誤或需要補充，請告知我們

## 📄 授權條款

本專案採用 [MIT License](LICENSE) 授權條款。

---

**© 2025 BESTox 開發團隊**

*本文檔最後更新：2025年1月20日*