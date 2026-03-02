# Going Live Checklist

This document provides a step-by-step checklist for deploying the Voting System to production.

## Pre-Deployment Checklist

### 1. Environment Configuration

- [ ] Copy `env.example` to `.env`
- [ ] Update all database credentials in `.env`
- [ ] Add **Advanta SMS/USSD API credentials**:
  - [ ] `ADVANTA_API_KEY` - Get from Advanta dashboard
  - [ ] `ADVANTA_PARTNER_ID` - Get from Advanta dashboard
  - [ ] `ADVANTA_SHORTCODE` - Your assigned shortcode (e.g., 415)
  - [ ] `ADVANTA_USSD_CALLBACK_URL` - Set to `https://voting.novotechafrica.co.ke/api/ussd.php`
- [ ] Add **M-Pesa Daraja API credentials**:
  - [ ] `MPESA_ENV=production` - Change from 'sandbox' to 'production'
  - [ ] `MPESA_CONSUMER_KEY` - Get from Safaricom Developer Portal
  - [ ] `MPESA_CONSUMER_SECRET` - Get from Safaricom Developer Portal
  - [ ] `MPESA_SHORTCODE` - Your production business shortcode
  - [ ] `MPESA_PASSKEY` - Your production Lipa Na M-Pesa passkey
  - [ ] `MPESA_CALLBACK_URL` - Set to `https://voting.novotechafrica.co.ke/api/mpesa-callback.php`
  - **Note**: This system uses C2B (Customer to Business) only via STK Push - no B2C credentials needed
- [ ] Set **USSD Base Code**: `USSD_BASE_CODE=*519*24#` (match your Advanta shortcode)
- [ ] Update **Application Settings**:
  - [ ] `APP_URL=https://voting.novotechafrica.co.ke`
  - [ ] `APP_ENV=production`
  - [ ] `APP_DEBUG=false`

### 2. Database Setup

- [ ] Create production database
- [ ] Import schema: `mysql -u user -p database_name < database/schema.sql`
- [ ] Seed categories and nominees: `mysql -u user -p database_name < database/seed.sql`
- [ ] **IMPORTANT**: Run `clear-seed-data.bat` or execute `database/clear_seed_data.sql` to remove all test data
- [ ] Change default admin password
- [ ] Create database backup schedule

### 3. Server Configuration

- [ ] Ensure PHP 7.4+ is installed
- [ ] Ensure MySQL 5.7+ is installed
- [ ] Enable SSL certificate (HTTPS required for M-Pesa callbacks)
- [ ] Configure web server (Apache/Nginx)
- [ ] Set proper file permissions:
  - [ ] `chmod 755 -R .` (directories and files)
  - [ ] `chmod 777 logs/` (if using logging)
- [ ] Configure firewall rules
- [ ] Set up error logging

### 4. Advanta Configuration

- [ ] Log into Advanta dashboard
- [ ] Navigate to USSD settings
- [ ] Set callback URL to: `https://voting.novotechafrica.co.ke/api/ussd.php`
- [ ] Verify USSD code is active
- [ ] Test USSD callback with a test request
- [ ] Verify SMS API credentials are correct

### 5. M-Pesa Daraja Configuration

- [ ] Log into Safaricom Developer Portal
- [ ] Create production app (if not already created)
- [ ] Get production Consumer Key and Secret
- [ ] Configure STK Push callback URL: `https://voting.novotechafrica.co.ke/api/mpesa-callback.php`
- [ ] Verify business shortcode is correct
- [ ] Verify passkey is correct (for C2B STK Push)
- [ ] Test STK Push with a small amount
- [ ] Verify callback is received correctly
- [ ] **Note**: Only C2B (STK Push) is used - no B2C initiator credentials needed

### 6. Security Checklist

- [ ] Change default admin password
- [ ] Ensure `.env` file is not accessible via web (check `.htaccess` or nginx config)
- [ ] Verify `.env` is in `.gitignore`
- [ ] Enable HTTPS/SSL
- [ ] Review and restrict file permissions
- [ ] Set up regular database backups
- [ ] Configure error logging (hide sensitive info in production)
- [ ] Review SQL injection protection (using prepared statements)
- [ ] Review XSS protection (using htmlspecialchars)

### 7. Testing

- [ ] Test USSD flow end-to-end
- [ ] Test M-Pesa STK Push payment
- [ ] Verify SMS confirmation is sent
- [ ] Test admin dashboard login
- [ ] Verify vote counting is accurate
- [ ] Test transaction history
- [ ] Test category leaderboards
- [ ] Test winners calculation
- [ ] Load test with multiple concurrent users

### 8. Monitoring

- [ ] Set up server monitoring
- [ ] Set up database monitoring
- [ ] Configure error alerting
- [ ] Set up transaction monitoring
- [ ] Monitor API response times
- [ ] Set up backup verification

## Post-Deployment

- [ ] Monitor first few transactions closely
- [ ] Verify all callbacks are working
- [ ] Check error logs regularly
- [ ] Monitor server resources
- [ ] Verify SMS delivery rates
- [ ] Monitor M-Pesa transaction success rates

## Rollback Plan

If issues occur:

1. Switch `MPESA_ENV` back to 'sandbox' temporarily
2. Disable USSD code in Advanta dashboard
3. Restore database from backup if needed
4. Review error logs to identify issues

## Support Contacts

- **Advanta Support**: [Your Advanta support contact]
- **M-Pesa Support**: [Safaricom Developer Portal support]
- **Server Admin**: [Your server administrator]

## Important Notes

- **Never commit `.env` file to version control**
- **Always use HTTPS in production** (required for M-Pesa callbacks)
- **Test thoroughly in sandbox before going live**
- **Keep backups of database before major changes**
- **Monitor first transactions closely after going live**
