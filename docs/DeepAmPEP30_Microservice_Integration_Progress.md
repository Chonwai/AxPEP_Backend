## AmPEP30（RF/CNN）微服務整合進度與待辦清單

此文件用於追蹤將 RFAmPEP30（RF）與 DeepAmPEP30（CNN）從本地 R 腳本改為 API 微服務的整合進度。整合策略採「微服務 → 轉寫 `<method>.out` → 既有彙整流程」的最小改動方案，確保與既有 `FileUtils::matchingAmPEP()`、`writeAmPEPResultFile()` 完全相容。

### 目標與範疇
- 目標：以微服務取代 `rfampep30` 與 `deepampep30` 的本地 R 腳本執行，產出與現行一致的 `.out` 檔案格式（空白分隔三欄：`id prediction probability`）。
- 範疇：
  - 新增微服務 Client 與健康檢查
  - 在 `TaskUtils` 實作兩個微服務執行方法與 `.out` 轉寫 adapter
  - 在 `AmPEPJob` 透過 feature flags 切換微服務/本地 R 腳本，並支援失敗回退
  - 補齊環境變數、日誌、重試策略與基本驗收/觀測性
  - 不改動既有結果彙整與下載 API（`FileUtils::matchingAmPEP()`、`writeAmPEPResultFile()`）

### 現況（快速總結）
- [x] 已有 AmPEP 微服務整合與健康檢查指令：
  - `app/Utils/TaskUtils.php`：`runAmPEPMicroservice()` 與 `.out` 轉寫邏輯
  - `app/Console/Commands/CheckAmPEPMicroservice.php`
- [ ] 尚未導入 RF/CNN（AmPEP30）微服務 Client 與 `TaskUtils` 對應方法
- [ ] `AmPEPJob` 仍以本地 R 腳本執行 RF/CNN（可透過 flags 加入微服務與回退）

---

## 任務列表（以核取方塊追蹤）

### 1. API 契約與組態
- [x] 對齊微服務 API 文件 `docs/DeepAmPEP30_MICROSERVICE_API_Guide.md`
- [ ] 決定採用路由策略與回退：
  - 優先嘗試 Alternative Router JSON：`POST /api/predict`（Body: `{ fasta, method }`）
  - 失敗回退 Final API：`POST /predict/fasta`（Form：`fasta_content`、`method`、`precision`）
- [ ] 定義標籤與分數標準化規則：將回傳標籤統一為 `AMP` / `non-AMP`，機率取 `amp_probability`

### 2. 環境變數與設定
- [ ] 新增/確認以下 `.env` 變數（可依部署調整）：
  - `DEEPAMPEP30_MICROSERVICE_BASE_URL=http://localhost:8002`（或 Compose 預設 `8002`）
  - `USE_RFAMPEP30_MICROSERVICE=true`
  - `USE_DEEPAMPEP30_MICROSERVICE=true`
  - （選擇性，如拆成兩個服務）`RFAMPEP30_MICROSERVICE_BASE_URL=...`
- [ ] 在 `config/services.php` 或集中設定檔中暴露上述設定（選擇性）

### 3. 微服務 Client（支援 RF/CNN）
- [ ] 新增 `app/Services/AmPEP30MicroserviceClient.php`
  - [ ] 方法：`predictFastaJson(fasta, method)` → 呼叫 `POST /api/predict`
  - [ ] 方法：`predictFastaForm(fasta, method, precision)` → 呼叫 `POST /predict/fasta`
  - [ ] 健康檢查：`GET /health`
  - [ ] 回應格式統一轉換：輸出為陣列，欄位至少含 `prediction` 與 `probability`

### 4. TaskUtils：執行與 `.out` 轉寫
- [ ] 新增：`TaskUtils::runRFAmPEP30Microservice($task)`
  - [ ] 流程：讀取 `storage/app/Tasks/<id>/input.fasta` → 呼叫 Client（method=`rf`）→ 以 FASTA 順序轉寫 `Tasks/<id>/rfampep30.out`
- [ ] 新增：`TaskUtils::runDeepAmPEP30Microservice($task)`
  - [ ] 流程：同上（method=`cnn`）→ 轉寫 `Tasks/<id>/deepampep30.out`
