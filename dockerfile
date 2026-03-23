FROM php:8.2-apache

# ติดตั้ง PostgreSQL extension
RUN apt-get update && apt-get install -y libpq-dev && docker-php-ext-install pdo_pgsql

# เปิดใช้งาน mod_rewrite (optional)
RUN a2enmod rewrite

# คัดลอกไฟล์ทั้งหมดไปยัง web root
COPY . /var/www/html/

# ตั้งค่าสิทธิ์ให้ uploads directory
RUN chown -R www-data:www-data /var/www/html/uploads && chmod -R 755 /var/www/html/uploads

# เปิดพอร์ต 80
EXPOSE 80
