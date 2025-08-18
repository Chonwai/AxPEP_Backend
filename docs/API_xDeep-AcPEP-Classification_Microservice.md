## xDeep-AcPEP-Classification Microservice 對接指南（繁體中文）

本文件說明如何以業界常見方式對接本微服務 API，涵蓋端點說明、請求/回應格式、錯誤處理與 Python 使用範例。

- **OpenAPI UI（Swagger）**: `http://localhost:8003/docs`
- **OpenAPI JSON**: `http://localhost:8003/openapi.json`
- **Base URL（預設開發環境）**: `http://localhost:8003`

若您以 Docker 啟動本服務（專案根目錄）：

```bash
docker compose -f microservice/docker-compose.yml up -d api
```


### 驗證與授權

- **認證**: 無（目前所有端點皆為公開存取，建議部署到生產環境時加上閘道或網段限制）
- **內容格式**: `application/json`


## 端點總覽

- `GET /health`: 健康檢查
- `GET /model/info`: 模型載入與特徵資訊
- `POST /predict/single`: 針對單一序列進行分類
- `POST /predict/batch`: 針對多筆序列進行分類（支援 JSON records 或 FASTA 文字內容，二擇一）


## 回應格式（通用欄位）

除 `GET /model/info` 外，其餘成功回應皆包含：

- **success**: 布林值，是否成功
- **data**: 回傳資料本體
- **timestamp**: ISO8601 時間戳記（UTC）

錯誤時可能回傳：

- **HTTP 400**: 使用者輸入錯誤（例如 FASTA 格式不正確）
- **HTTP 422**: 結構驗證錯誤（Pydantic 驗證失敗）
- **HTTP 500**: 伺服器內部錯誤（`detail: "INTERNAL_ERROR"`）


## 端點詳解與範例

### GET `/health`

- **用途**: 確認服務狀態
- **成功回應（200）** 範例：

```json
{
  "status": "healthy",
  "service": "xDeep-AcPEP-Classification",
  "version": "1.0.0",
  "timestamp": "2024-01-01T00:00:00.000000",
  "model_loaded": true
}
```

### GET `/model/info`

- **用途**: 取得模型載入狀態與特徵資訊
- **成功回應（200）** 範例：

```json
{
  "loaded": true,
  "features_selected": 123
}
```

或（尚未載入）

```json
{ "loaded": false }
```

### POST `/predict/single`

- **用途**: 送入單一序列，取得預測
- **Request Body**:
  - `name`（選填，預設 `"sequence_1"`）
  - `sequence`（必填，字串，胺基酸序列）

- **請求範例**：

```json
{
  "name": "sequence_1",
  "sequence": "ALWKTMLKKLGTMALHAGKAALGAAADTISQGTQ"
}
```

- **成功回應（200）** 範例：

```json
{
  "success": true,
  "data": {
    "name": "sequence_1",
    "prediction": 1,
    "probability": 0.8732
  },
  "timestamp": "2024-01-01T00:00:00.000000"
}
```

### POST `/predict/batch`

- **用途**: 一次提交多筆序列進行預測
- **Request Body（anyOf，二擇一）**:
  1. `records`: 推薦。陣列內每筆包含 `name`（選填）與 `sequence`（必填）
  2. `fasta`: 單一字串，必須以 `>` 開頭之 FASTA 格式

- **records 請求範例**：

```json
{
  "records": [
    {"name": "sequence_1", "sequence": "ALWKTMLKKLGTMALHAGKAALGAAADTISQGTQ"},
    {"name": "sequence_2", "sequence": "AWKKWAKAWKWAKAKWWAKAA"}
  ]
}
```

- **fasta 請求範例**：

```json
{
  "fasta": ">sequence_1\nALWKTMLKKLGTMALHAGKAALGAAADTISQGTQ\n>sequence_2\nAWKKWAKAWKWAKAKWWAKAA\n"
}
```

- 注意事項：
  - `records` 與 `fasta` 僅能擇一提供，否則會回傳 400
  - `fasta` 內容需確保每條序列皆有以 `>` 開頭的標頭行

- **成功回應（200）** 範例：

