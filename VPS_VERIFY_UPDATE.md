# VPS Update Verification Steps

## Your Setup
- Using PHP built-in server (voting-nova.service on port 8080)
- PHP 8.3.6
- Service is running ✅

## Verification Steps

### 1. Check Service Status
```bash
sudo systemctl status voting-nova
```

### 2. Check Application Logs
Since you're using PHP built-in server, check these logs:
```bash
# Check systemd journal for voting-nova service
sudo journalctl -u voting-nova -f

# Or check recent logs
sudo journalctl -u voting-nova -n 50
```

### 3. Test Application Endpoints

```bash
# Test admin dashboard
curl -I http://localhost:8080/admin/

# Test USSD endpoint
curl -I http://localhost:8080/api/ussd.php

# Test M-Pesa callback endpoint
curl -I http://localhost:8080/api/mpesa-callback.php
```

### 4. Verify SMS API Configuration
```bash
# Check .env file has correct SMS settings
grep ADVANTA .env

# Should show:
# ADVANTA_API_KEY=...
# ADVANTA_PARTNER_ID=...
# ADVANTA_SHORTCODE=...
```

### 5. Test SMS (if test-sms.php exists)
```bash
cd /var/www/novatech
php test-sms.php
```

### 6. Check Database Connection
```bash
php -r "require '/var/www/novatech/includes/db.php'; echo 'Database OK\n';"
```

### 7. Verify Changes Are Active
```bash
# Check if new SMS API endpoint is in config
grep "quicksms.advantasms.com" config/advanta.php

# Should show the new endpoint
```

## What to Monitor

### Watch Application Logs
```bash
# Real-time monitoring
sudo journalctl -u voting-nova -f
```

### Check for Errors
```bash
# Recent errors
sudo journalctl -u voting-nova -p err -n 20
```

## Common Issues

### If service won't start:
```bash
# Check service logs
sudo journalctl -u voting-nova -n 50

# Restart service
sudo systemctl restart voting-nova
```

### If endpoints don't respond:
```bash
# Check if port 8080 is listening
sudo netstat -tlnp | grep 8080

# Check firewall
sudo ufw status
```

### If SMS not working:
1. Verify .env has correct ADVANTA credentials
2. Test with: `php test-sms.php`
3. Check logs: `sudo journalctl -u voting-nova -f`

## Success Indicators

✅ Service status shows "active (running)"
✅ Admin dashboard loads: `http://your-domain:8080/admin/`
✅ USSD endpoint responds
✅ No errors in journal logs
✅ SMS test works (if test script available)
