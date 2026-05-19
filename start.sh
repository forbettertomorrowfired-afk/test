#!/bin/bash
set -e

echo "Starting NexusSync container..."

# Fallback to 80 if PORT is not provided by Railway
PORT="${PORT:-80}"
echo "Configuring Apache to listen on port $PORT"

# Safely replace port 80 with the dynamic PORT in Apache configurations
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

echo "Running database migrations..."
php sql/migrate.php || echo "Migrations failed or skipped, continuing startup..."

echo "Enforcing MPM prefork..."
a2dismod mpm_event mpm_worker || true
a2enmod mpm_prefork || true

echo "Starting Apache..."
exec apache2-foreground
