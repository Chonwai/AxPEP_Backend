#!/bin/bash
set -e

# Laravel應用設置
cd /var/www/html
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# 如果需要運行數據庫遷移（可選）
if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force
fi

# 確保目錄權限
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache

# 啟動Supervisor
exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
