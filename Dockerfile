FROM php:8.2-apache

# Instala extensões PHP/MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilita módulo rewrite do Apache
RUN a2enmod rewrite

# Configura DirectoryIndex e permissões
RUN echo '<Directory /var/www/html/>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
    DirectoryIndex index.php login.php index.html\n\
</Directory>' > /etc/apache2/conf-available/sistema.conf \
    && a2enconf sistema

# Copia todo o código do repositório para o Apache
COPY . /var/www/html/

# Ajusta permissões
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
