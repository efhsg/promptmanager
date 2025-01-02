#!/bin/bash
# /docker-entrypoint-initdb.d/init-databases.sh

# Grant CREATE privilege on all databases to MYSQL_USER
mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "GRANT ALL ON *.* TO '$MYSQL_USER'@'%'; FLUSH PRIVILEGES;"

# Create test database using MYSQL_USER
mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS \`$MYSQL_DATABASE_TEST\`;"
