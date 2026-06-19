#!/bin/sh
set -e

# Asegurar que estamos en el directorio correcto
cd /var/www/html

# Cachear la configuración, rutas y vistas para un rendimiento óptimo en producción
echo "⚡ Generando caché de configuración, rutas y vistas de Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Verificar conexión a la base de datos y correr migraciones
if [ -n "$DB_HOST" ] && [ "$DB_HOST" != "127.0.0.1" ]; then
    echo "🔍 Esperando conexión a la base de datos ($DB_HOST)..."
    
    if php -r '
        $max_tries = 15;
        $tries = 0;
        $host = getenv("DB_HOST");
        $port = getenv("DB_PORT") ?: "3306";
        $database = getenv("DB_DATABASE");
        $username = getenv("DB_USERNAME");
        $password = getenv("DB_PASSWORD");
        
        echo "Intentando conectar a mysql:host=$host;port=$port;dbname=$database como usuario $username...\n";
        
        while ($tries < $max_tries) {
            try {
                $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database", $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 3
                ]);
                echo "✅ ¡Conexión con la base de datos establecida con éxito!\n";
                exit(0);
            } catch (PDOException $e) {
                $tries++;
                echo "⚠️ Intento $tries/$max_tries fallido. Esperando...\n";
                sleep(2);
            }
        }
        exit(1);
    '; then
        echo "🚀 Ejecutando migraciones de la base de datos..."
        php artisan migrate --force
    else
        echo "❌ ADVERTENCIA: No se pudo establecer conexión con la base de datos después de varios intentos."
        echo "Omitiendo migraciones por ahora. Iniciando servidor para facilitar el diagnóstico..."
    fi
else
    echo "ℹ️ DB_HOST no definido o es local. Omitiendo la comprobación de base de datos y migraciones automáticas..."
fi

# Iniciar Supervisor (que controlará Nginx y PHP-FPM)
echo "🎬 Iniciando procesos con Supervisor..."
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
