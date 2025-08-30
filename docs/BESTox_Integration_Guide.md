# BESTox 微服務整合指南

## 🎯 整合概述

BESTox 微服務整合已完成！本系統現在支援透過 RESTful API 進行化學分子急性毒性預測，同時保持與舊版本 Python 腳本的完全向後相容性。

## ✅ 已實現的功能

### 核心組件
- ✅ **BESToxMicroserviceClient** - 完整的微服務客戶端
- ✅ **TaskUtils::runBESToxMicroservice** - 微服務呼叫邏輯
- ✅ **Feature Flag 機制** - 可控制的微服務切換
- ✅ **自動回退機制** - 微服務失敗時自動使用本地腳本
- ✅ **artisan bestox:health-check** - 健康檢查指令

### 資料格式相容性
- ✅ **輸入格式**: `input.smi` 檔案（SMILES 格式）
- ✅ **輸出格式**: `result.csv` 檔案（id,smiles,pre 三欄）
- ✅ **向後相容**: 與現有前端和下載功能完全相容

## 🔧 環境配置

### 必要的環境變數

在您的 `.env` 檔案中新增以下配置：

```bash
# BESTox 微服務配置
USE_BESTOX_MICROSERVICE=true
BESTOX_MICROSERVICE_BASE_URL=http://localhost:8006
BESTOX_MICROSERVICE_TIMEOUT=3600
```

### Docker 環境特別注意

如果您的 Laravel 應用運行在 Docker 容器中：

```bash
# Docker 環境使用
BESTOX_MICROSERVICE_BASE_URL=http://host.docker.internal:8006
```

### 現有配置已自動加入

系統已自動在 `config/services.php` 中加入 BESTox 配置：

```php
'bestox' => [
    'url' => env('BESTOX_MICROSERVICE_BASE_URL', 'http://localhost:8006'),
    'timeout' => env('BESTOX_MICROSERVICE_TIMEOUT', 3600),
    'enabled' => env('USE_BESTOX_MICROSERVICE', true),
],
```

## 🚀 使用方式

### 1. 健康檢查

檢查 BESTox 微服務是否正常運行：

```bash
php artisan bestox:health-check
```

輸出範例：
```
🔍 檢查 BESTox 微服務健康狀態...
✅ BESTox 微服務健康狀態：正常
   版本: 1.0.0
   時間: 2025-01-20T10:00:00.000000
   模型狀態: 已載入
📊 獲取模型信息...
✅ 模型信息獲取成功
   模型名稱: BESTox CNN
   模型版本: 1.0
🧪 執行簡單預測測試...
✅ 單一預測測試成功
🧪 執行批量預測測試...
✅ 批量預測測試成功
🎉 所有檢查完成！BESTox 微服務運行正常。
```

### 2. 任務處理

系統會自動根據環境變數選擇處理方式：

- **USE_BESTOX_MICROSERVICE=true**: 優先使用微服務，失敗時自動回退到本地腳本
- **USE_BESTOX_MICROSERVICE=false**: 直接使用本地 Python 腳本

### 3. 監控與日誌

所有微服務呼叫都會記錄在 `storage/logs/laravel.log` 中：

```
[2025-01-20 10:00:00] local.INFO: 嘗試使用 BESTox 微服務，TaskID: 12345
[2025-01-20 10:00:05] local.INFO: BESTox 微服務調用成功，TaskID: 12345
[2025-01-20 10:00:06] local.INFO: BESTox microservice results written: /path/to/result.csv, total molecules: 10
```

## 🔄 工作流程

### 微服務模式 (預設)
1. 使用者提交 SMILES 數據 → **BESToxController**
2. 建立任務 → **BESToxServices**
3. 分發到佇列 → **BESToxJob**
4. 讀取 `input.smi` → **TaskUtils::runBESToxMicroservice**
5. 呼叫微服務 API → **BESToxMicroserviceClient**
6. 解析結果並寫入 `result.csv`
7. 標記任務完成

