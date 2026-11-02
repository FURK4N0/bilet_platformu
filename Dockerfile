FROM php:8.2-apache

# Gerekli sistem kütüphaneleri ve PHP eklentileri (SQLite, Intl)
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libicu-dev \
    && docker-php-ext-install pdo pdo_sqlite intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Apache mod_rewrite aktif et ve .htaccess kurallarını geçerli kıl
RUN a2enmod rewrite
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Proje dosyalarını kopyala
COPY . /var/www/html/

# SQLite veritabanı klasörü ve dosyasını web sunucusu için yazılabilir yap
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html \
    && chmod -R 777 /var/www/html/database

# Render dinamik $PORT desteği (varsayılan 80 veya 10000)
EXPOSE 80 10000
CMD sh -c 'sed -i "s/Listen 80/Listen ${PORT:-80}/g" /etc/apache2/ports.conf && sed -i "s/:80/:${PORT:-80}/g" /etc/apache2/sites-available/000-default.conf && apache2-foreground'