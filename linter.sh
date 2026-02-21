#!/bin/bash
if [ -z "$1" ]; then
    echo "Usage: ./linter.sh <check|fix>"
    exit 1
fi

# Resolve APP_ROOT with fallback
if [ -z "$APP_ROOT" ]; then
    APP_ROOT=/var/www/worktree/main
    echo "APP_ROOT not set, using fallback: $APP_ROOT"
fi

# Validate APP_ROOT
if echo "$APP_ROOT" | grep -qE '(^|/)\.\.(/|$)'; then
    echo "ERROR: APP_ROOT invalid | value='$APP_ROOT' | reason='contains ..' | fix='Gebruik pad onder /var/www/worktree/'"
    exit 2
fi
if ! echo "$APP_ROOT" | grep -qE '^/var/www/worktree/[A-Za-z0-9._/-]+$'; then
    echo "ERROR: APP_ROOT invalid | value='$APP_ROOT' | reason='does not match allowlist' | fix='Gebruik pad onder /var/www/worktree/'"
    exit 2
fi

docker exec pma_yii vendor/bin/php-cs-fixer $1 --config "${APP_ROOT}/linterConfig.php"
