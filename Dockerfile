FROM alpine:latest

ADD https://repos.php.earth/alpine/phpearth.rsa.pub /etc/apk/keys/phpearth.rsa.pub

RUN echo "https://repos.php.earth/alpine/v3.8" >> /etc/apk/repositories

RUN apk update

RUN apk add --no-cache  \
    php7.3              \
    php7.3-fpm          \
    php7.3-curl         \
    php7.3-ctype        \
    php7.3-zip          \
    php7.3-mbstring     \
    php7.3-pcntl        \
    php7.3-posix        \
    php7.3-iconv        \
    php7.3-intl         \
    php7.3-session      \
    php7.3-tokenizer    \
    php7.3-dom          \
    php7.3-xml          \
    php7.3-simplexml    \
    php7.3-json         \
    php7.3-sodium       \
    php7.3-opcache      \
    php7.3-shmop        \
    php7.3-zlib         \
    php7.3-sysvsem      \
    php7.3-xmlwriter    \
    php7.3-common       \
    php7.3-fileinfo     \
    php7.3-xmlreader    \
    php7.3-xml          \
    composer            \
    supervisor          \
    git                 \
    openssh             \
    openssl             \
    ca-certificates     \
    curl                \
    wget                \
    zlib-dev            \
    unzip               \
    tzdata              \
    nginx               \
    nano                \
    unzip               \
    procps

RUN update-ca-certificates

# PHP Configuration
RUN echo 'memory_limit=1024M' > /etc/php/7.3/conf.d/memory_limit.ini \
&& echo 'output_buffering=Off' > /etc/php/7.3/conf.d/output_buffering.ini \
&& echo '[global]' > /etc/php/7.3/php-fpm.d/zz-docker.conf \
&& echo 'daemonize = yes' >> /etc/php/7.3/php-fpm.d/zz-docker.conf \
&& echo '[www]' >> /etc/php/7.3/php-fpm.d/zz-docker.conf \
&& echo 'listen=9000' >> /etc/php/7.3/php-fpm.d/zz-docker.conf \
&& echo 'clear_env=no' >> /etc/php/7.3/php-fpm.d/zz-docker.conf \
&& echo 'realpath_cache_size=2048M' > /etc/php/7.3/conf.d/pathcache.ini \
&& echo 'realpath_cache_ttl=7200' >> /etc/php/7.3/conf.d/pathcache.ini \
&& echo '[opcache]' > /etc/php/7.3/conf.d/opcache.ini \
&& echo 'opcache.memory_consumption = 512M' >> /etc/php/7.3/conf.d/opcache.ini \
&& echo 'opcache.max_accelerated_files = 1000000' >> /etc/php/7.3/conf.d/opcache.ini

RUN mkdir -p /run/nginx/
RUN mkdir -p /var/log/nginx/

# placeholder nginx configurations
COPY config/docker/prod/site.conf /site.conf
COPY config/docker/prod/sitessl.conf /sitessl.conf
COPY config/docker/prod/nginx.conf /etc/nginx/nginx.conf
RUN rm /etc/nginx/conf.d/default.conf

# entrypoint configurations
COPY config/docker/prod/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Other services
COPY config/docker/prod/crontab /etc/crontabs/root
COPY config/docker/prod/supervisord.conf /etc/supervisord.conf

# set up PHP web app correctly
COPY . /var/www/html/
RUN cd /var/www/html/ \
    && composer install \
    && chown -Rf www-data:www-data /var/www/html \
    && rm -Rf /var/www/html/var/* \
    && mkdir -p /var/log/supervisord/

EXPOSE 80 443

ENTRYPOINT /entrypoint.sh