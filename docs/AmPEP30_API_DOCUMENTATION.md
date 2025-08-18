# AmPEP30 Microservice API 文檔

## 📋 概述

**服務名稱**: Deep-AmPEP30 Microservice  
**版本**: 1.0.0  
**端口**: 8002  
**基礎 URL**: `http://localhost:8002`

AmPEP30 是一個抗菌胜肽（Antimicrobial Peptide）預測服務，支持隨機森林（RF）和深度學習（CNN）兩種預測模型。

## 🔄 重要更新 - 統一響應格式

### 變更日期
2024年8月17日

### 變更內容
**統一了成功和錯誤響應的格式結構**，使後端集成更加便利：

#### 🔹 變更前
- 成功響應：完整的預測結果對象
- 錯誤響應：簡單的錯誤消息字符串

#### 🔹 變更後
- **成功和錯誤響應使用相同的結構**
- 錯誤響應包含完整的序列信息和空的預測字段
- 通過 `status` 字段區分成功/錯誤狀態
- 錯誤詳情在 `error` 字段中提供

## 🛠️ API 端點

### 1. 健康檢查
```http
GET /health
```

**響應示例**:
```json
{
  "status": ["healthy"],
  "service": ["AmPEP30-Final-API"],
  "version": ["1.0.0"],
  "timestamp": ["2024-08-17T17:33:53+0000"]
}
```

### 2. 單序列預測
```http
POST /predict/single
```

**請求格式**:
```json
{
  "sequence": "GLFDIVKKVVGALGSL",
  "method": "rf",           // 可選: "rf" 或 "cnn"，默認 "rf"
  "precision": 3            // 可選: 0-6，默認 3
}
```

**參數說明**:
- `sequence`: 氨基酸序列（必需，5-30個氨基酸）
- `method`: 預測方法（可選）
  - `"rf"`: 隨機森林模型
  - `"cnn"`: 深度學習模型
- `precision`: 數值精度（可選，0-6位小數）

### 3. FASTA 批量預測
```http
POST /predict/fasta
```

**請求格式**:
```json
{
  "fasta_content": ">seq1\nGLFDIVKKVVGALGSL\n>seq2\nALWKTMLKKLGTMALH",
  "method": "rf",
  "precision": 3
}
```

### 4. 模型信息
```http
GET /model/info
```

### 5. 測試演示
```http
GET /test/demo
```

## 📊 響應格式詳解

### 🟢 成功響應
```json
{
  "sequence_name": ["query"],
  "sequence": ["GLFDIVKKVVGALGSL"],
  "length": [16],
  "prediction": [1],
  "amp_probability": [0.882],
  "non_amp_probability": [0.118],
  "confidence": [0.882],
  "model_used": ["rf"],
  "interpretation": ["此序列很可能是抗菌胜肽 (機率: 88.2%)"],
  "status": ["success"]
}
```

### 🔴 錯誤響應
```json
{
  "sequence_name": ["query"],
  "sequence": ["ALWKTMLKKLGTMALHAGKAALGAAADTISQGTQ"],
  "length": [34],
  "prediction": [null],
  "amp_probability": [null],
  "non_amp_probability": [null],
  "confidence": [null],
  "model_used": ["rf"],
  "error": ["序列長度必須在 5-30 氨基酸之間，當前長度: 34"],
  "status": ["error"]
}
```

### 響應字段說明

| 字段 | 類型 | 說明 |
|------|------|------|
| `sequence_name` | Array[String] | 序列名稱 |
| `sequence` | Array[String] | 原始氨基酸序列 |
| `length` | Array[Integer] | 序列長度 |
| `prediction` | Array[Integer/null] | 預測結果 (1=AMP, 0=非AMP) |
| `amp_probability` | Array[Float/null] | AMP 機率 |
| `non_amp_probability` | Array[Float/null] | 非AMP 機率 |
| `confidence` | Array[Float/null] | 置信度 |
| `model_used` | Array[String] | 使用的模型 |
| `interpretation` | Array[String] | 結果解釋（僅成功時） |
| `error` | Array[String] | 錯誤信息（僅錯誤時） |
| `status` | Array[String] | 狀態: "success" 或 "error" |

