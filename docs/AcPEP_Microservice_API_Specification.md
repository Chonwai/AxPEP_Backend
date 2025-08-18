# AcPEP 微服務 API 規範文檔

## 🎯 項目背景

AcPEP（抗癌肽預測）服務目前基於傳統的 Python 腳本架構，需要遷移到微服務架構以提高性能、可維護性和可擴展性。本文檔參考 AmPEP30 的成功微服務實現，為 AcPEP 團隊提供詳細的 API 規範和集成指南。

## 📋 服務概述

**服務名稱**: Deep-AcPEP Microservice  
**建議端口**: 8003 (預測服務) + 8004 (分類服務)  
**基礎 URL**: `http://localhost:8003` (預測), `http://localhost:8004` (分類)

AcPEP 微服務需要支持：
1. 🔬 **多種預測方法** - 各種抗癌肽預測算法
2. 🧬 **序列分類** - xDeep-AcPEP-Classification
3. 📊 **批量處理** - FASTA 格式輸入
4. ⚡ **高性能** - Docker 容器化部署

## 🌟 AmPEP30 成功範例

### AmPEP30 的架構優勢
基於我們對現有 AmPEP30 微服務的分析，以下是其成功的關鍵特點：

#### 1. **統一響應格式**
```json
// 成功響應
{
  "sequence_name": ["seq1"],
  "sequence": ["GLFDIVKKVVGALGSL"],  
  "length": [16],
  "prediction": [1],               // 1=AMP, 0=non-AMP
  "amp_probability": [0.882],
  "confidence": [0.882],
  "status": ["success"]
}

// 錯誤響應 (保持相同結構)
{
  "sequence_name": ["seq1"],
  "sequence": ["INVALID_SEQUENCE"],
  "length": [15],
  "prediction": [null],
  "amp_probability": [null],
  "confidence": [null],
  "status": ["error"],
  "error": ["序列包含無效氨基酸"]
}
```

#### 2. **健壯的客戶端實現**
- 自動重試機制
- 多路由回退策略 (`/api/predict` → `/predict/fasta`)
- 智能響應標準化
- 完整的錯誤處理

#### 3. **無縫後端集成**
- 生成與舊版本兼容的 `.out` 文件格式
- 支援環境變數切換 (`USE_RFAMPEP30_MICROSERVICE=true`)
- 自動回退到本地腳本（失敗時）

## 🛠️ AcPEP 微服務 API 規範

### 預測服務 API (端口 8003)

#### 1. 健康檢查
```http
GET /health
```

**響應格式**:
```json
{
  "status": ["healthy"],
  "service": ["AcPEP-Prediction-API"],
  "version": ["1.0.0"],
  "timestamp": ["2024-12-30T12:00:00+0000"],
  "available_methods": ["method1", "method2", "method3"]
}
```

#### 2. 單序列預測
```http
POST /predict/single
```

**請求格式**:
```json
{
  "sequence": "GLFDIVKKVVGALGSL",
  "method": "method1",        // 必需：具體的預測方法名
  "precision": 3              // 可選：小數精度，默認 3
}
```

#### 3. FASTA 批量預測  
```http
POST /predict/fasta
```

**請求格式**:
```json
{
  "fasta_content": ">seq1\nGLFDIVKKVVGALGSL\n>seq2\nALWKTMLKKLGTMALH",
  "method": "method1",
  "precision": 3
}
```

#### 4. 支持的方法查詢
```http
GET /methods
```

**響應格式**:
```json
{
  "methods": ["method1", "method2", "method3"],
  "default_method": "method1",
  "descriptions": {
    "method1": "傳統機器學習方法",
    "method2": "深度學習方法", 
    "method3": "混合方法"
  }
}
```

### 分類服務 API (端口 8004)

#### 1. 序列分類
```http
POST /classify
```

**請求格式**:
```json
{
  "fasta_content": ">seq1\nGLFDIVKKVVGALGSL\n>seq2\nALWKTMLKKLGTMALH"
}
```

**響應格式**:
```json
{
  "results": [
    {
      "sequence_name": "seq1",
      "sequence": "GLFDIVKKVVGALGSL",
      "classification": "Type_A",
      "confidence": 0.95,
      "status": "success"
    },
    {
      "sequence_name": "seq2", 
      "sequence": "ALWKTMLKKLGTMALH",
      "classification": "Type_B",
      "confidence": 0.87,
      "status": "success"
    }
  ]
}
```

## 📊 統一響應格式規範

### 預測響應結構
```json
{
  "sequence_name": ["序列名稱數組"],
  "sequence": ["原始序列數組"],
  "length": [序列長度數組],
  "prediction": [預測結果數組],      // 1=抗癌肽, 0=非抗癌肽
  "acp_probability": [抗癌肽機率數組],
  "confidence": [置信度數組],
  "method_used": ["使用的方法數組"],
  "status": ["狀態數組"],           // "success" 或 "error"
  "error": ["錯誤信息數組"]         // 僅錯誤時存在
}
```

