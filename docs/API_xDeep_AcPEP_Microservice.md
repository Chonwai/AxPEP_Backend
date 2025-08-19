### xDeep-AcPEP Prediction Service API（後端整合說明）

本文件說明如何與 xDeep-AcPEP 的預測服務 API 對接，包含端點用途、請求/回應格式、錯誤格式、限制條件與整合建議。

---

### 基本資訊

- **基底 URL**: `http://localhost:8004`
- **文件頁面**: `http://localhost:8004/docs`
- **OpenAPI JSON**: `http://localhost:8004/openapi.json`
- **認證**: 無（僅供內部/開發測試環境使用，可在閘道層加入認證）
- **Content-Type**: `application/json; charset=utf-8`

環境變數：
- **API_PORT**: 預設 `8004`
- **MODEL_DIR**: 預設 `/app/prediction/model/`（Docker 內），本機開發可掛載至 `prediction/model/`

啟動（開發模式，熱重載）：
```bash
uvicorn microservice.api.app:app --host 0.0.0.0 --port 8004 --reload
```

---

### 全域規則與限制

- **支援之 tissue**（可透過 `/model/info` 動態查詢）：`breast`、`cervix`、`colon`、`lung`、`prostate`、`skin`
- **序列限制**：
  - 僅允許 20 種標準胺基酸字母：`ACDEFGHIKLMNPQRSTVWY`
  - 長度上限：`38`
- **適用性領域（AD, Applicability Domain）**：
  - 回應欄位 `out_of_ad` 為 `true` 時表示該樣本超出模型適用性領域，此時 `prediction` 可能為 `null`。
- **排序**：批次預測的回傳結果與輸入順序一致。
- **錯誤格式**：HTTP 400，回傳 `{ "detail": "..." }`。

---

### 端點一覽

- `GET /health`：健康檢查（活性探針）
- `GET /model/info`：模型與資源資訊（支援 tissue、模型檔）
- `POST /predict/single`：單筆序列預測
- `POST /predict/batch`：多筆（同一 tissue）批次預測

---

### GET /health

- **用途**：服務健康檢查與容器活性探針。
- **Request**：無
- **Response 200**：
```json
{ "status": "healthy" }
```

範例（cURL）：
```bash
curl -s http://localhost:8004/health
```

---

### GET /model/info

- **用途**：查詢模型資料夾、可用 tissue 清單與已發現的模型檔。
- **Request**：無
- **Response 200**（示例）：
```json
{
  "model_dir": "/app/prediction/model/",
  "available_tissues": ["breast","cervix","colon","lung","prostate","skin"],
  "discovered_models": {
    "breast": "/app/prediction/model/model_breast.pth",
    "lung": "/app/prediction/model/model_lung.pth"
  }
}
```

範例（cURL）：
```bash
curl -s http://localhost:8004/model/info | jq
```

---

### POST /predict/single

- **用途**：對單條胺基酸序列在指定 tissue 上進行預測。
- **Request Body**：
```json
{
  "name": "pep-001",
  "sequence": "AWKKWAKAWKWAKAKWWAKAA",
  "tissue": "breast"
}
```
- **Response 200**：
```json
{
  "name": "pep-001",
  "tissue": "breast",
  "prediction": 12.345678,
  "out_of_ad": false
}
```
- **Response 400（示例）**：
```json
{ "detail": "Unsupported tissue type" }
```

範例（cURL）：
```bash
curl -s \
  -H "Content-Type: application/json" \
  -X POST http://localhost:8004/predict/single \
  -d '{
    "name": "pep-001",
    "sequence": "AWKKWAKAWKWAKAKWWAKAA",
    "tissue": "breast"
  }'
```

範例（Python requests）：
```python
import requests

payload = {
    "name": "pep-001",
    "sequence": "AWKKWAKAWKWAKAKWWAKAA",
    "tissue": "breast",
}
r = requests.post("http://localhost:8004/predict/single", json=payload, timeout=60)
r.raise_for_status()
print(r.json())
```

---

### POST /predict/batch

- **用途**：對多條序列（同一 tissue）進行批次預測。
- **Request Body**：
```json
{
  "tissue": "breast",
  "items": [
    {"name": "pep-001", "sequence": "AWKKWAKAWKWAKAKWWAKAA"},
    {"name": "pep-002", "sequence": "AKKWWKKAAKKAAWKKWAK"}
  ]
}
```
- **Response 200**：
```json
{
  "tissue": "breast",
  "results": [
    {"name": "pep-001", "tissue": "breast", "prediction": 12.345678, "out_of_ad": false},
    {"name": "pep-002", "tissue": "breast", "prediction": null, "out_of_ad": true}
  ]
}
```
- **Response 400（示例）**：
```json
{ "detail": "Unsupported tissue type" }
```

範例（cURL）：
```bash
curl -s \
  -H "Content-Type: application/json" \
  -X POST http://localhost:8004/predict/batch \
  -d '{
    "tissue": "breast",
    "items": [
      {"name": "pep-001", "sequence": "AWKKWAKAWKWAKAKWWAKAA"},
      {"name": "pep-002", "sequence": "AKKWWKKAAKKAAWKKWAK"}
    ]
  }'
```

範例（Python requests）：
```python
import requests

payload = {
    "tissue": "breast",
    "items": [
        {"name": "pep-001", "sequence": "AWKKWAKAWKWAKAKWWAKAA"},
        {"name": "pep-002", "sequence": "AKKWWKKAAKKAAWKKWAK"},
    ],
}
r = requests.post("http://localhost:8004/predict/batch", json=payload, timeout=300)
r.raise_for_status()
print(r.json())
```

---

### 錯誤處理與回傳語意

- **400 Bad Request**：
  - 不支援的 `tissue`
  - 非法序列字元或序列為空
  - 序列長度超過 38
  - 格式錯誤（缺少必要欄位）
- **out_of_ad**：`true` 表示該樣本不在適用性領域，`prediction` 可能為 `null`；整合端可將此視為「無有效分數」。

---

### 整合建議

- **超時設定**：單筆預測建議 `60s`，批次預測視批量大小可增至 `300s`。
- **重試策略**：僅對網路層錯誤（連線重置、暫時性 5xx）進行重試；對 4xx 不重試。
- **輸入校驗**：在呼叫 API 前先行校驗 `tissue`（可快取 `/model/info`）與序列字元/長度。
- **結果處理**：
  - 若 `out_of_ad = true`：建議標記為不可用，並提示資料不在模型適用性範圍。
  - 批次結果按輸入順序返回，可直接對位合併。
- **觀測性**：可於外層加上請求 ID 與日誌，便於追蹤預測。

---

### 版本與相容性

- API 版本：`1.0.0`（載於 OpenAPI 與 `/docs` 內）
- 如需新增 `tissue`，請同步更新模型與服務端常量並重啟服務；整合端可依 `/model/info` 動態處理。

