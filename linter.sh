#!/bin/bash
if [ -z "$1" ]; then
    echo "Usage: ./linter.sh <check|fix>"
    exit 1
fi
docker exec pma_yii vendor/bin/php-cs-fixer $1 --config /var/www/html/linterConfig.php