```json
{
  "success": true,
  "data": {
    "predictions": [
      {"name": "sequence_1", "prediction": 1, "probability": 0.8732},
      {"name": "sequence_2", "prediction": 0, "probability": 0.2419}
    ]
  },
  "timestamp": "2024-01-01T00:00:00.000000"
}
```


## Python 對接範例（requests）

```python
import requests

BASE_URL = "http://localhost:8003"

def predict_single(name: str, sequence: str):
    payload = {"name": name, "sequence": sequence}
    r = requests.post(f"{BASE_URL}/predict/single", json=payload, timeout=60)
    r.raise_for_status()
    return r.json()

def predict_batch_records(records):
    payload = {"records": records}
    r = requests.post(f"{BASE_URL}/predict/batch", json=payload, timeout=300)
    r.raise_for_status()
    return r.json()

def predict_batch_fasta(fasta_text: str):
    payload = {"fasta": fasta_text}
    r = requests.post(f"{BASE_URL}/predict/batch", json=payload, timeout=300)
    r.raise_for_status()
    return r.json()

if __name__ == "__main__":
    # 單筆
    single = predict_single(
        name="sequence_1",
        sequence="ALWKTMLKKLGTMALHAGKAALGAAADTISQGTQ",
    )
    print("single:", single)

    # 批次（records）
    batch_records = predict_batch_records([
        {"name": "sequence_1", "sequence": "ALWKTMLKKLGTMALHAGKAALGAAADTISQGTQ"},
        {"name": "sequence_2", "sequence": "AWKKWAKAWKWAKAKWWAKAA"},
    ])
    print("batch-records:", batch_records)

    # 批次（fasta）
    fasta_text = ">sequence_1\nALWKTMLKKLGTMALHAGKAALGAAADTISQGTQ\n>sequence_2\nAWKKWAKAWKWAKAKWWAKAA\n"
    batch_fasta = predict_batch_fasta(fasta_text)
    print("batch-fasta:", batch_fasta)
```


## cURL 範例

```bash
# 單筆
curl -sS -X POST "http://localhost:8003/predict/single" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "sequence_1",
    "sequence": "ALWKTMLKKLGTMALHAGKAALGAAADTISQGTQ"
  }' | jq

# 批次（records）
curl -sS -X POST "http://localhost:8003/predict/batch" \
  -H "Content-Type: application/json" \
  -d '{
    "records": [
      {"name": "sequence_1", "sequence": "ALWKTMLKKLGTMALHAGKAALGAAADTISQGTQ"},
      {"name": "sequence_2", "sequence": "AWKKWAKAWKWAKAKWWAKAA"}
    ]
  }' | jq

# 批次（fasta）
curl -sS -X POST "http://localhost:8003/predict/batch" \
  -H "Content-Type: application/json" \
  -d '{
    "fasta": ">sequence_1\nALWKTMLKKLGTMALHAGKAALGAAADTISQGTQ\n>sequence_2\nAWKKWAKAWKWAKAKWWAKAA\n"
  }' | jq
```


## 錯誤處理與除錯建議

- **400 Bad Request**：
  - `/predict/batch` 同時提供 `records` 與 `fasta`
  - `fasta` 內容未以 `>` 開頭，或格式不符合 FASTA
  - `/predict/single` 的 `sequence` 空白或非字串
- **422 Unprocessable Entity**：
  - JSON 結構與 schema 不符（請參考 `/docs` 表單提示）
- **500 Internal Server Error**：
  - 內部錯誤，回傳 `detail: "INTERNAL_ERROR"`
- 建議：
  - 優先確認 `/health` 與 `/model/info` 是否正常（`model_loaded`/`loaded`）
  - 保留原始請求與回應內容以利追蹤


## 常見問題（FAQ）

- **FASTA 必須以 `>` 開頭嗎？**
  - 是。每條序列需搭配一行以 `>` 開頭的標頭。
- **批次請求建議使用哪種？**
  - 建議使用 `records`，較易於建構與驗證；`fasta` 適用您已有 FASTA 檔或字串的情境。
- **回應中的 `prediction` 與 `probability` 代表什麼？**
  - `prediction`：二元分類結果（0 或 1）；`probability`：模型對為正類（1）的機率估計。


## 版本資訊

- 服務：`xDeep-AcPEP-Classification API`
- 版本：`1.0.0`
- 以實際 `/openapi.json` 與原始碼為準

