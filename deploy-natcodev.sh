#!/bin/bash
# NATCODEV Deployment Script - Zero Downtime
# Usage: ./deploy-natcodev.sh

set -e

echo "🚀 Starting NATCODEV Deployment..."

# Configuration
BACKUP_DIR="/var/backups/natcodev"
SITE_DIR="/var/www/html"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="natcodevcom_data"
DB_USER="natcodevcom_data"

# Step 1: Create backup directory
mkdir -p $BACKUP_DIR

# Step 2: Backup database
echo "💾 Backing up database..."
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/db_backup_$DATE.sql

# Step 3: Backup current site
echo "📁 Backing up current site..."
cp -r $SITE_DIR $BACKUP_DIR/site_backup_$DATE

# Step 4: Pull latest code (if using git)
# git pull origin main

# Step 5: Create asset directories
echo "🖼️ Setting up brand assets..."
mkdir -p $SITE_DIR/assets/{logo,seals,hero,css}

# Step 6: Upload new files (replace with your actual file list)
FILES_TO_UPLOAD=(
    "lib/twilio.php"
    "lib/payments.php"
    "lib/ussd.php"
    "api/iot/ingest.php"
    "api/imagery/upload.php"
    "dashboard/farm-health.php"
    "field-agent/app.js"
    "admin/agent-map.php"
    "assets/logo/natcodev-logo.png"
    "assets/seals/naic.png"
    # Add all your files here
)

for file in "${FILES_TO_UPLOAD[@]}"; do
    if [ -f "./$file" ]; then
        cp "./$file" "$SITE_DIR/$file"
        echo "✅ Deployed: $file"
    fi
done

# Step 7: Set permissions
echo "🔐 Setting permissions..."
chmod 644 $SITE_DIR/assets/*/*
chmod 755 $SITE_DIR/uploads $SITE_DIR/resources $SITE_DIR/certificates

# Step 8: Apply database schema updates
echo "📊 Updating database schema..."
mysql -u $DB_USER -p$DB_PASS $DB_NAME << EOF
-- Document verification table
CREATE TABLE IF NOT EXISTS document_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_type ENUM('nin', 'bvn', 'land_title', 'id_card', 'farm_photo') NOT NULL,
    file_path VARCHAR(255),
    verified TINYINT(1) DEFAULT 0,
    verified_by INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id)
);

-- Feature flags
INSERT IGNORE INTO settings (key_name, value) VALUES 
('iot_module_enabled', '0'),
('satellite_module_enabled', '0'),
('analytics_module_enabled', '0');
EOF

# Step 9: Clear cache
echo "🧹 Clearing cache..."
rm -f $SITE_DIR/.user.ini
systemctl reload apache2

echo "✅ Deployment completed successfully!"
echo "Backup location: $BACKUP_DIR"
echo "Rollback command: ./rollback-natcodev.sh $DATE"