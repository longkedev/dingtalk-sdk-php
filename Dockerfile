# 钉钉 PHP SDK 开发环境
FROM php:8.1-cli

# 设置工作目录
WORKDIR /app

# 安装系统依赖
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    && docker-php-ext-install \
    zip \
    intl \
    xml \
    && rm -rf /var/lib/apt/lists/*

# 安装 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 安装 Xdebug (用于代码覆盖率)
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# 配置 Xdebug
RUN echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# 复制项目文件
COPY . /app

# 安装依赖
RUN composer install --no-dev --optimize-autoloader

# 设置权限
RUN chown -R www-data:www-data /app

# 切换到非 root 用户
USER www-data

# 默认命令
CMD ["php", "-v"]