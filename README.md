# USSD Voting System

A complete USSD voting system for the Murang'a 40 Under 40 Awards with M-Pesa C2B (STK Push) payment integration, Advanta SMS/USSD APIs, and an admin dashboard for monitoring votes and winners.

## Features

- **USSD Voting Interface** - Users can vote via USSD code
- **M-Pesa Integration** - Secure payment processing via Safaricom Daraja API
- **Multi-Category Support** - 12 categories with Male and Female contestants
- **Real-time Dashboard** - Admin dashboard with live statistics and charts
- **SMS Notifications** - Automatic SMS confirmations via Advanta SMS API
- **Transaction Management** - Complete transaction history and filtering

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server with mod_rewrite
- cURL extension enabled
- SSL certificate (required for M-Pesa callbacks)

## Environment Configuration

Before going live, you must configure all API credentials in your `.env` file:

1. **Copy the example environment file**
   ```bash
   cp env.example .env
   ```

2. **Edit `.env` and fill in your credentials:**
   - **Database**: Update `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
   - **Advanta SMS/USSD**: Add `ADVANTA_API_KEY`, `ADVANTA_PARTNER_ID`, `ADVANTA_SHORTCODE`, `ADVANTA_USSD_CALLBACK_URL`
   - **M-Pesa Daraja**: Add `MPESA_CONSUMER_KEY`, `MPESA_CONSUMER_SECRET`, `MPESA_SHORTCODE`, `MPESA_PASSKEY`, `MPESA_CALLBACK_URL`
   - **USSD Base Code**: Set `USSD_BASE_CODE` to match your Advanta shortcode (e.g., `*519*24#`)
   - **Application**: Update `APP_URL` and set `APP_ENV=production`, `APP_DEBUG=false`

3. **Important**: Never commit your `.env` file to version control!

## Quick Start (Local Testing)

For local development and testing:

1. **Start Local Server**
   ```bash
   # Windows
   start-local.bat
   
   # Linux/Mac
   chmod +x start-local.sh
   ./start-local.sh
   
   # Or manually
   php -S localhost:8000
   ```

2. **Setup Database** (see Database Setup below)

3. **Access Application**
   - **USSD Simulator**: http://localhost:8000/simulator/
   - **Admin Dashboard**: http://localhost:8000/admin/
   - **API Endpoint**: http://localhost:8000/api/ussd.php

4. **Test USSD Flow**
   - Open the simulator in your browser
   - Click "Start New Session"
   - Follow the voting flow interactively

See [LOCAL_SETUP.md](LOCAL_SETUP.md) for detailed local setup instructions.

## Installation

### 1. Clone/Download the Project

```bash
cd /var/www/html
git clone <repository-url> votingnova
cd votingnova
```

### 2. Database Setup

```bash
# Create database and import schema
mysql -u root -p < database/schema.sql

# Seed initial data (categories and nominees)
mysql -u root -p votingnova < database/seed.sql
```

### 3. Configuration

Copy `env.example` to `.env` and update with your credentials:

```bash
cp env.example .env
```

Edit `.env` and configure:

- **Database credentials** (DB_HOST, DB_NAME, DB_USER, DB_PASS)
- **Advanta SMS/USSD API credentials**:
  - `ADVANTA_API_KEY` - Your Advanta API key
  - `ADVANTA_PARTNER_ID` - Your Advanta partner ID
  - `ADVANTA_SHORTCODE` - Your Advanta shortcode (e.g., 415)
  - `ADVANTA_USSD_CALLBACK_URL` - Your USSD callback URL (must be HTTPS in production)
- **M-Pesa Daraja API credentials**:
  - `MPESA_ENV` - Set to 'production' for live, 'sandbox' for testing
  - `MPESA_CONSUMER_KEY` - Your M-Pesa consumer key
  - `MPESA_CONSUMER_SECRET` - Your M-Pesa consumer secret
  - `MPESA_SHORTCODE` - Your M-Pesa business shortcode
  - `MPESA_PASSKEY` - Your Lipa Na M-Pesa Online passkey
  - `MPESA_CALLBACK_URL` - Your M-Pesa callback URL (must be HTTPS in production)
  - **Note**: This system uses C2B (Customer to Business) only via STK Push
