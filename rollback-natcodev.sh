#!/bin/bash
# NATCODEV Rollback Script
# Usage: ./rollback-natcodev.sh YYYYMMDD_HHMMSS

if [ -z "$1" ]; then
    echo "Usage: ./rollback-natcodev.sh BACKUP_TIMESTAMP"
    exit 1
fi

BACKUP_DIR="/var/backups/natcodev"
SITE_DIR="/var/www/html"
TIMESTAMP=$1

echo "🔄 Rolling back to $TIMESTAMP..."

# Restore site
rm -rf $SITE_DIR
cp -r $BACKUP_DIR/site_backup_$TIMESTAMP $SITE_DIR

# Restore database (optional - uncomment if needed)
# mysql -u coconutventure_growers -p$DB_PASS coconutventure_growers < $BACKUP_DIR/db_backup_$TIMESTAMP.sql

systemctl reload apache2
echo "✅ Rollback completed!"