### 回退模式 (微服務失敗時)
1. 微服務呼叫失敗 → 記錄錯誤日誌
2. 自動切換 → **TaskUtils::runBESToxTask**
3. 執行本地 Python 腳本 → `../BESTox/main.py`
4. 產生 `result.csv` 並完成任務

## 🧪 API 端點對應

### BESTox 微服務 API

| 端點 | 方法 | 用途 |
|------|------|------|
| `/health` | GET | 健康檢查 |
| `/model/info` | GET | 模型資訊 |
| `/status` | GET | 服務狀態 |
| `/predict/single` | POST | 單一分子預測 |
| `/predict/batch` | POST | 批量分子預測 |

### 請求/回應格式

#### 批量預測請求：
```json
{
  "batch_id": "task_12345",
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

#### 批量預測回應：
```json
{
  "success": true,
  "batch_id": "task_12345",
  "predictions": [
    {
      "molecule_id": "mol_1",
      "smiles": "CC(C)=CCO",
      "ld50": 1.032,
      "log10_ld50": -0.0136
    }
  ],
  "total_processed": 2,
  "total_successful": 2,
  "total_failed": 0
}
```

## 📊 效能優勢

### 微服務 vs 本地腳本

| 指標 | 本地腳本 | 微服務 |
|------|----------|--------|
| **啟動時間** | ~30s (Python 載入) | ~0.1s (HTTP 請求) |
| **記憶體使用** | 每次重新載入 | 持續載入，記憶體共享 |
| **並發處理** | 序列處理 | 支援並發請求 |
| **監控能力** | 有限 | 完整健康檢查與指標 |
| **部署彈性** | 綁定本地 | 可獨立擴展 |

## 🛠️ 故障排除

### 常見問題

#### 1. 微服務連接失敗
```bash
# 檢查微服務狀態
php artisan bestox:health-check

# 檢查網路連接
curl http://localhost:8006/health
```

#### 2. Docker 網路問題
```bash
# 確認使用正確的主機地址
BESTOX_MICROSERVICE_BASE_URL=http://host.docker.internal:8006
```

#### 3. 權限問題
```bash
# 確認 storage 目錄可寫
chmod -R 775 storage/
```

### 日誌位置

- **Laravel 日誌**: `storage/logs/laravel.log`
- **微服務日誌**: 請查看 BESTox 微服務容器日誌

## 🔐 安全考量

### 內部網路
- 微服務應僅在內部網路中可訪問
- 建議使用防火牆限制外部訪問端口 8006

### 輸入驗證
- 系統已內建 SMILES 格式驗證
- 自動過濾無效字符和超長序列

## 📈 監控建議

### 生產環境監控

1. **健康檢查**：定期執行 `php artisan bestox:health-check`
2. **效能監控**：監控 `/status` 端點回傳的指標
3. **日誌監控**：設定日誌告警，監控微服務失敗率
4. **資源監控**：監控微服務容器的 CPU 和記憶體使用

### 推薦工具

- **健康檢查**: 可整合到 Kubernetes readiness/liveness probes
- **指標收集**: Prometheus + Grafana
- **日誌分析**: ELK Stack (Elasticsearch, Logstash, Kibana)

## 🔄 升級與維護

### 微服務更新
1. 更新 BESTox 微服務容器
2. 執行健康檢查確認服務正常
3. 不需要重啟 Laravel 應用

### Laravel 端更新
- 微服務客戶端已完全解耦，更新時風險極低
- 所有變更向後相容

## 📞 技術支援

如遇到問題，請提供：
1. 健康檢查輸出: `php artisan bestox:health-check`
2. 相關日誌: `storage/logs/laravel.log`
3. 環境配置: `.env` 中的 BESTox 相關變數
4. 錯誤重現步驟

---

**整合完成時間**: 2025年1月20日  
**版本**: v1.0.0  
**維護團隊**: AxPEP Backend Team
