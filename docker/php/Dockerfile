FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql pgsql zip gd intl bcmath exif pcntl \
    && rm -rf /var/lib/apt/lists/*

# Node.js pour Vite / Tailwind
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Limite d'upload alignée avec la limite MVP (10 Mo par fichier)
RUN { \
      echo "upload_max_filesize=12M"; \
      echo "post_max_size=64M"; \
      echo "memory_limit=512M"; \
    } > /usr/local/etc/php/conf.d/docpilot.ini

WORKDIR /var/www/html
