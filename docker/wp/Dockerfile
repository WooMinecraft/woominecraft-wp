FROM wordpress:5-php7.1-fpm-alpine

RUN apk add --no-cache $PHPIZE_DEPS \
	&& pecl install xdebug-2.5.0 \
	&& docker-php-ext-enable xdebug