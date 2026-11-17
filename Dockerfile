FROM php:8.2-apache
RUN docker-php-ext-install pdo_mysql \
    && a2dismod -f mpm_event mpm_worker \
    && a2enmod mpm_prefork
COPY docker/apache-site.conf /etc/apache2/sites-available/000-default.conf
COPY docker/start.sh /usr/local/bin/autotrack-start
COPY .htaccess /var/www/html/autotrackplus/.htaccess
COPY actions /var/www/html/autotrackplus/actions
COPY bin /var/www/html/autotrackplus/bin
COPY config /var/www/html/autotrackplus/config
COPY public /var/www/html/autotrackplus/public
COPY sql /var/www/html/autotrackplus/sql
COPY tests /var/www/html/autotrackplus/tests
RUN chmod +x /usr/local/bin/autotrack-start
WORKDIR /var/www/html/autotrackplus
CMD ["autotrack-start"]
