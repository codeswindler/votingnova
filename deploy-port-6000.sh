#!/bin/bash

# ============================================
# Voting Nova - Port 6000 Deployment (Non-Intrusive)
# ============================================
# This script ONLY sets up port 6000, doesn't touch existing services
# Run as: sudo bash deploy-port-6000.sh

set -e

PROJECT_DIR="/var/www/novatech"
DB_NAME="votingnova"
DB_USER="votingnova_user"
REPO_URL="https://github.com/codeswindler/votingnova.git"
APP_PORT=6000

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo "=========================================="
echo "Voting Nova - Port 6000 Deployment"
echo "=========================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Please run as root or with sudo${NC}"
    exit 1
fi

# Check if port 6000 is available
echo "Checking port $APP_PORT availability..."
if netstat -tuln 2>/dev/null | grep -q ":$APP_PORT "; then
    echo -e "${RED}✖ Port $APP_PORT is already in use${NC}"
    exit 1
else
    echo -e "${GREEN}✓ Port $APP_PORT is available${NC}"
fi

# Step 1: Install packages (if not installed)
echo ""
echo "[1/8] Checking packages..."
if ! command -v php &> /dev/null; then
    apt update
    apt install -y php php-mysql php-curl php-json php-mbstring git
    echo "  ✓ PHP installed"
else
    echo "  ✓ PHP already installed"
fi

if ! command -v mysql &> /dev/null; then
    apt install -y mysql-server
    echo "  ✓ MySQL installed"
else
    echo "  ✓ MySQL already installed"
fi

# Step 2: Create project directory
echo ""
echo "[2/8] Creating project directory..."
mkdir -p $PROJECT_DIR
chown -R $SUDO_USER:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
echo "  ✓ Directory created: $PROJECT_DIR"

# Step 3: Clone/Update repository
echo ""
echo "[3/8] Cloning/updating repository..."
cd $PROJECT_DIR
if [ -d ".git" ]; then
    echo "  Pulling latest changes..."
    git pull || echo "  ⚠ Git pull failed, continuing..."
else
    echo "  Cloning repository..."
    git clone $REPO_URL .
fi
echo "  ✓ Repository ready"

# Step 4: Setup environment
echo ""
echo "[4/8] Setting up environment file..."
if [ ! -f ".env" ]; then
    cp env.example .env
    echo -e "${YELLOW}  ⚠ IMPORTANT: Edit .env file with your production credentials${NC}"
    echo "     Run: nano $PROJECT_DIR/.env"
    echo ""
    read -p "  Press Enter after editing .env file..."
else
    echo "  ✓ .env file already exists"
fi

# Step 5: Database setup
echo ""
echo "[5/8] Setting up database..."
echo "  Enter MySQL root password:"
read -s MYSQL_ROOT_PASS

# Check if database exists
DB_EXISTS=$(mysql -u root -p"$MYSQL_ROOT_PASS" -e "SHOW DATABASES LIKE '$DB_NAME';" 2>/dev/null | grep -c "$DB_NAME" || echo "0")

if [ "$DB_EXISTS" -eq "0" ]; then
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
    echo "  ✓ Database already exists"
fi

# Step 6: Import schema
echo ""
echo "[6/8] Importing database schema..."
TABLE_COUNT=$(mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME -e "SHOW TABLES;" 2>/dev/null | wc -l || echo "0")
if [ "$TABLE_COUNT" -le "1" ]; then
    if [ -f "database/schema.sql" ]; then
        mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME < database/schema.sql
    fi
    if [ -f "database/migrate_user_tables.sql" ]; then
        mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME < database/migrate_user_tables.sql
    fi
    if [ -f "database/migrate_user_passwords.sql" ]; then
        mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME < database/migrate_user_passwords.sql
    fi
    if [ -f "database/seed_settings.sql" ]; then
        mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME < database/seed_settings.sql
    fi
    echo "  ✓ Database schema imported"
else
    echo "  ✓ Database tables already exist"
fi

# Step 7: Create systemd service for PHP built-in server (better than Apache for this use case)
echo ""
echo "[7/8] Creating systemd service for port $APP_PORT..."

# Create service file
cat > /etc/systemd/system/voting-nova.service <<EOF
[Unit]
Description=Voting Nova PHP Application
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=$PROJECT_DIR
ExecStart=/usr/bin/php -S 0.0.0.0:$APP_PORT -t $PROJECT_DIR
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

# Reload systemd and enable service
systemctl daemon-reload
systemctl enable voting-nova.service
echo "  ✓ Systemd service created"

# Step 8: Start service
echo ""
echo "[8/8] Starting application on port $APP_PORT..."
systemctl start voting-nova.service
sleep 2

if systemctl is-active --quiet voting-nova.service; then
    echo -e "${GREEN}  ✓ Application is running on port $APP_PORT${NC}"
    echo ""
    echo "=========================================="
    echo -e "${GREEN}Deployment Complete!${NC}"
    echo "=========================================="
    echo ""
    echo "Application URL: http://voting.novotechafrica.co.ke:$APP_PORT"
    echo "Admin Dashboard: http://voting.novotechafrica.co.ke:$APP_PORT/admin/"
    echo ""
    echo "Service management:"
    echo "  Status:  sudo systemctl status voting-nova"
    echo "  Stop:    sudo systemctl stop voting-nova"
    echo "  Start:   sudo systemctl start voting-nova"
    echo "  Restart: sudo systemctl restart voting-nova"
    echo "  Logs:    sudo journalctl -u voting-nova -f"
else
    echo -e "${RED}  ✖ Failed to start service${NC}"
    echo "  Check logs: sudo journalctl -u voting-nova -n 50"
    exit 1
fi
