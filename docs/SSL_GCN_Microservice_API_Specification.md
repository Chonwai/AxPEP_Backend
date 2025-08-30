# SSL-GCN 微服務 API 規範

## 📋 概述

本文檔詳細描述了 SSL-GCN (Self-Supervised Learning Graph Convolutional Network) 微服務的 API 規範，用於蛋白質毒理學和生物活性預測。

**版本**: v1.0.0  
**基礎 URL**: `http://localhost:8005` (開發環境)  
**生產 URL**: `https://ssl-gcn-api.axpep.com` (生產環境)  
**協議**: HTTP/HTTPS  
**數據格式**: JSON  

## 🎯 服務概述

SSL-GCN 微服務提供基於圖卷積神經網路的蛋白質序列分析能力，支援：
- 蛋白質毒理學預測
- 生物活性評估
- 結構特徵分析
- 批量序列處理

## 🔗 API 端點

### 1. 健康檢查

#### `GET /health`

檢查服務健康狀態和可用性。

**請求範例**:
```http
GET /health HTTP/1.1
Host: localhost:8005
Accept: application/json
```

**響應範例**:
```json
{
  "status": "healthy",
  "version": "1.0.0",
  "timestamp": "2024-12-30T10:30:00Z",
  "service": "ssl-gcn",
  "models": {
    "toxicity": {
      "status": "loaded",
      "version": "ssl-gcn-tox-v2.1",
      "last_updated": "2024-12-25T14:20:00Z"
    },
    "bioactivity": {
      "status": "loaded", 
      "version": "ssl-gcn-bio-v1.8",
      "last_updated": "2024-12-20T09:15:00Z"
    }
  },
  "system": {
    "memory_usage": "2.1GB",
    "gpu_usage": "45%",
    "uptime": "24h 30m 15s",
    "requests_processed": 1250,
    "average_response_time": "28.5s"
  }
}
```

**狀態碼**:
- `200 OK` - 服務健康
- `503 Service Unavailable` - 服務不可用

---

### 2. 預測端點

#### `POST /predict/fasta`

對 FASTA 格式的蛋白質序列進行預測分析。

**請求範例**:
```http
POST /predict/fasta HTTP/1.1
Host: localhost:8005
Content-Type: application/json
Accept: application/json

{
  "fasta_content": ">seq1\nGLFDIVKKVVGALGSL\n>seq2\nALWKTMLKKLGTMALH\n>seq3\nMKLLILVTCLLAVAALA",
  "method": "toxicity",
  "precision": 6,
  "timeout": 300,
  "options": {
    "include_graph_features": true,
    "include_confidence": true,
    "batch_size": 32
  }
}
```

**請求參數**:
| 參數 | 類型 | 必需 | 描述 |
|------|------|------|------|
| `fasta_content` | string | ✅ | FASTA 格式的蛋白質序列 |
| `method` | string | ✅ | 預測方法 (`toxicity`, `bioactivity`) |
| `precision` | integer | ❌ | 結果精度 (預設: 6) |
| `timeout` | integer | ❌ | 超時時間，秒 (預設: 300) |
| `options` | object | ❌ | 額外選項 |

**成功響應範例**:
```json
{
  "status": "success",
  "request_id": "ssl-gcn-20241230-001",
  "processing_time": 28.5,
  "method": "toxicity",
  "timestamp": "2024-12-30T10:35:00Z",
  "results": [
    {
      "sequence_name": "seq1",
      "sequence": "GLFDIVKKVVGALGSL",
      "length": 16,
      "prediction": 0.823456,
      "confidence": 0.891234,
      "classification": "toxic",
      "graph_features": {
        "node_count": 16,
        "edge_count": 45,
        "clustering_coefficient": 0.234567,
        "average_path_length": 2.8,
        "graph_density": 0.375
      },
      "structural_features": {
        "alpha_helix": 0.625,
        "beta_sheet": 0.25,
        "random_coil": 0.125,
        "hydrophobic_ratio": 0.75
      },
      "status": "success"
    },
    {
      "sequence_name": "seq2",
      "sequence": "ALWKTMLKKLGTMALH", 
      "length": 16,
      "prediction": 0.156789,
      "confidence": 0.945123,
      "classification": "non-toxic",
      "graph_features": {
        "node_count": 16,
        "edge_count": 38,
        "clustering_coefficient": 0.198765,
        "average_path_length": 3.2,
        "graph_density": 0.317
      },
      "structural_features": {
        "alpha_helix": 0.5,
        "beta_sheet": 0.375,
        "random_coil": 0.125,
        "hydrophobic_ratio": 0.6875
      },
      "status": "success"
    }
  ],
  "metadata": {
    "model_version": "ssl-gcn-tox-v2.1",
    "graph_construction": "contact_map_8A",
    "feature_extraction": "gcn_3_layers",
    "total_sequences": 2,
    "successful_predictions": 2,
    "failed_predictions": 0
  }
}
```

