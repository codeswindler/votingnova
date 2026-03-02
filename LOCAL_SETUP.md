# Local Development Setup Guide

## Quick Start for Local Testing

### Option 1: Using XAMPP/WAMP/MAMP (Recommended for Windows)

1. **Install XAMPP**
   - Download from https://www.apachefriends.org/
   - Install and start Apache and MySQL services

2. **Setup Project**
   ```bash
   # Copy project to htdocs/www
   C:\xampp\htdocs\votingnova
   # or
   C:\wamp64\www\votingnova
   ```

3. **Create Database**
   - Open http://localhost/phpmyadmin
   - Import `database/schema.sql`
   - Import `database/seed.sql`

4. **Configure Database**
   - Edit `config/database.php` or create `.env`:
   ```php
   'host' => 'localhost',
   'database' => 'votingnova',
   'username' => 'root',
   'password' => ''  // Usually empty for XAMPP
   ```

5. **Access Application**
   - Admin Dashboard: http://localhost/votingnova/admin/
   - USSD Simulator: http://localhost/votingnova/simulator/
   - API Endpoint: http://localhost/votingnova/api/ussd.php

### Option 2: Using PHP Built-in Server

1. **Start PHP Server**
   ```bash
   cd votingnova
   php -S localhost:8000
   ```

2. **Access Application**
   - Admin Dashboard: http://localhost:8000/admin/
   - USSD Simulator: http://localhost:8000/simulator/
   - API Endpoint: http://localhost:8000/api/ussd.php

### Option 3: Using Docker (Advanced)

Create a `docker-compose.yml`:

```yaml
version: '3.8'
services:
  web:
    image: php:7.4-apache
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/html
    depends_on:
      - db
  
  db:
    image: mysql:5.7
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: votingnova
    ports:
      - "3306:3306"
    volumes:
      - ./database/schema.sql:/docker-entrypoint-initdb.d/schema.sql
```

Run: `docker-compose up`

## Testing Checklist

### 1. Database Setup
```bash
php setup.php
```

### 2. Test Admin Dashboard
- Go to: http://localhost/votingnova/admin/
- Login: admin / admin123
- Verify you can see statistics and categories

### 3. Test USSD Simulator
- Go to: http://localhost/votingnova/simulator/
- Simulate USSD flow
- Check database for session creation

### 4. Test M-Pesa (Sandbox)
- Use Safaricom Daraja Sandbox
- Test with test phone numbers
- Verify callbacks are received

## Local Testing Notes

- **USSD Callbacks**: Use the simulator or Postman to test
- **M-Pesa**: Use Safaricom Daraja Sandbox environment
- **SMS**: Advanta SMS may not work locally (requires production setup)
- **HTTPS**: Not required locally, but needed for production M-Pesa

## Troubleshooting

### Database Connection Error
- Check MySQL is running
- Verify credentials in `config/database.php`
- Ensure database exists

### 404 Errors
- Check `.htaccess` is present
- Verify mod_rewrite is enabled (Apache)
- Check file paths are correct

### Session Issues
- Check PHP session directory is writable
- Verify cookies are enabled in browser
