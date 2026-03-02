#!/bin/bash

# ============================================
# Voting Nova - SAFE VPS Deployment Script
# ============================================
# This script checks for conflicts and won't break existing projects
# Run as: sudo bash deploy-safe.sh

set -e

PROJECT_DIR="/var/www/novatech"
DB_NAME="votingnova"
DB_USER="votingnova_user"
REPO_URL="https://github.com/codeswindler/votingnova.git"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo "=========================================="
echo "Voting Nova - SAFE Deployment"
echo "=========================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Please run as root or with sudo${NC}"
    exit 1
fi

# ============================================
# PRE-DEPLOYMENT CHECKS
# ============================================

echo -e "${BLUE}=== Pre-Deployment Safety Checks ===${NC}"
echo ""

# Check if Apache is running
if systemctl is-active --quiet apache2; then
    echo -e "${GREEN}✓ Apache is already running${NC}"
    APACHE_RUNNING=true
else
    echo -e "${YELLOW}⚠ Apache is not running. Will start it.${NC}"
    APACHE_RUNNING=false
fi

# Check if MySQL is running
if systemctl is-active --quiet mysql || systemctl is-active --quiet mariadb; then
    echo -e "${GREEN}✓ MySQL/MariaDB is already running${NC}"
    MYSQL_RUNNING=true
else
    echo -e "${YELLOW}⚠ MySQL is not running. Will start it.${NC}"
    MYSQL_RUNNING=false
fi

# Check for existing Apache sites
echo ""
echo -e "${BLUE}Checking existing Apache virtual hosts...${NC}"
EXISTING_SITES=$(apache2ctl -S 2>/dev/null | grep "namevhost" | wc -l || echo "0")
if [ "$EXISTING_SITES" -gt "0" ]; then
    echo -e "${YELLOW}⚠ Found $EXISTING_SITES existing virtual host(s)${NC}"
    echo "Existing sites:"
    apache2ctl -S 2>/dev/null | grep "namevhost" || true
    echo ""
    echo -e "${YELLOW}This deployment will ADD a new site, not replace existing ones.${NC}"
    echo -e "${YELLOW}The default site will NOT be disabled.${NC}"
    DISABLE_DEFAULT=false
else
    echo -e "${GREEN}✓ No existing virtual hosts found${NC}"
    DISABLE_DEFAULT=true
fi

# Check if port 6000 is available (using custom port to avoid conflicts)
echo ""
echo -e "${BLUE}Checking port availability...${NC}"
APACHE_PORT=6000
if netstat -tuln 2>/dev/null | grep -q ":$APACHE_PORT "; then
    PORT_IN_USE=$(netstat -tuln 2>/dev/null | grep ":$APACHE_PORT " | head -1)
    echo -e "${YELLOW}⚠ Port $APACHE_PORT is in use:${NC}"
    echo "   $PORT_IN_USE"
    echo -e "${RED}   Please free port $APACHE_PORT or choose a different port${NC}"
    exit 1
else
    echo -e "${GREEN}✓ Port $APACHE_PORT is available${NC}"
    echo -e "${BLUE}   Using port $APACHE_PORT for Apache (to avoid conflicts)${NC}"
fi

# Check if project directory exists
if [ -d "$PROJECT_DIR" ] && [ "$(ls -A $PROJECT_DIR 2>/dev/null)" ]; then
    echo ""
    echo -e "${YELLOW}⚠ Directory $PROJECT_DIR already exists and is not empty${NC}"
    echo -e "${YELLOW}   Will pull/update repository if it's a git repo${NC}"
fi

# Check if database exists
if mysql -u root -e "USE $DB_NAME;" 2>/dev/null; then
    echo ""
    echo -e "${YELLOW}⚠ Database '$DB_NAME' already exists${NC}"
    echo -e "${YELLOW}   Will use existing database (schema will be imported if tables don't exist)${NC}"
fi

echo ""
echo -e "${BLUE}=== Summary ===${NC}"
echo "  • Project Directory: $PROJECT_DIR"
echo "  • Database Name: $DB_NAME"
echo "  • Database User: $DB_USER"
echo "  • Domain: voting.novotechafrica.co.ke"
echo "  • Apache Port: 6000 (to avoid conflicts)"
echo "  • Will NOT disable existing Apache sites"
echo "  • Will NOT break existing projects"
echo ""
read -p "Continue with deployment? (y/N): " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${RED}Deployment cancelled${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}=== Starting Deployment ===${NC}"
echo ""

# ============================================
# DEPLOYMENT STEPS
# ============================================

