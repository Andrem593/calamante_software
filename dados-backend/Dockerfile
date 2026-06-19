# ==========================================
# Etapa 1: Instalar dependencias de PHP (para obtener Ziggy y otros paquetes)
# ==========================================
FROM php:8.3-fpm-alpine AS php-dependencies
WORKDIR /var/www/html

# Instalar utilidades requeridas para Composer y extensiones
RUN apk add --no-cache git unzip zip libzip-dev

# Instalar extensión de zip necesaria para Composer
RUN docker-php-ext-install zip

# Copiar Composer desde su imagen oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar únicamente los archivos de Composer para aprovechar la caché de capas de Docker
COPY composer.json composer.lock ./

# Instalar dependencias de producción ignorando requisitos de plataforma temporalmente (las extensiones se instalan en la etapa final)
RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader --prefer-dist --ignore-platform-reqs

# ==========================================
# Etapa 2: Compilar assets frontend (Vite/Vue + Ziggy)
# ==========================================
FROM node:20-alpine AS assets-builder
WORKDIR /app

# Copiar dependencias de Node e instalar
COPY package*.json ./
RUN npm ci

# Copiar todo el código fuente
COPY . .

# Copiar la carpeta vendor desde la Etapa 1 (permite que Vite resuelva Ziggy)
COPY --from=php-dependencies /var/www/html/vendor ./vendor

# Compilar assets para producción
RUN npm run build

# ==========================================
# Etapa 3: Imagen Final de Producción (PHP + Nginx)
# ==========================================
FROM php:8.3-fpm-alpine
WORKDIR /var/www/html

# Instalar dependencias del sistema y Nginx/Supervisor
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    oniguruma-dev \
    libzip-dev \
    icu-dev \
    tzdata

# Instalar extensiones PHP para producción (incluyendo gd para phpspreadsheet)
RUN docker-php-ext-install \
    pdo_mysql \
    bcmath \
    gd \
    zip \
    intl \
    opcache

# Configurar Opcache para rendimiento óptimo
RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.enable_cli=1'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Copiar configuraciones personalizadas de Nginx y Supervisor
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copiar el código del backend
COPY . .

# Copiar la carpeta vendor pre-instalada desde la Etapa 1
COPY --from=php-dependencies /var/www/html/vendor ./vendor

# Copiar los assets de frontend ya compilados desde la Etapa 2
COPY --from=assets-builder /app/public/build ./public/build

# Crear directorios necesarios y ajustar permisos para Nginx, Supervisor y PHP-FPM
RUN mkdir -p /var/lib/nginx /var/log/nginx /var/tmp/nginx /run \
    && chown -R www-data:www-data /var/www/html \
    && chown -R www-data:www-data /var/lib/nginx \
    && chown -R www-data:www-data /var/log/nginx \
    && chown -R www-data:www-data /var/tmp/nginx \
    && chown -R www-data:www-data /run

# Copiar y hacer ejecutable el script de arranque (entrypoint)
COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Exponer puerto HTTP
EXPOSE 80

# Definir variables de entorno por defecto
ENV PORT=80
ENV APP_ENV=production
ENV APP_DEBUG=false

# Configurar el entrypoint
ENTRYPOINT ["docker-entrypoint.sh"]
