# AMP Regression EC SA Predict API V2 規範

## 概述
這是AMP Regression EC SA Predict微服務的V2 JSON API規範，旨在替代當前基於文件傳輸的V1 API。

## API 端點

### POST /predict/sequences

#### 請求格式
```json
{
  "task_id": "4bfcd05b-b8c3-4ce8-b775-73984366653d",
  "sequences": [
    {
      "id": "AC_1",
      "sequence": "ALWKTMLKKLGTMALHAGKAALGAAADTISQGTQ"
    },
    {
      "id": "AC_2", 
      "sequence": "AWKKWAKAWKWAKAKWWAKAA"
    }
  ]
}
```

#### 響應格式
```json
{
  "status": true,
  "task_id": "4bfcd05b-b8c3-4ce8-b775-73984366653d",
  "predictions": [
    {
      "id": "AC_1",
      "ec_predicted_MIC_μM": 12.45,
      "sa_predicted_MIC_μM": 8.92
    },
    {
      "id": "AC_2",
      "ec_predicted_MIC_μM": 15.67,
      "sa_predicted_MIC_μM": 11.23
    }
  ],
  "processing_time": 2.34,
  "timestamp": "2024-01-15T10:30:45Z"
}
```

#### 錯誤響應格式
```json
{
  "status": false,
  "error": {
    "code": "INVALID_SEQUENCE",
    "message": "序列包含無效字符",
    "details": "序列 AC_1 包含非標準氨基酸字符"
  },
  "task_id": "4bfcd05b-b8c3-4ce8-b775-73984366653d",
  "timestamp": "2024-01-15T10:30:45Z"
}
```

## 健康檢查端點

### GET /health
```json
{
  "status": "healthy",
  "version": "2.0.0",
  "timestamp": "2024-01-15T10:30:45Z"
}
```

### GET /api/info
```json
{
  "service": "AMP_Regression_EC_SA_Predict",
  "version": "2.0.0",
  "api_version": "v2",
  "description": "Antimicrobial peptide activity prediction service",
  "supported_endpoints": ["/predict/sequences", "/health", "/api/info"],
  "max_sequences_per_request": 1000,
  "max_sequence_length": 100
}
```

## 與V1 API的對比

| 特性 | V1 API | V2 API |
|------|--------|--------|
| 數據傳輸 | 文件複製 | JSON HTTP |
| 調用次數 | 3次操作 | 1次操作 |
| 錯誤處理 | 文件系統錯誤 | HTTP狀態碼 |
| 並發安全 | 文件名衝突風險 | 無狀態安全 |
| 容器化 | 需要卷掛載 | 純HTTP通信 |
| 監控友好 | 難以追蹤 | 完整請求日誌 |

## 向後兼容性

系統將通過環境變數 `USE_AMP_REGRESSION_V2_API` 控制使用哪個版本：
- `true`: 使用V2 JSON API
- `false`: 使用V1文件傳輸API (默認)

當V2 API調用失敗時，系統會自動回退到V1 API確保服務穩定性。
