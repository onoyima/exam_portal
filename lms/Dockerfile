FROM php:8.2-apache

LABEL maintainer="Veritas University ICT <devops@veritas.edu.ng>"
LABEL version="1.0.0"
LABEL description="Veritas Integrated Learning Management System"

# System dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Configure GD with JPEG & Freetype support
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Install PHP extensions
RUN docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    intl \
    bcmath \
    gd \
    zip \
    opcache

# Install Redis extension via PECL
RUN pecl install redis && docker-php-ext-enable redis

# Enable Apache rewrite module
RUN a2enmod rewrite headers expires

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/local/bin/composer

# Set document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/lms/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Allow .htaccess override
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copy custom Apache config
COPY docker/apache/lms.conf /etc/apache2/sites-available/lms.conf
RUN a2ensite lms.conf && a2dissite 000-default.conf

# PHP production configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Custom PHP overrides for LMS
RUN echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/lms-overrides.ini \
    && echo "upload_max_filesize = 100M" >> /usr/local/etc/php/conf.d/lms-overrides.ini \
    && echo "post_max_size = 110M" >> /usr/local/etc/php/conf.d/lms-overrides.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/lms-overrides.ini \
    && echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/lms-overrides.ini \
    && echo "date.timezone = Africa/Lagos" >> /usr/local/etc/php/conf.d/lms-overrides.ini \
    && echo "opcache.enable = 1" >> /usr/local/etc/php/conf.d/lms-overrides.ini \
    && echo "opcache.memory_consumption = 128" >> /usr/local/etc/php/conf.d/lms-overrides.ini \
    && echo "opcache.interned_strings_buffer = 8" >> /usr/local/etc/php/conf.d/lms-overrides.ini \
    && echo "opcache.max_accelerated_files = 10000" >> /usr/local/etc/php/conf.d/lms-overrides.ini \
    && echo "opcache.revalidate_freq = 60" >> /usr/local/etc/php/conf.d/lms-overrides.ini \
    && echo "opcache.fast_shutdown = 1" >> /usr/local/etc/php/conf.d/lms-overrides.ini \
    && echo "session.save_handler = redis" >> /usr/local/etc/php/conf.d/lms-overrides.ini \
    && echo "session.save_path = tcp://redis:6379?auth=${REDIS_PASSWORD}&prefix=LMS_SESSION_" >> /usr/local/etc/php/conf.d/lms-overrides.ini

# Create storage directories with proper permissions
RUN mkdir -p /var/www/html/lms/storage/media \
    /var/www/html/lms/storage/logs \
    /var/www/html/lms/storage/cache \
    /var/www/html/lms/storage/tmp \
    && chown -R www-data:www-data /var/www/html/lms/storage \
    && chmod -R 775 /var/www/html/lms/storage

# Copy application files
COPY . /var/www/html/lms/

# Set working directory
WORKDIR /var/www/html/lms

# Install PHP dependencies (if composer.json exists)
RUN if [ -f composer.json ]; then composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader; fi

# Set permissions for web server
RUN chown -R www-data:www-data /var/www/html/lms \
    && chmod -R 755 /var/www/html/lms/public

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -f http://localhost/api/v1/health || exit 1

EXPOSE 80

CMD ["apache2-foreground"]
