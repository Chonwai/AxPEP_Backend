# ARM CPU 部署指南

## ARM CPU 兼容性

您的Docker配置已經針對ARM CPU進行了優化：

### 自動支援的組件
- ✅ PHP 8.1 官方映像支援ARM64
- ✅ Redis Alpine 映像支援ARM64
- ✅ Composer 官方映像支援ARM64

### 在ARM伺服器上的部署步驟

1. **確認系統架構**
   ```bash
   uname -m  # 應該顯示 aarch64 或 arm64
   ```

2. **安裝Docker（如果未安裝）**
   ```bash
   # Ubuntu/Debian ARM64
   curl -fsSL https://get.docker.com -o get-docker.sh
   sudo sh get-docker.sh
   ```

3. **部署應用**
   ```bash
   ./deploy-docker.sh
   ```

### 性能考量

**ARM CPU優勢：**
- 更低的功耗
- 現代ARM處理器性能優秀
- 成本效益高

**注意事項：**
- 首次建構可能較慢（需要下載ARM64映像）
- 某些第三方擴展可能需要重新編譯
- 記憶體和CPU限制可能需要根據實際硬體調整

### 建議配置

對於生物信息學計算任務，建議：
- 最少 8GB RAM
- 4+ CPU核心
- SSD存儲以提升I/O性能

### 故障排除

如果遇到架構相關問題：
```bash
# 檢查Docker是否支援多架構
docker buildx ls

# 強制使用ARM64
docker compose -f docker/docker-compose.yml build --platform linux/arm64
```
