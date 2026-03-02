#!/bin/bash

# ============================================
# Voting Nova - Complete VPS Deployment Script
# ============================================
# This script automates the entire deployment process
# Run as: sudo bash deploy.sh

set -e  # Exit on any error

echo "=========================================="
echo "Voting Nova - VPS Deployment"
echo "=========================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
PROJECT_DIR="/var/www/novatech"
DB_NAME="votingnova"
DB_USER="votingnova_user"
REPO_URL="https://github.com/codeswindler/votingnova.git"

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Please run as root or with sudo${NC}"
    exit 1
fi

echo -e "${GREEN}Step 1: Installing required packages...${NC}"
apt update
apt install -y apache2 mysql-server php php-mysql php-curl php-json php-mbstring git curl

echo -e "${GREEN}Step 2: Enabling Apache modules...${NC}"
a2enmod rewrite
a2enmod ssl
a2enmod headers

echo -e "${GREEN}Step 3: Creating project directory...${NC}"
mkdir -p $PROJECT_DIR
chown -R $SUDO_USER:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR

echo -e "${GREEN}Step 4: Cloning repository...${NC}"
cd $PROJECT_DIR
if [ -d ".git" ]; then
    echo -e "${YELLOW}Repository already exists. Pulling latest changes...${NC}"
    git pull
else
    git clone $REPO_URL .
fi

echo -e "${GREEN}Step 5: Setting up environment file...${NC}"
if [ ! -f ".env" ]; then
    cp env.example .env
    echo -e "${YELLOW}Please edit .env file with your production credentials:${NC}"
    echo "  nano $PROJECT_DIR/.env"
    echo ""
    echo -e "${YELLOW}Press Enter after you've configured .env file...${NC}"
    read
else
    echo -e "${YELLOW}.env file already exists. Skipping...${NC}"
fi

echo -e "${GREEN}Step 6: Setting up database...${NC}"
echo -e "${YELLOW}Enter MySQL root password:${NC}"
read -s MYSQL_ROOT_PASS

# Generate random password for database user if not set
DB_PASS=$(openssl rand -base64 12)

echo "Creating database and user..."
mysql -u root -p"$MYSQL_ROOT_PASS" <<EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF

echo -e "${GREEN}Database created!${NC}"
echo -e "${YELLOW}Database User: $DB_USER${NC}"
echo -e "${YELLOW}Database Password: $DB_PASS${NC}"
echo -e "${YELLOW}Update this in your .env file!${NC}"
echo ""

echo -e "${GREEN}Step 7: Importing database schema...${NC}"
mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME < database/schema.sql
mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME < database/migrate_user_tables.sql
mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME < database/migrate_user_passwords.sql
mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME < database/seed_settings.sql

echo -e "${GREEN}Step 8: Creating Apache virtual host...${NC}"
cat > /etc/apache2/sites-available/voting-nova.conf <<'APACHE_CONFIG'
<VirtualHost *:80>
    ServerName voting.novotechafrica.co.ke
    ServerAlias www.voting.novotechafrica.co.ke
    
    DocumentRoot /var/www/novatech

    <Directory /var/www/novatech>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Logging
    ErrorLog ${APACHE_LOG_DIR}/voting-nova-error.log
    CustomLog ${APACHE_LOG_DIR}/voting-nova-access.log combined
</VirtualHost>
APACHE_CONFIG

echo -e "${GREEN}Step 9: Enabling Apache site...${NC}"
a2ensite voting-nova.conf
a2dissite 000-default.conf 2>/dev/null || true
systemctl reload apache2

echo -e "${GREEN}Step 10: Setting file permissions...${NC}"
chown -R www-data:www-data $PROJECT_DIR
find $PROJECT_DIR -type d -exec chmod 755 {} \;
find $PROJECT_DIR -type f -exec chmod 644 {} \;
chmod 640 $PROJECT_DIR/.env
chown www-data:www-data $PROJECT_DIR/.env

echo -e "${GREEN}Step 11: Configuring firewall...${NC}"
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

echo ""
echo -e "${GREEN}=========================================="
echo "Deployment Complete!"
echo "==========================================${NC}"
echo ""
echo -e "${YELLOW}Next Steps:${NC}"
echo ""
echo "1. Configure .env file with production credentials:"
echo "   nano $PROJECT_DIR/.env"
echo ""
echo "2. Update database credentials in .env:"
echo "   DB_USER=$DB_USER"
echo "   DB_PASS=$DB_PASS"
echo ""
echo "3. Set up SSL certificate (Let's Encrypt):"
echo "   apt install -y certbot python3-certbot-apache"
echo "   certbot --apache -d voting.novotechafrica.co.ke -d www.voting.novotechafrica.co.ke"
echo ""
echo "4. Configure Advanta USSD Callback:"
echo "   URL: https://voting.novotechafrica.co.ke/api/ussd.php"
echo ""
echo "5. Configure M-Pesa Callback:"
echo "   URL: https://voting.novotechafrica.co.ke/api/mpesa-callback.php"
echo ""
echo "6. Access admin dashboard:"
echo "   https://voting.novotechafrica.co.ke/admin/"
echo ""
echo -e "${GREEN}Done!${NC}"
