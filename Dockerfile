FROM php:8.1-cli

RUN docker-php-ext-install mysqli

WORKDIR /app

COPY . /app

CMD ["php", "-S", "0.0.0.0:8080", "-t", "."]
