FROM php:8.2-cli-alpine
WORKDIR /app
COPY bot.php /app/bot.php
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} bot.php"]