- [ ] 共用：對齊 `runAmPEPMicroservice()` 的 `.out` 轉寫邏輯，優先以 FASTA header 順序 index 對齊；若數量不一致，寫入可用者並記錄 warning。

### 5. AmPEPJob：Feature Flags 與回退
- [ ] 在 `app/Jobs/AmPEPJob.php`：
  - [ ] `deepampep30` 分支：若 `USE_DEEPAMPEP30_MICROSERVICE=true` → 嘗試微服務，失敗回退 `TaskUtils::runDeepAmPEP30Task()`
  - [ ] `rfampep30` 分支：若 `USE_RFAMPEP30_MICROSERVICE=true` → 嘗試微服務，失敗回退 `TaskUtils::runRFAmPEP30Task()`
  - [ ] 日誌與例外處理模式對齊 `ampep` 分支

### 6. 健康檢查與運維
- [ ] 新增 Artisan 指令：`ampep30:health-check`（可接受 `--method=rf|cnn|both`）
  - [ ] 檢查 `/health`
  - [ ] 簡單預測 smoke test（使用臨時 FASTA）
  - [ ] 輸出版本、時間、支援方法等資訊

### 7. 日誌與重試策略
- [ ] 日誌：所有微服務請求/回應與錯誤情境記錄 `task_id`、method、耗時
- [ ] 超時：維持 3600 秒上限
- [ ] 重試：針對暫時性錯誤（5xx、連線中斷）進行最多 2 次指數退避重試

### 8. 驗收與 QA
- [ ] 用例一：只開 `rfampep30` → 產出 `.out` 與 `classification.csv`/`score.csv` 欄位正確
- [ ] 用例二：只開 `deepampep30` → 同上
- [ ] 用例三：同時開 `ampep` + `rfampep30` + `deepampep30` → CSV 各欄位正確填充
- [ ] 服務不可用 → 自動回退本地 R 腳本，流程仍成功
- [ ] FASTA id 與 `.out` 完整對齊（抽樣比對）
- [ ] 大輸入檔（邊界）在超時上限內行為符合預期

### 9. 文件與移交
- [ ] 更新 `docs/DeepAmPEP30_MICROSERVICE_API_Guide.md`（若實作細節與預設端口有差異）
- [ ] 在 README 或部署說明新增 `.env` 範例與健康檢查使用方式
- [ ] 補充「常見錯誤與排查」段落（端口衝突、CORS、容器啟動、模型載入等）

---

## 架構與資料流（簡述）
- Job：`app/Jobs/AmPEPJob.php` 按 `methods` 依序執行 → 優先微服務，失敗回退本地 R 腳本
- Task 層：`app/Utils/TaskUtils.php` 呼叫微服務 Client → 以 FASTA 順序寫入 `<method>.out`
- 彙整：`app/Utils/FileUtils.php` → `writeAmPEPResultFile()`/`matchingAmPEP()` 讀取各 `<method>.out` 匯入 `classification.csv`/`score.csv`

> 注意：不改動 `FileUtils::matchingAmPEP()` 與 CSV 結構，僅確保 `<method>.out` 符合「空白分隔三欄」與 FASTA id 對齊。

---

## 風險與緩解
- 微服務回傳數量/順序與 FASTA 不一致 → 以 FASTA 索引對齊；缺漏記錄 warning，必要時以 `sequence` 二次匹配（成本較高，預設關閉）
- 標籤不一致（1/0、AMP/NON-AMP 等） → adapter 內標準化為 `AMP` / `non-AMP`
- 服務暫時不可用 → 指數退避 + 自動回退本地 R 腳本
- 可觀測性不足 → 健康檢查指令 + 請求/回應關鍵欄位與耗時記錄

---

## 參考檔案
- `app/Jobs/AmPEPJob.php`
- `app/Utils/TaskUtils.php`
- `app/Utils/FileUtils.php`
- `app/Console/Commands/CheckAmPEPMicroservice.php`
- `docs/DeepAmPEP30_MICROSERVICE_API_Guide.md`

---

## 進度標記說明
- [ ] 未開始
- [/] 進行中
- [x] 已完成

 