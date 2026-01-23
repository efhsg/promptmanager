#!/bin/bash
#
# Restore database from Google Drive backup
# Usage: ./restore-db.sh <backup_filename.sql.gz|backup_filename.sql>
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Load database credentials from .env
source "$PROJECT_DIR/.env"

# Configuration
RESTORE_DIR="/tmp/promptmanager_restore"

# Google Drive folder ID (find via: right-click folder in Drive > Get link > extract ID from URL)
GDRIVE_FOLDER_ID="1OKY3B49FWW16dBMOUsDyKVnPuLkXmXOC"

# Map .env variable names
DB_NAME="$DB_DATABASE"

# Check argument
if [ -z "$1" ]; then
    echo "Usage: $0 <backup_filename>"
    echo ""
    echo "Available backups on Google Drive:"
    rclone ls "gdrive:" --drive-root-folder-id="$GDRIVE_FOLDER_ID"
    exit 1
fi

# Sanitize input to prevent path traversal
BACKUP_FILE="$(basename "$1")"

# Create restore directory
mkdir -p "$RESTORE_DIR"

echo "[$(date)] Downloading $BACKUP_FILE from Google Drive..."

# Download from Google Drive
rclone copy "gdrive:$BACKUP_FILE" "$RESTORE_DIR/" \
    --drive-root-folder-id="$GDRIVE_FOLDER_ID" \
    --log-level INFO

if [ ! -f "$RESTORE_DIR/$BACKUP_FILE" ]; then
    echo "Error: Failed to download $BACKUP_FILE"
    exit 1
fi

echo "[$(date)] Downloaded. Starting restore..."

# Confirm before restore
read -p "This will overwrite the current database. Continue? (y/N) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Restore cancelled."
    rm -f "$RESTORE_DIR/$BACKUP_FILE"
    exit 0
fi

# Restore database (handle both .sql.gz and .sql files)
# Using MYSQL_PWD to avoid password in process list
if [[ "$BACKUP_FILE" == *.gz ]]; then
    gunzip -c "$RESTORE_DIR/$BACKUP_FILE" | docker exec -i -e MYSQL_PWD="$DB_PASSWORD" pma_mysql mysql \
        -u"$DB_USER" \
        "$DB_NAME"
else
    docker exec -i -e MYSQL_PWD="$DB_PASSWORD" pma_mysql mysql \
        -u"$DB_USER" \
        "$DB_NAME" < "$RESTORE_DIR/$BACKUP_FILE"
fi

echo "[$(date)] Database restored successfully from $BACKUP_FILE"

# Clean up
rm -f "$RESTORE_DIR/$BACKUP_FILE"

echo "[$(date)] Restore completed"
