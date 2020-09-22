FROM php:7.4-apache

ENV GLUED_IN_DOCKER=true

RUN apt-get update && \
    apt-get install -y npm git zip netcat && \
    npm install --global yarn && \
    php -r "readfile('http://getcomposer.org/installer');" | php -- --install-dir=/usr/bin/ --filename=composer

ADD https://raw.githubusercontent.com/mlocati/docker-php-extension-installer/master/install-php-extensions /usr/local/bin/
ADD https://raw.githubusercontent.com/eficode/wait-for/master/wait-for /usr/local/bin/

RUN chmod uga+x /usr/local/bin/install-php-extensions && \
    chmod +x /usr/local/bin/wait-for && \
    sync && \
    install-php-extensions gd zip imap bcmath exif mysqli soap gmp pdo pdo_mysql

COPY private /var/www/glued/private
COPY composer.json package.json /var/www/glued/

RUN cd /var/www/glued && \
    composer update


COPY docker/apache-config.conf /etc/apache2/sites-available/glued.conf
COPY docker/phinx.docker.yml /var/www/glued/phinx.yml

RUN rm /etc/apache2/sites-enabled/* && \
    ln -s /etc/apache2/sites-available/glued.conf /etc/apache2/sites-enabled/glued.conf

# TODO: zdokumentovat volumes

ENV PATH="/var/www/glued/docker-scripts:${PATH}" \
    MYSQL_PORT=3306
    
COPY . /var/www/glued 
WORKDIR /var/www/glued
CMD ["start"]
