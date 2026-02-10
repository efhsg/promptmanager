#!/bin/bash
#
# Daily database backup to Google Drive
# Backs up promptmanager MySQL database and uploads to Google Drive
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Load only needed database credentials from .env
DB_NAME=$(grep '^DB_DATABASE=' "$PROJECT_DIR/.env" | cut -d= -f2-)
DB_USER=$(grep '^DB_USER=' "$PROJECT_DIR/.env" | cut -d= -f2-)
DB_PASSWORD=$(grep '^DB_PASSWORD=' "$PROJECT_DIR/.env" | cut -d= -f2-)

# Configuration
BACKUP_DIR="/tmp/promptmanager_backups"
RETENTION_DAILY=30    # Daily backups: keep 30 days
RETENTION_MONTHLY=365 # Monthly backups (1st of month): keep 1 year

# Google Drive folder ID (find via: right-click folder in Drive > Get link > extract ID from URL)
GDRIVE_FOLDER_ID="1OKY3B49FWW16dBMOUsDyKVnPuLkXmXOC"

# Timestamp and backup type (monthly on 1st of month, daily otherwise)
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DAY_OF_MONTH=$(date +%d)
if [ "$DAY_OF_MONTH" = "01" ]; then
    BACKUP_TYPE="monthly"
else
    BACKUP_TYPE="daily"
fi
BACKUP_FILE="promptmanager_${BACKUP_TYPE}_${TIMESTAMP}.sql.gz"

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

# Remove old daily backups (older than 30 days)
if ! rclone delete "gdrive:" \
    --drive-root-folder-id="$GDRIVE_FOLDER_ID" \
    --include "promptmanager_daily_*.sql.gz" \
    --min-age "${RETENTION_DAILY}d" \
    --log-level INFO 2>&1; then
    echo "[$(date)] Warning: Failed to clean up old daily backups"
fi

# Remove old monthly backups (older than 1 year)
if ! rclone delete "gdrive:" \
    --drive-root-folder-id="$GDRIVE_FOLDER_ID" \
    --include "promptmanager_monthly_*.sql.gz" \
    --min-age "${RETENTION_MONTHLY}d" \
    --log-level INFO 2>&1; then
    echo "[$(date)] Warning: Failed to clean up old monthly backups"
fi

echo "[$(date)] Backup completed successfully"