**錯誤響應範例**:
```json
{
  "status": "error",
  "request_id": "ssl-gcn-20241230-002",
  "error_code": "INVALID_SEQUENCE",
  "error_message": "One or more sequences contain invalid amino acids",
  "timestamp": "2024-12-30T10:40:00Z",
  "details": {
    "invalid_sequences": [
      {
        "sequence_name": "seq3",
        "sequence": "MKLXILVTCLLAVAALA",
        "error": "Contains invalid amino acid: X"
      }
    ],
    "valid_sequences": 2,
    "invalid_sequences": 1
  }
}
```

**狀態碼**:
- `200 OK` - 請求成功
- `400 Bad Request` - 請求參數錯誤
- `413 Payload Too Large` - 序列數量超過限制
- `422 Unprocessable Entity` - 序列格式錯誤
- `500 Internal Server Error` - 服務器內部錯誤
- `503 Service Unavailable` - 服務暫時不可用

---

### 3. 批量預測端點

#### `POST /predict/batch`

處理大量序列的批量預測請求。

**請求範例**:
```http
POST /predict/batch HTTP/1.1
Host: localhost:8005
Content-Type: application/json

{
  "sequences": [
    {
      "id": "protein_001", 
      "sequence": "GLFDIVKKVVGALGSL"
    },
    {
      "id": "protein_002",
      "sequence": "ALWKTMLKKLGTMALH"
    }
  ],
  "method": "toxicity",
  "options": {
    "batch_size": 50,
    "parallel_processing": true
  }
}
```

---

### 4. 模型資訊端點

#### `GET /models`

獲取可用模型的詳細資訊。

**響應範例**:
```json
{
  "models": [
    {
      "name": "toxicity",
      "version": "ssl-gcn-tox-v2.1",
      "description": "SSL-GCN based protein toxicity prediction",
      "input_format": "amino_acid_sequence",
      "output_format": "probability_score",
      "performance": {
        "accuracy": 0.96,
        "precision": 0.94,
        "recall": 0.93,
        "f1_score": 0.935
      },
      "training_data": {
        "dataset_size": 15000,
        "last_updated": "2024-12-25T14:20:00Z"
      }
    },
    {
      "name": "bioactivity", 
      "version": "ssl-gcn-bio-v1.8",
      "description": "SSL-GCN based protein bioactivity prediction",
      "input_format": "amino_acid_sequence",
      "output_format": "activity_score",
      "performance": {
        "accuracy": 0.92,
        "precision": 0.91,
        "recall": 0.89,
        "f1_score": 0.9
      },
      "training_data": {
        "dataset_size": 12000,
        "last_updated": "2024-12-20T09:15:00Z"
      }
    }
  ]
}
```

---

### 5. 統計資訊端點

#### `GET /stats`

獲取服務使用統計資訊。

**響應範例**:
```json
{
  "service_stats": {
    "total_requests": 5420,
    "successful_requests": 5280,
    "failed_requests": 140,
    "average_response_time": "32.5s",
    "uptime": "168h 45m 30s"
  },
  "model_usage": {
    "toxicity": {
      "requests": 3200,
      "success_rate": 0.97
    },
    "bioactivity": {
      "requests": 2220,
      "success_rate": 0.95
    }
  },
  "performance_metrics": {
    "cpu_usage": "65%",
    "memory_usage": "2.8GB",
    "gpu_usage": "78%",
    "disk_usage": "45%"
  }
}
```

## 📊 支援的預測方法

### 1. Toxicity (毒理學預測)
- **方法名**: `toxicity`
- **描述**: 預測蛋白質的毒性潛力
- **輸出範圍**: 0.0 - 1.0 (0: 非毒性, 1: 高毒性)
- **閾值**: 0.5 (>0.5 為毒性)

### 2. Bioactivity (生物活性預測)  
- **方法名**: `bioactivity`
- **描述**: 預測蛋白質的生物活性
- **輸出範圍**: 0.0 - 1.0 (0: 無活性, 1: 高活性)
- **閾值**: 0.3 (>0.3 為有活性)

## ⚠️ 錯誤代碼

