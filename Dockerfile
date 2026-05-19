FROM dunglas/frankenphp:1-php8.2

# Install PostgreSQL PDO extension and OPcache
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo_pgsql opcache \
    && rm -rf /var/lib/apt/lists/*

# Configure OPcache for extreme performance
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=8'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.revalidate_freq=2'; \
        echo 'opcache.fast_shutdown=1'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Copy application files
COPY . /app

# Copy Caddy configuration
COPY Caddyfile /etc/caddy/Caddyfile
