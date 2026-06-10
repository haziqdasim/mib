FROM php:8.2-apache

# Install system deps + PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install curl

# Enable Apache rewrite (for future flexibility)
RUN a2enmod rewrite

# Set document root to project root
ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

# Copy project files
COPY . /var/www/html/

# Runtime permissions for slide uploads
RUN chown -R www-data:www-data /var/www/html/assets/slide /var/www/html/active_slide.txt 2>/dev/null; \
    chmod -R 755 /var/www/html/assets/slide 2>/dev/null; \
    touch /var/www/html/active_slide.txt && \
    chmod 664 /var/www/html/active_slide.txt