| 錯誤代碼 | 描述 | HTTP 狀態 |
|----------|------|-----------|
| `INVALID_SEQUENCE` | 序列包含無效氨基酸 | 422 |
| `SEQUENCE_TOO_LONG` | 序列長度超過限制 | 413 |
| `SEQUENCE_TOO_SHORT` | 序列長度不足 | 422 |
| `INVALID_METHOD` | 不支援的預測方法 | 400 |
| `INVALID_FORMAT` | FASTA 格式錯誤 | 422 |
| `TIMEOUT_EXCEEDED` | 處理超時 | 408 |
| `MODEL_NOT_LOADED` | 模型未加載 | 503 |
| `INSUFFICIENT_MEMORY` | 記憶體不足 | 503 |
| `GPU_ERROR` | GPU 處理錯誤 | 500 |

## 🔒 安全性

### 認證
```http
Authorization: Bearer <your-api-key>
```

### 請求限制
- **請求頻率**: 100 requests/minute
- **序列數量**: 最多 100 個序列/請求
- **序列長度**: 10-1000 氨基酸
- **請求大小**: 最大 10MB

### CORS 設定
```http
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization
```

## 📈 性能指標

### 預期性能
- **響應時間**: 15-45 秒 (取決於序列長度和數量)
- **吞吐量**: 50-100 序列/分鐘
- **並發支援**: 最多 10 個並行請求
- **可用性**: 99.9% SLA

### 資源需求
- **CPU**: 4 cores minimum, 8 cores recommended
- **記憶體**: 8GB minimum, 16GB recommended  
- **GPU**: NVIDIA GPU with 8GB+ VRAM (optional but recommended)
- **儲存**: 20GB for models and cache

## 🐳 Docker 部署

### 環境變數
```bash
# 服務配置
SSL_GCN_PORT=8005
SSL_GCN_HOST=0.0.0.0
SSL_GCN_WORKERS=4

# 模型配置  
MODEL_PATH=/app/models
CACHE_PATH=/app/cache
LOG_LEVEL=INFO

# 性能配置
MAX_BATCH_SIZE=100
DEFAULT_TIMEOUT=300
ENABLE_GPU=true
```

### Docker Compose 範例
```yaml
version: '3.8'
services:
  ssl-gcn:
    image: ssl-gcn:latest
    ports:
      - "8005:8005"
    environment:
      - SSL_GCN_PORT=8005
      - MODEL_PATH=/app/models
      - ENABLE_GPU=true
    volumes:
      - ./models:/app/models
      - ./cache:/app/cache
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8005/health"]
      interval: 30s
      timeout: 10s
      retries: 3
    deploy:
      resources:
        limits:
          memory: 16G
        reservations:
          memory: 8G
```

## 📚 使用範例

### Python 客戶端範例
```python
import requests
import json

class SSLGCNClient:
    def __init__(self, base_url="http://localhost:8005"):
        self.base_url = base_url
        
    def predict_toxicity(self, fasta_content):
        url = f"{self.base_url}/predict/fasta"
        payload = {
            "fasta_content": fasta_content,
            "method": "toxicity",
            "precision": 6
        }
        
        response = requests.post(url, json=payload)
        return response.json()

# 使用範例
client = SSLGCNClient()
fasta = ">test_seq\nGLFDIVKKVVGALGSL"
result = client.predict_toxicity(fasta)
print(f"Prediction: {result['results'][0]['prediction']}")
```

### PHP 客戶端範例 (Laravel)
```php
<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class SSLGCNMicroserviceClient
{
    private $client;
    private $baseUrl;
    
    public function __construct()
    {
        $this->baseUrl = env('SSL_GCN_MICROSERVICE_BASE_URL', 'http://localhost:8005');
        $this->client = new Client([
            'timeout' => env('SSL_GCN_MICROSERVICE_TIMEOUT', 300),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ]);
    }
    
    public function predict($fastaContent, $method = 'toxicity')
    {
        try {
            $response = $this->client->post($this->baseUrl . '/predict/fasta', [
                'json' => [
                    'fasta_content' => $fastaContent,
                    'method' => $method,
                    'precision' => 6,
                    'options' => [
                        'include_graph_features' => true,
                        'include_confidence' => true
                    ]
                ]
            ]);
            
            return json_decode($response->getBody(), true);
            
        } catch (RequestException $e) {
            throw new \Exception("SSL-GCN 微服務調用失敗: " . $e->getMessage());
        }
    }
    
    public function healthCheck()
    {
        try {
            $response = $this->client->get($this->baseUrl . '/health');
            $data = json_decode($response->getBody(), true);
            return $data['status'] === 'healthy';
        } catch (RequestException $e) {
            return false;
        }
    }
}
```

## 📝 版本歷史

### v1.0.0 (2024-12-30)
- 初始 API 版本
- 支援毒理學和生物活性預測
- 基本健康檢查和統計端點
- Docker 容器化支援

---

**文檔版本**: 1.0.0  
**最後更新**: 2024-12-30  
**維護團隊**: AxPEP Backend Team
