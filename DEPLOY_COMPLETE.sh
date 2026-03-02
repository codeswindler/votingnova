#!/bin/bash
# ============================================
# COMPLETE DEPLOYMENT - Copy and paste this entire script
# ============================================
# Run: sudo bash DEPLOY_COMPLETE.sh

set -e

PROJECT_DIR="/var/www/novatech"
DB_NAME="votingnova"
DB_USER="votingnova_user"
REPO_URL="https://github.com/codeswindler/votingnova.git"

echo "=========================================="
echo "Voting Nova - Complete Deployment"
echo "=========================================="

# Step 1: Install packages
echo "[1/10] Installing packages..."
apt update -qq
apt install -y apache2 mysql-server php php-mysql php-curl php-json php-mbstring git curl

# Step 2: Enable Apache modules
echo "[2/10] Enabling Apache modules..."
a2enmod -q rewrite ssl headers

# Step 3: Create directory
echo "[3/10] Creating project directory..."
mkdir -p $PROJECT_DIR
chown -R $SUDO_USER:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR

# Step 4: Clone repository
echo "[4/10] Cloning repository..."
cd $PROJECT_DIR
if [ -d ".git" ]; then
    git pull -q
else
    git clone -q $REPO_URL .
fi

# Step 5: Setup .env
echo "[5/10] Setting up environment file..."
if [ ! -f ".env" ]; then
    cp env.example .env
    echo "⚠️  IMPORTANT: Edit .env file with your credentials!"
    echo "   Run: nano $PROJECT_DIR/.env"
    echo ""
    echo "Press Enter to continue after editing .env..."
    read
fi

# Step 6: Database setup
echo "[6/10] Setting up database..."
echo "Enter MySQL root password:"
read -s MYSQL_ROOT_PASS

# Generate secure password
DB_PASS=$(openssl rand -base64 12 2>/dev/null || date +%s | sha256sum | base64 | head -c 12)

mysql -u root -p"$MYSQL_ROOT_PASS" <<EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF

echo "✅ Database created!"
echo "   User: $DB_USER"
echo "   Password: $DB_PASS"
echo "   ⚠️  Update this in .env file!"

# Step 7: Import schema
echo "[7/10] Importing database schema..."
mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME < database/schema.sql
mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME < database/migrate_user_tables.sql
mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME < database/migrate_user_passwords.sql
mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME < database/seed_settings.sql

# Step 8: Apache virtual host
echo "[8/10] Configuring Apache..."
cat > /etc/apache2/sites-available/voting-nova.conf <<'EOF'
<VirtualHost *:80>
    ServerName voting.novotechafrica.co.ke
    ServerAlias www.voting.novotechafrica.co.ke
    DocumentRoot /var/www/novatech

    <Directory /var/www/novatech>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/voting-nova-error.log
    CustomLog ${APACHE_LOG_DIR}/voting-nova-access.log combined
</VirtualHost>
EOF

a2ensite -q voting-nova.conf
a2dissite -q 000-default.conf 2>/dev/null || true
systemctl reload apache2

# Step 9: Permissions
echo "[9/10] Setting permissions..."
chown -R www-data:www-data $PROJECT_DIR
find $PROJECT_DIR -type d -exec chmod 755 {} \;
find $PROJECT_DIR -type f -exec chmod 644 {} \;
chmod 640 $PROJECT_DIR/.env

# Step 10: Firewall
echo "[10/10] Configuring firewall..."
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

echo ""
echo "=========================================="
echo "✅ Deployment Complete!"
echo "=========================================="
echo ""
echo "📝 Next Steps:"
echo ""
echo "1. Update .env with database credentials:"
echo "   DB_USER=$DB_USER"
echo "   DB_PASS=$DB_PASS"
echo ""
echo "2. Install SSL certificate:"
echo "   apt install -y certbot python3-certbot-apache"
echo "   certbot --apache -d voting.novotechafrica.co.ke"
echo ""
echo "3. Configure callbacks:"
echo "   - Advanta USSD: https://voting.novotechafrica.co.ke/api/ussd.php"
echo "   - M-Pesa: https://voting.novotechafrica.co.ke/api/mpesa-callback.php"
echo ""
echo "4. Access admin: https://voting.novotechafrica.co.ke/admin/"
echo ""
