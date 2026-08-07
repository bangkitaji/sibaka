#!/bin/bash
# /opt/scripts/sibaka-backup.sh
# SIBAKA Portal - Daily Backup Script
# Schedule via cron: 0 2 * * * /opt/scripts/sibaka-backup.sh >> /var/log/sibaka-backup.log 2>&1
#
# Performs:
#   1. PostgreSQL database dump (gzipped)
#   2. Storage files archive (gzipped tar)
#   3. Retention cleanup (30 days)

set -euo pipefail

# Configuration
BACKUP_DIR="/opt/backups/sibaka"
DB_NAME="sibaka"
DB_USER="sibaka_user"
DB_HOST="127.0.0.1"
STORAGE_PATH="/var/www/sibaka/storage/app/public"
RETENTION_DAYS=30
DATE=$(date +%Y%m%d_%H%M%S)

# Ensure backup directory exists
mkdir -p "$BACKUP_DIR"

echo "[$DATE] Starting SIBAKA backup..."

# 1. PostgreSQL dump
echo "  Dumping PostgreSQL database..."
pg_dump -U "$DB_USER" -h "$DB_HOST" "$DB_NAME" | gzip > "$BACKUP_DIR/db_${DATE}.sql.gz"
DB_SIZE=$(du -h "$BACKUP_DIR/db_${DATE}.sql.gz" | cut -f1)
echo "  Database backup complete: db_${DATE}.sql.gz ($DB_SIZE)"

# 2. Storage files backup
echo "  Archiving storage files..."
if [ -d "$STORAGE_PATH" ]; then
    tar -czf "$BACKUP_DIR/storage_${DATE}.tar.gz" -C "$(dirname "$STORAGE_PATH")" "$(basename "$STORAGE_PATH")"
    STORAGE_SIZE=$(du -h "$BACKUP_DIR/storage_${DATE}.tar.gz" | cut -f1)
    echo "  Storage backup complete: storage_${DATE}.tar.gz ($STORAGE_SIZE)"
else
    echo "  WARNING: Storage path not found, skipping storage backup."
fi

# 3. Clean old backups (retention: 30 days)
echo "  Cleaning backups older than ${RETENTION_DAYS} days..."
DELETED=$(find "$BACKUP_DIR" -type f -mtime +$RETENTION_DAYS -print -delete | wc -l)
echo "  Removed $DELETED old backup file(s)."

# Summary
TOTAL_SIZE=$(du -sh "$BACKUP_DIR" | cut -f1)
echo "[$DATE] Backup completed. Total backup storage: $TOTAL_SIZE"
