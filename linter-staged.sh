#!/bin/bash
if [ -z "$1" ]; then
    echo "Usage: ./linter-staged.sh <check|fix>"
    exit 1
fi
FILES=$(git diff --cached --name-only --diff-filter=d | grep '\.php$')
if [ -n "$FILES" ]; then
    # Convert host paths to container paths
    CONTAINER_FILES=$(echo "$FILES" | sed 's|^|/var/www/html/|')
    docker exec pma_yii vendor/bin/php-cs-fixer $1 --sequential --config /var/www/html/linterConfig.php $CONTAINER_FILES
else
    echo "No staged PHP files to lint"
fi
