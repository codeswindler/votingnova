# VPS Update Steps - Production Deployment

## Quick Update (If Already Deployed)

If the system is already running on the VPS, follow these steps to update with the latest changes:

### 1. SSH into VPS
```bash
ssh user@your-vps-ip
```

### 2. Navigate to Project Directory
```bash
cd /var/www/novatech
```

### 3. Backup Current State (IMPORTANT!)
```bash
# Backup database
mysqldump -u votingnova_user -p votingnova > backup_$(date +%Y%m%d_%H%M%S).sql

# Backup current code (optional but recommended)
cp -r . ../novatech_backup_$(date +%Y%m%d_%H%M%S)
```

### 4. Pull Latest Changes
```bash
# Check current branch
git branch

# Pull latest changes from main
git pull origin main
```

### 5. Verify Changes
```bash
# Check what changed
git log --oneline -5

# Verify files are updated
git status
```

### 6. Check Environment Variables
```bash
# Verify .env file has correct SMS API settings
nano .env
```

**Ensure these are set correctly:**
```env
# Advanta SMS (NEW API)
ADVANTA_API_KEY=your_api_key
ADVANTA_PARTNER_ID=your_partner_id
ADVANTA_SHORTCODE=your_shortcode

# M-Pesa (Production)
MPESA_ENV=production
MPESA_CONSUMER_KEY=your_consumer_key
MPESA_CONSUMER_SECRET=your_consumer_secret
MPESA_SHORTCODE=your_shortcode
MPESA_PASSKEY=your_passkey
MPESA_CALLBACK_URL=https://voting.novotechafrica.co.ke/api/mpesa-callback.php
```

### 7. Clear PHP Opcache (if enabled)
```bash
# Restart PHP-FPM (if using)
sudo systemctl restart php8.0-fpm

# Or restart Apache
sudo systemctl restart apache2
```

### 8. Test the Update
```bash
# Check Apache status
sudo systemctl status apache2

# Check error logs
sudo tail -f /var/log/apache2/voting-nova-error.log

# Test database connection
php -r "require '/var/www/novatech/includes/db.php'; echo 'Database OK\n';"
```

### 9. Verify SMS API is Working
```bash
# Test SMS endpoint (if test-sms.php exists)
cd /var/www/novatech
php test-sms.php
```

### 10. Monitor for Issues
```bash
# Watch error logs in real-time
sudo tail -f /var/log/apache2/voting-nova-error.log

# Watch access logs
sudo tail -f /var/log/apache2/voting-nova-access.log
```

## What Changed in This Update

1. **SMS API Updated**
   - New endpoint: `https://quicksms.advantasms.com/api/services/sendotp`
   - Parameter names changed: `api_key` → `apikey`, `partner_id` → `partnerID`
   - Response format handling updated

2. **Pending Votes Removed**
   - Votes are now only created when payment is confirmed
   - No more pending vote records

3. **Simulator Logic Removed**
   - All SIM- transaction handling removed from production
   - Only real M-Pesa transactions are processed

4. **STK Push Improvements**
   - Better validation and error handling
   - Enhanced logging

## Rollback Plan (If Something Goes Wrong)

If you need to rollback:

```bash
cd /var/www/novatech

# Revert to previous commit
git log --oneline  # Find the commit hash before the update
git reset --hard <previous_commit_hash>

# Restore database backup
mysql -u votingnova_user -p votingnova < backup_YYYYMMDD_HHMMSS.sql

# Restart services
sudo systemctl restart apache2
```

## Post-Update Verification Checklist

- [ ] Admin dashboard loads: `https://voting.novotechafrica.co.ke/admin/`
- [ ] USSD endpoint responds: Test with `curl https://voting.novotechafrica.co.ke/api/ussd.php`
- [ ] M-Pesa callback works: Check logs after a test payment
- [ ] SMS sending works: Test OTP or payment confirmation SMS
- [ ] No errors in Apache logs
- [ ] Database queries work (check admin dashboard statistics)
- [ ] Real payments are processed correctly

## Troubleshooting

### If SMS is not working:
1. Check `.env` file has correct `ADVANTA_API_KEY` and `ADVANTA_PARTNER_ID`
2. Verify new API endpoint is accessible
3. Check error logs: `sudo tail -f /var/log/apache2/voting-nova-error.log`
4. Test with: `php test-sms.php`

### If payments are not processing:
1. Verify M-Pesa credentials in `.env`
2. Check M-Pesa callback URL is correct
3. Verify callback endpoint: `curl https://voting.novotechafrica.co.ke/api/mpesa-callback.php`
4. Check transaction logs in database

### If votes are not being recorded:
1. Check M-Pesa callback is receiving requests
2. Verify `ussd_sessions` table has correct data
3. Check error logs for callback processing errors
4. Verify votes are only created after payment confirmation (new behavior)

## Full Deployment (If Starting Fresh)

If this is a new deployment, follow the complete steps in `DEPLOYMENT.md` or `DEPLOY_EXACT_COMMANDS.txt`.
