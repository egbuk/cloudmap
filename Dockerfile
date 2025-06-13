FROM node:24-alpine AS encore
RUN apk add git woff2
WORKDIR /build
RUN git clone https://github.com/koemaeda/gohufont-ttf.git
COPY package*.json /build/
RUN npm install
COPY webpack.config.js /build/
ADD assets /build/assets
RUN woff2_compress gohufont-ttf/gohufont-11.ttf && \
    mv gohufont-ttf/*woff2 ./assets/styles
RUN npm run build
FROM php:8.4-fpm-alpine3.20
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS imagemagick-dev geos-dev git
RUN apk add nginx imagemagick geos supervisor protoc valkey
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
RUN composer install && crontab /var/www/docker/crontab && \
    ln -s /var/www/bin/console /usr/bin/symfony && mkdir -p /var/run/php && mkdir -p /var/valkey
CMD ["supervisord", "-c", "/etc/supervisord.conf", "--nodaemon"]
