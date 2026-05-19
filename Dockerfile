FROM dunglas/frankenphp:1-php8.2

# Use the built-in extension installer for absolute reliability
RUN install-php-extensions pdo_pgsql opcache

# Copy application files
COPY . /app

# Copy Caddy configuration
COPY Caddyfile /etc/caddy/Caddyfile
