# VPS Deployment Guide

This guide covers deploying the Voting Nova system to a VPS server at `/var/www/novatech`.

## Prerequisites

- VPS with Ubuntu/Debian Linux
- Root or sudo access
- Apache/Nginx web server
- PHP 8.0+ with required extensions
- MySQL/MariaDB
- Git installed
- SSL certificate (Let's Encrypt recommended)

## Deployment Steps

### 1. Server Preparation

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y apache2 mysql-server php php-mysql php-curl php-json php-mbstring git

# Enable Apache modules
sudo a2enmod rewrite
sudo a2enmod ssl
sudo a2enmod headers

# Start services
sudo systemctl start apache2
sudo systemctl start mysql
sudo systemctl enable apache2
sudo systemctl enable mysql
```

### 2. Create Project Directory

```bash
# Create directory
sudo mkdir -p /var/www/novatech

# Set ownership (replace 'www-data' with your web server user if different)
sudo chown -R $USER:www-data /var/www/novatech

# Set permissions
sudo chmod -R 755 /var/www/novatech
```

### 3. Clone Repository

```bash
cd /var/www/novatech
git clone https://github.com/codeswindler/votingnova.git .

# Or if you prefer a different branch:
# git clone -b production https://github.com/codeswindler/votingnova.git .
```

### 4. Configure Environment

```bash
# Copy environment template
cp env.example .env

# Edit .env file with production credentials
nano .env
```

**Required .env settings for production:**

```env
# Database
DB_HOST=localhost
DB_NAME=votingnova
DB_USER=votingnova_user
DB_PASS=your_secure_password_here

# Application
APP_URL=https://voting.novotechafrica.co.ke
APP_ENV=production
APP_DEBUG=false

# Advanta SMS/USSD
ADVANTA_API_KEY=a0141970388cbca1a9cf86cab3a12e8e
ADVANTA_PARTNER_ID=15762
ADVANTA_SHORTCODE=SchoolSMS
ADVANTA_USSD_CALLBACK_URL=https://voting.novotechafrica.co.ke/api/ussd.php

# M-Pesa (C2B only)
MPESA_ENV=production
MPESA_CONSUMER_KEY=your_production_consumer_key
MPESA_CONSUMER_SECRET=your_production_consumer_secret
MPESA_SHORTCODE=your_production_shortcode
MPESA_PASSKEY=your_production_passkey
MPESA_CALLBACK_URL=https://voting.novotechafrica.co.ke/api/mpesa-callback.php

# USSD
USSD_BASE_CODE=*519*24#

# Admin Credentials
ADMIN_USERNAME=admin
ADMIN_PASSWORD=your_secure_admin_password
```

### 5. Database Setup

```bash
# Login to MySQL
sudo mysql -u root -p

# Create database and user
CREATE DATABASE votingnova CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'votingnova_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON votingnova.* TO 'votingnova_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Import schema
mysql -u votingnova_user -p votingnova < database/schema.sql

# Run migrations
mysql -u votingnova_user -p votingnova < database/migrate_user_tables.sql
mysql -u votingnova_user -p votingnova < database/migrate_user_passwords.sql
mysql -u votingnova_user -p votingnova < database/seed_settings.sql
```

### 6. Apache Virtual Host Configuration

Create Apache configuration file:

```bash
sudo nano /etc/apache2/sites-available/voting-nova.conf
```

**Apache Configuration:**

```apache
<VirtualHost *:80>
    ServerName voting.novotechafrica.co.ke
    ServerAlias www.voting.novotechafrica.co.ke
    
    # Redirect HTTP to HTTPS
    Redirect permanent / https://voting.novotechafrica.co.ke/
</VirtualHost>

<VirtualHost *:443>
    ServerName voting.novotechafrica.co.ke
    ServerAlias www.voting.novotechafrica.co.ke
    DocumentRoot /var/www/novatech

    <Directory /var/www/novatech>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/voting.novotechafrica.co.ke/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/voting.novotechafrica.co.ke/privkey.pem

    # Security Headers
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"

    # Logging
    ErrorLog ${APACHE_LOG_DIR}/voting-nova-error.log
    CustomLog ${APACHE_LOG_DIR}/voting-nova-access.log combined
</VirtualHost>
```

Enable site and restart Apache:

```bash
sudo a2ensite voting-nova.conf
sudo a2dissite 000-default.conf  # Disable default site if needed
sudo systemctl reload apache2
```

### 7. SSL Certificate (Let's Encrypt)

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-apache

# Obtain certificate
sudo certbot --apache -d voting.novotechafrica.co.ke -d www.voting.novotechafrica.co.ke

# Auto-renewal is set up automatically
```

### 8. Set File Permissions

```bash
cd /var/www/novatech

# Set ownership
sudo chown -R www-data:www-data /var/www/novatech

# Set directory permissions
sudo find /var/www/novatech -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /var/www/novatech -type f -exec chmod 644 {} \;

# Make sure .env is readable only by web server
sudo chmod 640 .env
sudo chown www-data:www-data .env
```

### 9. Firewall Configuration

```bash
# Allow HTTP, HTTPS, and SSH
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

### 10. Verify Deployment

1. **Check Apache status:**
   ```bash
   sudo systemctl status apache2
   ```

2. **Test database connection:**
   ```bash
   php -r "require '/var/www/novatech/includes/db.php'; echo 'Database connected!';"
   ```

3. **Check PHP errors:**
   ```bash
   tail -f /var/log/apache2/voting-nova-error.log
   ```

4. **Access admin dashboard:**
   - URL: `https://voting.novotechafrica.co.ke/admin/`
   - Use credentials from `.env` file

### 11. Configure Advanta USSD Callback

In your Advanta dashboard:
- **USSD Callback URL**: `https://voting.novotechafrica.co.ke/api/ussd.php`
- **Method**: GET/POST
- **Content-Type**: text/plain

### 12. Configure M-Pesa Callback

In Safaricom Daraja Portal:
- **STK Push Callback URL**: `https://voting.novotechafrica.co.ke/api/mpesa-callback.php`
- Ensure your production app has this callback configured

## Port Configuration

This deployment uses standard ports:
- **Port 80**: HTTP (redirects to HTTPS)
- **Port 443**: HTTPS
- **Port 3306**: MySQL (localhost only)

No conflicts with existing projects if they use different ports or domains.

## Maintenance

### Update Code

```bash
cd /var/www/novatech
git pull origin main
# Restart Apache if needed
sudo systemctl restart apache2
```

### Backup Database

```bash
# Create backup
mysqldump -u votingnova_user -p votingnova > backup_$(date +%Y%m%d_%H%M%S).sql

# Restore from backup
mysql -u votingnova_user -p votingnova < backup_file.sql
```

### Monitor Logs

```bash
# Apache error log
tail -f /var/log/apache2/voting-nova-error.log

# Apache access log
tail -f /var/log/apache2/voting-nova-access.log

# PHP error log (if configured)
tail -f /var/log/php/error.log
```

## Troubleshooting

### Permission Issues
```bash
sudo chown -R www-data:www-data /var/www/novatech
sudo chmod -R 755 /var/www/novatech
```

### Database Connection Issues
- Verify credentials in `.env`
- Check MySQL service: `sudo systemctl status mysql`
- Test connection: `mysql -u votingnova_user -p votingnova`

### SSL Certificate Issues
- Renew certificate: `sudo certbot renew`
- Check certificate: `sudo certbot certificates`

### USSD Not Working
- Verify callback URL in Advanta dashboard
- Check Apache logs for incoming requests
- Test endpoint: `curl https://voting.novotechafrica.co.ke/api/ussd.php`

## Security Checklist

- [ ] `.env` file has correct permissions (640)
- [ ] Database user has limited privileges
- [ ] SSL certificate is valid and auto-renewing
- [ ] Firewall is configured
- [ ] Admin password is changed from default
- [ ] `APP_DEBUG=false` in production
- [ ] Error logging is enabled but display is disabled
- [ ] Regular backups are scheduled
