FROM php:8.2-fpm

# TZ
RUN echo "date.timezone=Europe/Amsterdam" > /usr/local/etc/php/conf.d/timezone.ini

# Disable expose_php for security reasons
RUN echo "expose_php=Off" > /usr/local/etc/php/conf.d/security.ini

# Install system dependencies
RUN apt-get update && apt-get install -y \
    zip \
    unzip \
    git \
    curl \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libfreetype6-dev \
    libjpeg-dev \
    libicu-dev \
    libpq-dev \
    imagemagick \
    libmagickwand-dev \
    libmemcached-dev \
    zlib1g-dev \
    vim --no-install-recommends && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

RUN apt-get update \
 && apt-get install -y iputils-ping \
 && apt-get install -y netcat-openbsd \
 && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd intl pdo_pgsql

# Install and enable the Memcached extension
RUN pecl install memcached && docker-php-ext-enable memcached

# Install and enable the Imagick extension
RUN pecl install imagick && docker-php-ext-enable imagick  

# Install Xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Create Xdebug configuration file (host/port resolved from env at runtime)
RUN cat <<'EOF' > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
zend_extension=xdebug.so
xdebug.mode=debug,develop
xdebug.start_with_request=trigger
xdebug.discover_client_host=1
xdebug.client_host=${XDEBUG_CLIENT_HOST}
xdebug.client_port=${XDEBUG_CLIENT_PORT}
xdebug.idekey=PHPSTORM
xdebug.connect_timeout_ms=200
EOF

# Set max_execution_time
RUN echo "max_execution_time=300" > /usr/local/etc/php/conf.d/custom.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Arguments to create a user with specific UID and username
ARG USER_ID
ARG USER_NAME


# Create a user, add /data directory for persisting files, and change ownership
RUN adduser --uid $USER_ID --disabled-password --gecos "" $USER_NAME \
    && mkdir -p /data \
    && chown -R $USER_NAME:$USER_NAME /var/www/html /data


# Define the work directory as a build argument
ARG WORKDIR=/var/www/html/yii

# Set the working directory
WORKDIR $WORKDIR

# Add current directory (.) to the PATH
ENV PATH="$WORKDIR:${PATH}"

USER root

# Solves problem with PHPStorm
RUN mkdir -p /var/www/html/yii/vendor/bin && \
    ln -s ../autoload.php /var/www/html/yii/vendor/bin/autoload.php

ARG PHP_FPM_PORT

# Set PHP-FPM port
RUN sed -i "s|9000|${PHP_FPM_PORT}|" /usr/local/etc/php-fpm.d/www.conf
RUN sed -i "s|9000|${PHP_FPM_PORT}|" /usr/local/etc/php-fpm.d/zz-docker.conf

USER $USER_NAME

# Create the function and append it to ~/.bashrc
RUN echo 'function test() {' >> /home/$USER_NAME/.bashrc && \
    echo '    clear && vendor/bin/codecept run "$@"' >> /home/$USER_NAME/.bashrc && \
    echo '}' >> /home/$USER_NAME/.bashrc

# Start PHP-FPM
CMD ["php-fpm"]