# Step 1: Install packages (only if not installed)
echo "[1/11] Checking/Installing packages..."
if ! command -v apache2 &> /dev/null; then
    apt update -qq
    apt install -y apache2 mysql-server php php-mysql php-curl php-json php-mbstring git curl
else
    echo "  ✓ Required packages already installed"
fi

# Step 2: Enable Apache modules
echo "[2/11] Enabling Apache modules..."
a2enmod -q rewrite ssl headers 2>/dev/null || true

# Step 3: Configure Apache for port 6000
echo "[3/11] Configuring Apache for port 6000..."
APACHE_PORT=6000

# Update ports.conf to listen on port 6000
if ! grep -q "Listen $APACHE_PORT" /etc/apache2/ports.conf 2>/dev/null; then
    echo "Listen $APACHE_PORT" >> /etc/apache2/ports.conf
    echo "  ✓ Added Listen $APACHE_PORT to ports.conf"
else
    echo "  ✓ Port $APACHE_PORT already configured"
fi

# Step 4: Start services if not running
echo "[4/11] Starting services..."
if [ "$APACHE_RUNNING" = false ]; then
    # Check if port 6000 is available
    if netstat -tuln 2>/dev/null | grep -q ":$APACHE_PORT "; then
        echo -e "${RED}  ✗ Port $APACHE_PORT is already in use${NC}"
        netstat -tulpn 2>/dev/null | grep ":$APACHE_PORT " || true
        exit 1
    fi
    
    # Now try to start Apache
    if systemctl start apache2; then
        systemctl enable apache2
        echo "  ✓ Apache started successfully on port $APACHE_PORT"
    else
        echo -e "${RED}  ✗ Failed to start Apache${NC}"
        echo "  Run: sudo systemctl status apache2"
        echo "  Run: sudo apache2ctl configtest"
        exit 1
    fi
else
    echo "  ✓ Apache is already running"
fi
if [ "$MYSQL_RUNNING" = false ]; then
    systemctl start mysql 2>/dev/null || systemctl start mariadb
    systemctl enable mysql 2>/dev/null || systemctl enable mariadb
fi

# Step 5: Create directory
echo "[5/11] Creating project directory..."
mkdir -p $PROJECT_DIR
chown -R $SUDO_USER:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR

# Step 6: Clone repository
echo "[6/11] Cloning/Updating repository..."
cd $PROJECT_DIR
if [ -d ".git" ]; then
    echo "  ✓ Repository exists, pulling latest..."
    git pull -q || echo "  ⚠ Git pull had issues, continuing..."
else
    git clone -q $REPO_URL .
fi

# Step 7: Setup .env
echo "[7/11] Setting up environment file..."
if [ ! -f ".env" ]; then
    cp env.example .env
    echo -e "${YELLOW}  ⚠ IMPORTANT: Edit .env file with your production credentials${NC}"
    echo "     Run: nano $PROJECT_DIR/.env"
    echo ""
    read -p "  Press Enter after editing .env file..."
else
    echo "  ✓ .env file already exists"
fi

# Step 8: Database setup
echo "[8/11] Setting up database..."
echo "  Enter MySQL root password:"
read -s MYSQL_ROOT_PASS

# Check if database exists
DB_EXISTS=$(mysql -u root -p"$MYSQL_ROOT_PASS" -e "SHOW DATABASES LIKE '$DB_NAME';" 2>/dev/null | grep -c "$DB_NAME" || echo "0")

if [ "$DB_EXISTS" -eq "0" ]; then
    # Generate secure password
    DB_PASS=$(openssl rand -base64 12 2>/dev/null || date +%s | sha256sum | base64 | head -c 12)
    
    mysql -u root -p"$MYSQL_ROOT_PASS" <<EOF
CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF
    echo "  ✓ Database and user created"
    echo -e "${YELLOW}  ⚠ Database Password: $DB_PASS${NC}"
    echo -e "${YELLOW}     Update this in your .env file!${NC}"
else
    echo "  ✓ Database already exists, checking user..."
    USER_EXISTS=$(mysql -u root -p"$MYSQL_ROOT_PASS" -e "SELECT User FROM mysql.user WHERE User='$DB_USER';" 2>/dev/null | grep -c "$DB_USER" || echo "0")
    if [ "$USER_EXISTS" -eq "0" ]; then
        DB_PASS=$(openssl rand -base64 12 2>/dev/null || date +%s | sha256sum | base64 | head -c 12)
        mysql -u root -p"$MYSQL_ROOT_PASS" <<EOF
CREATE USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF
        echo "  ✓ Database user created"
        echo -e "${YELLOW}  ⚠ Database Password: $DB_PASS${NC}"
    else
        echo "  ✓ Database user already exists"
    fi
