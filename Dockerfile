FROM php:7.0-zts
RUN pecl install pthreads
RUN docker-php-ext-enable pthreads
RUN curl --silent --show-error https://getcomposer.org/installer | php
RUN mv composer.phar /usr/bin/composer
RUN chmod +x /usr/bin/composer
RUN mkdir -p /var/www/phusey
RUN chown -R www-data:www-data /var/www
USER www-data
WORKDIR /var/www/phusey/
RUN echo "Now working in $(pwd) as user $(whoami)"
COPY --chown=www-data:www-data ./ /var/www/phusey/
RUN composer install 
RUN php -v

CMD [ "./phusey.sh" ]


