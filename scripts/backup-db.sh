#!/bin/bash
#
# Daily database backup to Google Drive
# Backs up promptmanager MySQL database and uploads to Google Drive
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Load database credentials from .env
source "$PROJECT_DIR/.env"

# Configuration
BACKUP_DIR="/tmp/promptmanager_backups"
RETENTION_DAYS=30

# Google Drive folder ID (find via: right-click folder in Drive > Get link > extract ID from URL)
GDRIVE_FOLDER_ID="1OKY3B49FWW16dBMOUsDyKVnPuLkXmXOC"

# Map .env variable names
DB_NAME="$DB_DATABASE"

# Timestamp for backup file
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="promptmanager_${TIMESTAMP}.sql.gz"

# Create backup directory if needed
mkdir -p "$BACKUP_DIR"

echo "[$(date)] Starting backup..."

# Create database dump via Docker (using MYSQL_PWD to avoid password in process list)
docker exec -e MYSQL_PWD="$DB_PASSWORD" pma_mysql mysqldump \
    -u"$DB_USER" \
    --single-transaction \
    --routines \
    --triggers \
    "$DB_NAME" | gzip > "$BACKUP_DIR/$BACKUP_FILE"

echo "[$(date)] Database dumped to $BACKUP_FILE"

# Upload to Google Drive
rclone copy "$BACKUP_DIR/$BACKUP_FILE" "gdrive:" \
    --drive-root-folder-id="$GDRIVE_FOLDER_ID" \
    --log-level INFO

echo "[$(date)] Uploaded to Google Drive"

# Clean up local backup
rm -f "$BACKUP_DIR/$BACKUP_FILE"

# Remove old backups from Google Drive (keep last RETENTION_DAYS days)
if ! rclone delete "gdrive:" \
    --drive-root-folder-id="$GDRIVE_FOLDER_ID" \
    --min-age "${RETENTION_DAYS}d" \
    --log-level INFO 2>&1; then
    echo "[$(date)] Warning: Failed to clean up old backups"
fi

echo "[$(date)] Backup completed successfully"
