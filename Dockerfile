FROM node:24 AS encore
WORKDIR /build
COPY package*.json /build/
RUN npm install
COPY webpack.config.js /build/
ADD assets /build/assets
RUN npm run build
FROM php:8.4-fpm-alpine3.20
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS imagemagick-dev geos-dev git
RUN apk add nginx imagemagick geos supervisor protoc redis
RUN pecl install imagick protobuf redis && \
    git clone https://git.osgeo.org/gitea/geos/php-geos.git /usr/src/php/ext/geos && cd /usr/src/php/ext/geos && \
        	./autogen.sh && ./configure && make && \
        mv /usr/src/php/ext/geos/modules/geos.so /usr/local/lib/php/extensions/no-debug-non-zts-20240924/geos.so
RUN docker-php-ext-enable imagick geos protobuf redis
RUN apk del -f .build-deps && rm -rf /tmp/* /var/cache/apk/*
COPY docker/nginx /etc/nginx/http.d
COPY docker/supervisor /etc/supervisor.d
COPY --from=composer /usr/bin/composer /usr/bin/composer
WORKDIR /var/www
ADD . /var/www
COPY --from=encore /build/public/build /var/www/public/build
RUN composer install && \
    ln -s /var/www/bin/console /usr/bin/symfony && mkdir -p /var/run/php
CMD ["supervisord", "-c", "/etc/supervisord.conf", "--nodaemon"]