## 🧪 使用示例

### 成功案例
```bash
curl -X POST "http://localhost:8002/predict/single" \
  -H "Content-Type: application/json" \
  -d '{"sequence": "GLFDIVKKVVGALGSL"}'
```

### 錯誤案例 - 序列過長
```bash
curl -X POST "http://localhost:8002/predict/single" \
  -H "Content-Type: application/json" \
  -d '{"sequence": "ALWKTMLKKLGTMALHAGKAALGAAADTISQGTQ"}'
```

### 錯誤案例 - 序列過短
```bash
curl -X POST "http://localhost:8002/predict/single" \
  -H "Content-Type: application/json" \
  -d '{"sequence": "ABC"}'
```

### 指定模型和精度
```bash
curl -X POST "http://localhost:8002/predict/single" \
  -H "Content-Type: application/json" \
  -d '{
    "sequence": "GLFDIVKKVVGALGSL",
    "method": "rf",
    "precision": 4
  }'
```

## ❌ 錯誤處理

### 常見錯誤類型

1. **序列長度錯誤**
   - 錯誤信息: `"序列長度必須在 5-30 氨基酸之間，當前長度: X"`
   - HTTP 狀態碼: 200 (但 status 為 "error")

2. **無效氨基酸**
   - 錯誤信息: `"序列包含無效氨基酸"`
   - 允許的氨基酸: `ACDEFGHIKLMNPQRSTVWY`

3. **無效方法**
   - 錯誤信息: `"不支持的方法"`
   - 支持的方法: `rf`, `cnn`

4. **JSON 格式錯誤**
   - HTTP 狀態碼: 400
   - 錯誤信息: JSON 解析錯誤

## 🔧 後端集成指南

### 1. 統一響應處理
```javascript
// JavaScript 示例
async function callAmPEP30API(sequence) {
  const response = await fetch('http://localhost:8002/predict/single', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ sequence })
  });
  
  const result = await response.json();
  
  // 統一處理邏輯
  if (result.status[0] === 'success') {
    // 處理成功結果
    console.log('預測結果:', result.prediction[0]);
    console.log('AMP 機率:', result.amp_probability[0]);
  } else {
    // 處理錯誤
    console.error('預測失敗:', result.error[0]);
  }
  
  return result;
}
```

### 2. 批量處理
```python
# Python 示例
import requests
import json

def predict_sequences(sequences, method='rf'):
    fasta_content = ''
    for i, seq in enumerate(sequences):
        fasta_content += f'>seq{i+1}\n{seq}\n'
    
    response = requests.post(
        'http://localhost:8002/predict/fasta',
        json={
            'fasta_content': fasta_content.strip(),
            'method': method
        }
    )
    
    return response.json()
```

### 3. 錯誤處理最佳實踐
```python
def handle_ampep30_response(response_data):
    """統一處理 AmPEP30 API 響應"""
    if response_data['status'][0] == 'success':
        return {
            'success': True,
            'prediction': response_data['prediction'][0],
            'probability': response_data['amp_probability'][0],
            'confidence': response_data['confidence'][0]
        }
    else:
        return {
            'success': False,
            'error': response_data['error'][0],
            'sequence': response_data['sequence'][0],
            'length': response_data['length'][0]
        }
```

## 🚀 部署信息

### Docker 部署
```bash
# 構建容器
docker compose build deep-ampep30

# 啟動服務
docker compose up -d deep-ampep30

# 檢查狀態
curl http://localhost:8002/health
```

### 環境變量
- `PLUMBER_PORT`: API 端口 (默認: 8002)
- `DEFAULT_METHOD`: 默認預測方法 (默認: rf)
- `MIN_SEQUENCE_LENGTH`: 最小序列長度 (默認: 5)
- `MAX_SEQUENCE_LENGTH`: 最大序列長度 (默認: 30)

## 📞 技術支持

如有問題或需要進一步的技術支持，請聯繫開發團隊。

---

**更新日期**: 2024年8月17日  
**文檔版本**: 1.1.0