fi

# Step 9: Import schema (only if tables don't exist)
echo "[9/11] Importing database schema..."
TABLE_COUNT=$(mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME -e "SHOW TABLES;" 2>/dev/null | wc -l || echo "0")
if [ "$TABLE_COUNT" -le "1" ]; then
    mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME < database/schema.sql
    mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME < database/migrate_user_tables.sql
    mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME < database/migrate_user_passwords.sql
    mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME < database/seed_settings.sql
    echo "  ✓ Database schema imported"
else
    echo "  ✓ Database tables already exist, skipping import"
fi

# Step 10: Apache virtual host (SAFE - won't disable existing sites)
echo "[10/11] Configuring Apache virtual host..."
APACHE_PORT=6000
if [ -f "/etc/apache2/sites-available/voting-nova.conf" ]; then
    echo "  ✓ Virtual host config already exists"
else
    cat > /etc/apache2/sites-available/voting-nova.conf <<EOF
<VirtualHost *:$APACHE_PORT>
    ServerName voting.novotechafrica.co.ke
    ServerAlias www.voting.novotechafrica.co.ke
    DocumentRoot /var/www/novatech

    <Directory /var/www/novatech>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/voting-nova-error.log
    CustomLog \${APACHE_LOG_DIR}/voting-nova-access.log combined
</VirtualHost>
EOF
    echo "  ✓ Virtual host config created for port $APACHE_PORT"
fi

# Enable site (won't disable others)
a2ensite -q voting-nova.conf 2>/dev/null || echo "  ✓ Site already enabled"

# Only disable default if no other sites exist
if [ "$DISABLE_DEFAULT" = true ]; then
    a2dissite -q 000-default.conf 2>/dev/null || true
    echo "  ✓ Default site disabled (no other sites found)"
else
    echo "  ✓ Site enabled (keeping existing sites active)"
fi

# Test Apache config before reloading
if apache2ctl configtest 2>/dev/null | grep -q "Syntax OK"; then
    systemctl reload apache2
    echo "  ✓ Apache reloaded successfully"
else
    echo -e "${RED}  ✗ Apache configuration error!${NC}"
    apache2ctl configtest
    exit 1
fi

# Step 11: Permissions
echo "[11/11] Setting file permissions..."
chown -R www-data:www-data $PROJECT_DIR
find $PROJECT_DIR -type d -exec chmod 755 {} \;
find $PROJECT_DIR -type f -exec chmod 644 {} \;
[ -f ".env" ] && chmod 640 .env && chown www-data:www-data .env
echo "  ✓ Permissions set"

# Step 12: Firewall (SAFE - only adds rules, doesn't enable if disabled)
APACHE_PORT=6000
echo "[12/12] Configuring firewall..."
if ufw status | grep -q "Status: active"; then
    ufw allow 22/tcp 2>/dev/null || true
    ufw allow $APACHE_PORT/tcp 2>/dev/null || true
    echo "  ✓ Firewall rules added for port $APACHE_PORT"
else
    echo -e "${YELLOW}  ⚠ Firewall is not active, skipping rules${NC}"
    echo "     You may want to enable it manually: sudo ufw enable"
fi

echo ""
echo -e "${GREEN}=========================================="
echo "✅ Deployment Complete!"
echo "==========================================${NC}"
echo ""
APACHE_PORT=6000
echo -e "${BLUE}Summary:${NC}"
echo "  • Project installed at: $PROJECT_DIR"
echo "  • Domain: voting.novotechafrica.co.ke"
echo "  • Apache Port: $APACHE_PORT"
echo "  • Access URL: http://voting.novotechafrica.co.ke:$APACHE_PORT"
echo "  • Existing projects: ${GREEN}NOT AFFECTED${NC}"
echo "  • Existing Apache sites: ${GREEN}STILL ACTIVE${NC}"
echo ""
echo -e "${YELLOW}Next Steps:${NC}"
echo ""
echo "1. Update .env file with production credentials"
echo ""
echo "2. Access admin dashboard:"
echo "   http://voting.novotechafrica.co.ke:$APACHE_PORT/admin/"
echo ""
echo "3. Configure callbacks (update URLs to include port $APACHE_PORT):"
echo "   - Advanta USSD: http://voting.novotechafrica.co.ke:$APACHE_PORT/api/ussd.php"
echo "   - M-Pesa: http://voting.novotechafrica.co.ke:$APACHE_PORT/api/mpesa-callback.php"
echo ""
echo "4. Optional: Set up reverse proxy (Nginx) to forward port 80/443 to $APACHE_PORT"
echo ""
