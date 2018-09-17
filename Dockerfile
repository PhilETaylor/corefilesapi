FROM alpine:3.8

ADD https://repos.php.earth/alpine/phpearth.rsa.pub /etc/apk/keys/phpearth.rsa.pub

RUN echo "https://repos.php.earth/alpine/v3.8" >> /etc/apk/repositories

RUN apk update

# PHP

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
    php7.3-pdo_mysql    \
    php7.3-mysqli       \
    php7.3-tokenizer    \
    php7.3-dom          \
    php7.3-xml          \
    php7.3-simplexml    \
    php7.3-json         \
    php7.3-sodium       \
    php7.3-opcache      \
    php7.3-shmop        \
    php7.3-zlib         \
    php7.3-xmlwriter    \
    php7.3-common       \
    php7.3-xmlreader    \
    php7.3-xml          \
    composer            \
    supervisor          \
    git                 \
    openssh             \
    ca-certificates     \
    curl                \
    wget                \
    zlib-dev            \
    unzip               \
    tzdata              \
    nginx               \
    unzip               \
    procps

RUN update-ca-certificates

# PHP Configuration
RUN echo 'memory_limit=1024M' > /etc/php/7.3/conf.d/memory_limit.ini
RUN echo 'output_buffering=Off' > /etc/php/7.3/conf.d/output_buffering.ini
RUN echo '[global]' > /etc/php/7.3/php-fpm.d/zz-docker.conf
RUN echo 'daemonize = yes' >> /etc/php/7.3/php-fpm.d/zz-docker.conf
RUN echo '[www]' >> /etc/php/7.3/php-fpm.d/zz-docker.conf
RUN echo 'listen=9000' >> /etc/php/7.3/php-fpm.d/zz-docker.conf
RUN echo 'realpath_cache_size=2048M' > /etc/php/7.3/conf.d/pathcache.ini
RUN echo 'realpath_cache_ttl=7200' >> /etc/php/7.3/conf.d/pathcache.ini
RUN echo '[opcache]' > /etc/php/7.3/conf.d/opcache.ini
RUN echo 'opcache.memory_consumption = 512M' >> /etc/php/7.3/conf.d/opcache.ini
RUN echo 'opcache.max_accelerated_files = 1000000' >> /etc/php/7.3/conf.d/opcache.ini

RUN mkdir -p /run/nginx/
RUN mkdir -p /var/log/nginx/
COPY config/docker/prod/site.conf /etc/nginx/conf.d/site.conf
COPY config/docker/prod/nginx.conf /etc/nginx/nginx.conf
RUN rm /etc/nginx/conf.d/default.conf

#Supervisor
COPY config/docker/prod/supervisord.conf /etc/supervisord.conf
RUN mkdir -p /var/log/supervisord/

COPY . /var/www/html/
RUN cd /var/www/html/ && composer install
#ADD https://github.com/PhilETaylor/corefilesapi/archive/master.zip /var/www/html
#RUN unzip -o /var/www/html/master.zip /var/www/html/ && rm /var/www/html/master.zip

EXPOSE 80

CMD ["supervisord", "-c", "/etc/supervisord.conf"]