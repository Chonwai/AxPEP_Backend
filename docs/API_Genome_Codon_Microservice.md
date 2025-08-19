## Genome ORF 微服務 API 使用手冊（給 Laravel 後端）

本文件說明如何呼叫本專案提供的 ORF 抽取 API。服務以 FastAPI 實作，支援自動產生之 OpenAPI 文件與 Swagger UI。

- **本機開發 Base URL（預設）**：`http://localhost:8005`
- **文件與規格**：
  - Swagger UI：`http://localhost:8005/docs`
  - OpenAPI JSON：`http://localhost:8005/openapi.json`
- **認證**：無（目前僅限內部開發測試環境）
- **Content-Type**：`application/json`


### 狀態碼與錯誤格式

- **200 OK**：成功。回傳對應資料結構。
- **400 Bad Request**：輸入格式錯誤或處理失敗時，回傳：

```json
{ "detail": "錯誤訊息" }
```

- **5xx**：非預期錯誤（容器/服務異常）。


### 資料模型（Schema）

- `SinglePredictRequest`
  - `name`（string，必填）：序列識別名
  - `sequence`（string，必填）：DNA 序列本體（不可含 FASTA header；只含 A/C/G/T）
  - `codon_table`（int，預設 1）：NCBI 遺傳密碼表編號
  - `min_len`（int，預設 5）：肽段長度下限（不含起始 M）
  - `max_len`（int，預設 250）：肽段長度上限（不含起始 M）
  - `only_standard_amino_acids`（bool，預設 true）：是否僅保留 20 種標準胺基酸

- `BatchPredictRequest`
  - `fasta`（string，必填）：完整 FASTA 文字（可多筆記錄）
  - 其餘欄位同上（`codon_table`、`min_len`、`max_len`、`only_standard_amino_acids`）

- 成功回傳（單筆與批次相同結構）
  - `count`（int）：抽取到的肽段條數
  - `fasta`（string）：FASTA 格式輸出，每條以 header+換行+序列 表示；序列為「去除起始 M 的肽段」。


### 端點一覽

#### GET `/health`
- 健康檢查。
- 回應：`{ "status": "healthy" }`

#### GET `/model/info`
- 服務資訊與預設參數。
- 回應（範例）：

```json
{
  "name": "ORF extractor",
  "version": "0.1.0",
  "description": "Extracts peptide ORFs from genome DNA and formats as FASTA (peptides exclude initial M).",
  "defaults": { "codon_table": 1, "min_len": 5, "max_len": 250, "only_standard_amino_acids": true }
}
```

#### POST `/predict/single`
- 以單條 DNA 序列（無 header）請求 ORF 抽取。
- 請求（JSON）：`SinglePredictRequest`
- 回應（JSON）：`{ count, fasta }`

請求範例：

```bash
curl -X POST http://localhost:8005/predict/single \
  -H 'Content-Type: application/json' \
  -d '{
    "name": "seq1",
    "sequence": "ATGGCCATTGTAATGGGCCGCTGAAAGGGTGCCCGATAG",
    "codon_table": 1,
    "min_len": 5,
    "max_len": 250,
    "only_standard_amino_acids": true
  }'
```

回應範例（示意）：

```json
{
  "count": 3,
  "fasta": ">+|seq1:4..31\nPEPTIDESEQ1\n>+|seq1:10..40\nPEPTIDESEQ2\n-|seq1:5..28\nPEPTIDESEQ3\n"
}
```

> 備註：FASTA `header` 形如 `>+|<identifier>:<start_nt>..<end_nt` 或 `>-|...`，座標為核苷酸座標、含括兩端（inclusive）。`+`/`-` 代表股向；回傳的肽段序列已「去除起始 M」。

#### POST `/predict/batch`
- 以多條 FASTA 文本（可多記錄）請求 ORF 抽取。
- 請求（JSON）：`BatchPredictRequest`
- 回應（JSON）：`{ count, fasta }`

請求範例：

```bash
curl -X POST http://localhost:8005/predict/batch \
  -H 'Content-Type: application/json' \
  -d '{
    "fasta": ">contig1\nATGGCCATTGTAATGGGCCGCTGAAAGGGTGCCCGATAG\n>contig2\nATGAAAAAAATAG",
    "codon_table": 1,
    "min_len": 5,
    "max_len": 250,
    "only_standard_amino_acids": true
  }'
```

回應範例（示意，格式同單筆）：

```json
{
  "count": 7,
  "fasta": ">+|contig1:4..31\nPEPTIDE1\n...\n>+|contig2:4..10\nPEPTIDE2\n"
}
```


### Laravel 整合範例（使用 `Http` 門面）

```php
use App\Services\CodonMicroserviceClient;

// 以專案內建的 Client 呼叫（建議做法）
$client = new CodonMicroserviceClient();
$result = $client->predictBatchFasta($fastaText, codonTable: 1, minLen: 5, maxLen: 250, onlyStandardAminoAcids: true);
// $result = ['count' => 7, 'fasta' => ">+|contig1:4..31\nPEPTIDE1\n...\n"]
file_put_contents(storage_path("app/Tasks/$taskId/codon_orf.fasta"), $result['fasta']);
```


### 使用注意事項與建議

- **輸入 DNA 字元集**：請使用大寫 `A/C/G/T`；若含其他符號或非標準字元，可能導致 400。
- **肽段長度**：`min_len`/`max_len` 指的是「不含起始 M」之長度；回傳的肽段序列也不包含起始 `M`。
- **股向與座標**：`+` 代表正股、`-` 代表反股；座標為核苷酸座標、含括兩端（inclusive）。
- **遺傳密碼表**：`codon_table` 依 NCBI 編號（預設 1）。
- **胺基酸集合**：若 `only_standard_amino_acids=true`，會過濾含非 20 種標準胺基酸的結果。
- **效能**：處理時間與輸入長度近似線性成長；批次請求建議適度切分以利穩定性。
- **測試與偵錯**：可使用 `http://localhost:8005/docs` 直接測試所有端點；或下載 `http://localhost:8005/openapi.json` 匯入 Postman。

### Laravel 環境變數

在 `.env` 新增或確認：

```env
USE_CODON_MICROSERVICE=true
CODON_MICROSERVICE_BASE_URL=http://localhost:8005
CODON_MICROSERVICE_TIMEOUT=300
```

當 `USE_CODON_MICROSERVICE=true` 時，後端會優先呼叫微服務；失敗時自動回退到本地腳本 `../Genome/ORF.py`。


### 版本與相依

- 服務版本：`0.1.0`
- 主要相依：FastAPI、Uvicorn、Biopython、Pydantic


### 啟動（參考）

本機（非容器）：

```bash
uvicorn microservice.api.app:app --host 0.0.0.0 --port 8005
```

Docker Compose：

```bash
docker compose -f microservice/docker/docker-compose.yml up --build
```

