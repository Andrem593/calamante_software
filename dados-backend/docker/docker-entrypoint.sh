#!/bin/sh
set -e

# Asegurar que estamos en el directorio correcto
cd /var/www/html

# Cachear la configuración, rutas y vistas para un rendimiento óptimo en producción
echo "⚡ Generando caché de configuración, rutas y vistas de Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Ejecutar migraciones de la base de datos de forma segura
echo "🚀 Ejecutando migraciones de la base de datos..."
php artisan migrate --force || true

# Iniciar Supervisor (que controlará Nginx y PHP-FPM)
echo "🎬 Iniciando procesos con Supervisor..."
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