- **USSD Base Code**: `USSD_BASE_CODE` - The USSD code users dial (e.g., *519*24#)
- **Application Settings**: `APP_URL`, `APP_ENV`, `APP_DEBUG`

### 4. Web Server Configuration

#### Apache
Ensure mod_rewrite is enabled and point DocumentRoot to the project directory.

#### Nginx
Add the following location block:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### 5. Set Permissions

```bash
chmod 755 -R .
chmod 777 -R logs/  # If you create a logs directory
```

### 6. Configure Advanta USSD

1. Log into your Advanta account
2. Navigate to USSD settings
3. Set callback URL to: `https://voting.novotechafrica.co.ke/api/ussd.php`
4. Ensure your USSD code is active

### 7. Configure M-Pesa Daraja

1. Log into Safaricom Developer Portal
2. Create an app and get Consumer Key/Secret
3. Configure STK Push callback URL: `https://voting.novotechafrica.co.ke/api/mpesa-callback.php`
4. Update credentials in `.env`

## Default Admin Credentials

Admin credentials are configured in your `.env` file:
- `ADMIN_USERNAME` - Default: admin
- `ADMIN_PASSWORD` - Default: admin123

**⚠️ IMPORTANT:** Change the default password immediately after first login!

The default admin user is created during database seeding. To change the password:

```sql
UPDATE admin_users 
SET password_hash = '$2y$10$...' 
WHERE username = 'admin';
```

Generate new hash using PHP:

```php
echo password_hash('your_new_password', PASSWORD_DEFAULT);
```

## User Management & OTP

The system includes a user management feature where admins can:

- **Create System Users**: Add users with phone numbers, names, and emails
- **Enable/Disable OTP**: Toggle OTP on/off globally or per user
- **OTP via SMS**: When enabled, OTP codes are sent via Advanta SMS API to user's registered phone number
- **User Management**: Edit, delete, and manage user accounts

Access user management from the admin dashboard: `/admin/users.php`

To change password:

```sql
UPDATE admin_users 
SET password_hash = '$2y$10$...' 
WHERE username = 'admin';
```

Generate new hash using PHP:

```php
echo password_hash('your_new_password', PASSWORD_DEFAULT);
```

## USSD Flow

1. User dials USSD code (e.g., *519*24#)
2. System displays categories
3. User selects category
4. User selects gender (Male/Female)
5. System displays nominees
6. User selects nominee
7. User enters number of votes
8. System shows confirmation
9. STK Push is sent to user's phone
10. User completes payment
11. Votes are recorded and SMS confirmation sent

## Admin Dashboard

Access the admin dashboard at: `https://voting.novotechafrica.co.ke/admin/`

### Dashboard Features

- **Overview Statistics** - Total votes, revenue, transactions
- **Category Leaderboards** - View winners per category
- **Transaction History** - Filter and search all transactions
- **Real-time Updates** - Auto-refresh every 10 seconds
- **Charts & Analytics** - Visual representation of voting data

## API Endpoints

### USSD Callback
- **URL:** `/api/ussd.php`
- **Method:** GET/POST
- **Parameters:** SESSIONID, USSDCODE, MSISDN, INPUT

### M-Pesa Callback
- **URL:** `/api/mpesa-callback.php`
- **Method:** POST
- **Content-Type:** application/json

### Admin API
- **URL:** `/api/admin-api.php`
- **Method:** GET
- **Actions:**
  - `stats` - Dashboard statistics
  - `category-leaderboard&category_id=X` - Category results
  - `transactions` - Transaction list
  - `winners` - Overall winners
  - `votes-by-category` - Votes distribution

## Database Structure

### Main Tables

- **categories** - Voting categories
- **nominees** - Contestants with vote counts
- **votes** - Individual vote transactions
- **mpesa_transactions** - M-Pesa payment records
- **admin_users** - Admin authentication
- **ussd_sessions** - USSD session management

## Security Considerations

1. **Change default admin password** immediately
2. **Use HTTPS** for all API endpoints (required for M-Pesa)
3. **Restrict IP access** to admin dashboard if possible
4. **Regular backups** of database
5. **Keep PHP and dependencies updated**
6. **Use environment variables** for sensitive credentials
7. **Enable error logging** but disable display in production

## Testing

### Local Testing with Simulator

The easiest way to test locally is using the built-in USSD simulator:

1. **Start local server** (see Quick Start above)
2. **Open simulator**: http://localhost:8000/simulator/
3. **Test complete flow**:
   - Start new session
   - Select category
   - Choose gender
   - Pick nominee
   - Enter votes
   - Confirm payment

The simulator provides a visual interface to test the entire USSD flow without needing actual USSD access.

### USSD Testing (Production)
1. Use Advanta test USSD code
2. Test complete flow end-to-end
3. Verify payment processing
4. Check SMS delivery

### M-Pesa Testing
1. Use Safaricom Daraja sandbox environment
2. Test with test phone numbers
3. Verify callback handling
4. Test payment failures

See [test/README.md](test/README.md) for detailed testing instructions.

## Troubleshooting

### USSD Not Working
- Check callback URL is accessible
- Verify Advanta credentials
- Check server logs for errors
- Ensure USSD code is active

### M-Pesa Payments Failing
- Verify Daraja API credentials
- Check callback URL is HTTPS
- Ensure shortcode and passkey are correct
- Review M-Pesa transaction logs

### Dashboard Not Loading
- Check database connection
- Verify admin user exists
- Check PHP error logs
- Ensure JavaScript files are accessible

## Support

For issues or questions:
1. Check error logs
2. Review configuration
3. Test API endpoints individually
4. Contact Advanta support for USSD issues
5. Contact Safaricom support for M-Pesa issues

## License

[Specify your license here]

## Contributors

[Add contributors here]
