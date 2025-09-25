# PHP 8.1 FPM（自動支援多架構包括ARM）
FROM php:8.1-fpm

# 安裝依賴 (移除nginx，添加PostgreSQL支持和R環境)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    zip \
    unzip \
    supervisor \
    r-base \
    r-base-dev

# 清理apt緩存
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# 安裝PHP擴展（支持MySQL和PostgreSQL）
RUN docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd zip

# 安裝Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 設置工作目錄
WORKDIR /var/www/html

# 配置Git安全目錄
RUN git config --global --add safe.directory /var/www/html

# 複製composer文件
COPY composer.json composer.lock ./

# 安裝依賴，但不運行腳本
RUN composer install --no-scripts --no-autoloader --no-interaction --prefer-dist

# 複製所有文件
COPY . .

# 生成優化的自動加載文件
RUN composer dump-autoload --optimize

# 配置PHP和PHP-FPM
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# 配置Supervisor (只管理php-fpm)
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# 創建一個簡單的入口點腳本
RUN echo '#!/bin/bash\nphp artisan config:clear\nphp artisan cache:clear\nphp artisan route:clear\nchown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache\nexec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf' > /entrypoint.sh
RUN chmod +x /entrypoint.sh

# 設置權限
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 暴露PHP-FPM端口
EXPOSE 9000

# 設置入口點
ENTRYPOINT ["/entrypoint.sh"]
