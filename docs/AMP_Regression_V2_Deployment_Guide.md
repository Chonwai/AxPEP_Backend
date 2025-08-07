# AMP Regression V2 API 部署指南

## 🚀 概述

本指南說明如何在AxPEP_Backend系統中部署和使用新的AMP Regression V2 JSON API。

## ✅ 實現狀態

### 已完成的組件
- ✅ `AmpRegressionECSAPredictMicroserviceClientV2` - V2微服務客戶端
- ✅ `TaskUtils::runAmpRegressionEcSaPredictMicroserviceV2` - V2實現邏輯  
- ✅ 向後兼容機制和環境變數控制
- ✅ 核心邏輯單元測試
- ✅ API規範文檔

### 系統影響範圍
- **修改文件**: `app/Utils/TaskUtils.php` (添加V2方法)
- **新增文件**: `app/Services/AmpRegressionECSAPredictMicroserviceClientV2.php`
- **調用點**: 僅 `app/Jobs/AmPEPJob.php` 第71行
- **風險等級**: 🟢 極低 (完全向後兼容)

## 🔧 環境配置

### 步驟1: 添加環境變數

在 `.env` 文件中添加以下配置：

```bash
# 啟用AMP Regression V2 JSON API
USE_AMP_REGRESSION_V2_API=true

# 微服務基礎URL
# ⚠️ Docker環境中請使用 host.docker.internal 而不是 127.0.0.1
AMP_REGRESSION_EC_SA_PREDICT_BASE_URL=http://host.docker.internal:8889
```

### ⚠️ Docker網絡重要提醒

**如果您的Laravel應用運行在Docker容器中，必須使用正確的主機地址：**

| 部署環境 | 正確配置 | 說明 |
|----------|----------|------|
| **Docker容器** | `http://host.docker.internal:8889` | 容器內訪問宿主機服務 |
| **直接宿主機** | `http://127.0.0.1:8889` | 宿主機直接運行 |

**原因：** 在Docker容器內，`127.0.0.1` 指向容器本身，無法訪問宿主機上的微服務。

### 步驟2: 驗證配置

```bash
# 檢查環境變數
php artisan tinker
>>> env('USE_AMP_REGRESSION_V2_API')
>>> env('AMP_REGRESSION_EC_SA_PREDICT_BASE_URL')
```

## 📋 分階段部署策略

### Phase 1: 測試環境部署
```bash
# 在測試環境啟用V2 API
USE_AMP_REGRESSION_V2_API=true

# 提交AmPEP任務進行測試
# 檢查日誌：storage/logs/laravel.log
```

### Phase 2: 生產環境灰度發布
```bash
# 初始設置（保持V1）
USE_AMP_REGRESSION_V2_API=false

# 確認系統穩定後，啟用V2
USE_AMP_REGRESSION_V2_API=true
```

### Phase 3: 完全切換到V2
- 監控V2 API的成功率和性能
- 確認所有功能正常後，可以考慮移除V1代碼

## 🔍 監控和日誌

### 關鍵日誌信息
```
# V2 API成功調用
[INFO] 嘗試使用AMP Regression V2 JSON API，TaskID: {task_id}
[INFO] AMP Regression V2 API調用成功，TaskID: {task_id}

# V2 API失敗，回退到V1
[ERROR] AMP Regression V2 API調用失敗，回退到V1文件傳輸，TaskID: {task_id}

# V2結果保存
[INFO] AMP Regression V2 結果已保存: {path}，預測數量: {count}
```

### 健康檢查命令
```bash
# 檢查V2微服務健康狀態
curl http://127.0.0.1:8889/health

# 檢查服務信息
curl http://127.0.0.1:8889/api/info
```

## 🧪 驗證步驟

### 1. 核心邏輯測試
```bash
php tests/test_amp_regression_v2_simple.php
```

### 2. 完整集成測試
```bash
# 提交AmPEP測試任務
# 查看任務結果文件：storage/app/Tasks/{task_id}/amp_activity_prediction.csv
```

### 3. 性能對比
- V1: 3次操作 (文件複製 + HTTP調用 + 文件複製)
- V2: 1次操作 (JSON HTTP調用)

## 🔄 回退方案

如果V2 API出現問題，可以即時回退：

```bash
# 立即回退到V1
USE_AMP_REGRESSION_V2_API=false

# 重啟應用程序（如果使用緩存）
php artisan config:clear
php artisan cache:clear
```

## 🎯 預期優勢

### 技術優勢
- **性能提升**: 從3次操作減少到1次操作
- **容器友好**: 無需文件系統掛載
- **錯誤處理**: 標準HTTP狀態碼和響應
- **監控友好**: 完整的請求追蹤日誌

### 運維優勢
- **部署簡化**: 無需配置共享文件系統
- **水平擴展**: 支持多實例並發
- **故障隔離**: 減少外部依賴點

## 🔍 故障排除

### 常見問題
1. **V2 API調用失敗**
   - 檢查微服務是否運行：`curl http://127.0.0.1:8889/health`
   - 檢查網絡連接和防火牆設置
   - 查看Laravel日誌中的詳細錯誤信息

2. **結果文件格式問題**
   - 確認CSV文件生成：`storage/app/Tasks/{task_id}/amp_activity_prediction.csv`
   - 檢查文件內容格式是否正確

3. **環境變數不生效**
   - 清除配置緩存：`php artisan config:clear`
   - 確認.env文件語法正確

## 🏆 成功指標

- ✅ V2 API調用成功率 > 95%
- ✅ 平均響應時間 < V1響應時間
- ✅ 零破壞性變更
- ✅ 結果文件格式完全一致
