FROM dunglas/frankenphp:1-php8.2

# Use the built-in extension installer for absolute reliability. (FrankenPHP already includes OPcache)
RUN install-php-extensions pdo_pgsql

# Copy application files
COPY . /app

# Copy Caddy configuration
COPY Caddyfile /etc/caddy/Caddyfile
