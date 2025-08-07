FROM php:8.1-cli

WORKDIR /app

COPY . /app

CMD ["php", "-S", "0.0.0.0:8080", "-t", "."]