### 分類響應結構
```json
{
  "results": [
    {
      "sequence_name": "序列名稱",
      "sequence": "原始序列",
      "classification": "分類結果", 
      "confidence": 置信度,
      "status": "success"
    }
  ]
}
```

## 🔧 後端集成要求

### 1. 檔案格式兼容性

#### 預測結果格式 (`{method}.out`)
```
# 空白分隔的三欄格式（與現有系統兼容）
seq1 1 0.882
seq2 0 0.234
seq3 1 0.756
```

#### 分類結果格式 (`xDeep-AcPEP-Classification.csv`)
```csv
sequence_name,classification,confidence
seq1,Type_A,0.95
seq2,Type_B,0.87
seq3,Type_A,0.92
```

### 2. 錯誤處理要求

- ✅ 序列驗證（長度、氨基酸有效性）
- ✅ 方法驗證（支持的方法列表）
- ✅ 批量處理中的部分失敗處理
- ✅ 統一錯誤響應格式
- ✅ HTTP 狀態碼規範 (200 for business errors)

### 3. 性能要求

- ⚡ 單序列響應時間：< 5秒
- ⚡ 批量處理：每個序列 < 10秒
- ⚡ 健康檢查響應：< 1秒
- 🔧 支持並發請求
- 📦 容器化部署

## 🚀 部署規範

### Docker 容器要求

#### 預測服務 Dockerfile 示例
```dockerfile
FROM python:3.9-slim

WORKDIR /app
COPY requirements.txt .
RUN pip install -r requirements.txt

COPY . .
EXPOSE 8003

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8003"]
```

#### 分類服務 Dockerfile 示例  
```dockerfile
FROM python:3.9-slim

WORKDIR /app
COPY requirements.txt .
RUN pip install -r requirements.txt

COPY . .
EXPOSE 8004

CMD ["uvicorn", "classifier:app", "--host", "0.0.0.0", "--port", "8004"]
```

### Docker Compose 配置
```yaml
version: '3.8'
services:
  acpep-prediction:
    build:
      context: .
      dockerfile: Dockerfile.prediction
    ports:
      - "8003:8003"
    environment:
      - API_PORT=8003
      - DEFAULT_METHOD=method1
    
  acpep-classification:
    build:
      context: .
      dockerfile: Dockerfile.classification  
    ports:
      - "8004:8004"
    environment:
      - API_PORT=8004
```

## 📝 實現檢查清單

### 預測服務 ✅
- [ ] `/health` 端點實現
- [ ] `/predict/single` 端點實現  
- [ ] `/predict/fasta` 端點實現
- [ ] `/methods` 端點實現
- [ ] 統一響應格式
- [ ] 錯誤處理機制
- [ ] 序列驗證邏輯
- [ ] 方法驗證邏輯
- [ ] 批量處理支持
- [ ] Docker 容器化

### 分類服務 ✅
- [ ] `/health` 端點實現
- [ ] `/classify` 端點實現
- [ ] 統一響應格式
- [ ] 錯誤處理機制
- [ ] Docker 容器化

### 測試要求 ✅
- [ ] 單元測試覆蓋
- [ ] 集成測試
- [ ] 性能測試
- [ ] 壓力測試
- [ ] 錯誤場景測試

## 🔄 遷移策略

### 階段 1: 微服務開發
1. 實現預測微服務 (8003 端口)
2. 實現分類微服務 (8004 端口)  
3. 本地測試和驗證

### 階段 2: 後端集成
1. 開發 `AcPEPMicroserviceClient`
2. 修改 `TaskUtils` 添加微服務方法
3. 更新 `AcPEPJob` 支持切換

### 階段 3: 部署和切換
1. 部署微服務到測試環境
2. 功能測試和性能驗證
3. 生產環境部署
4. 逐步切換到微服務

## 📞 技術對接

### 需要 AcPEP 團隊提供
1. **現有方法清單** - 所有支持的預測方法名稱
2. **輸入輸出樣例** - 每種方法的示例數據
3. **分類邏輯** - xDeep-AcPEP-Classification 的具體實現
4. **性能基準** - 當前系統的性能數據
5. **測試數據集** - 用於驗證微服務正確性

### 我們提供支持
1. **詳細 API 規範** - 完整的接口文檔
2. **客戶端實現** - PHP 客戶端代碼
3. **集成指南** - 後端集成步驟
4. **測試工具** - API 測試腳本
5. **部署支持** - Docker 和配置協助

---

**文檔版本**: 1.0.0  
**創建日期**: 2024年12月30日  
**更新日期**: 2024年12月30日

這份文檔基於 AmPEP30 的成功實踐，為 AcPEP 微服務化提供完整的技術規範。如有任何疑問或需要進一步討論，請隨時聯繫！
