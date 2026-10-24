# Образ сайта рецептов: PHP + Apache. pdo_sqlite в официальном образе уже включён.
FROM php:8.3-apache

# Настройки PHP для загрузки фото
RUN { \
      echo 'upload_max_filesize = 6M'; \
      echo 'post_max_size = 8M'; \
      echo 'expose_php = Off'; \
    } > /usr/local/etc/php/conf.d/recipes.ini

# Защита служебных папок задана в конфиге Apache, а не в .htaccess:
# data/ и uploads/ в k8s монтируются с диска, и .htaccess из образа там не будет.
COPY docker/apache-site.conf /etc/apache2/conf-enabled/recipes.conf

COPY --chown=www-data:www-data . /var/www/html/wife-recipes/

RUN rm -rf /var/www/html/wife-recipes/docker /var/www/html/wife-recipes/k8s \
 && mkdir -p /var/www/html/wife-recipes/data /var/www/html/wife-recipes/uploads \
 && chown -R www-data:www-data /var/www/html/wife-recipes/data /var/www/html/wife-recipes/uploads

EXPOSE 80
