# Testing Guide

## Local Testing Setup

### 1. Start Local Server

**Option A: PHP Built-in Server**
```bash
cd votingnova
php -S localhost:8000
```

**Option B: XAMPP/WAMP**
- Place project in `htdocs` or `www` folder
- Start Apache and MySQL
- Access via `http://localhost/votingnova`

### 2. Setup Database

```bash
# Create database
mysql -u root -p < database/schema.sql

# Seed data
mysql -u root -p votingnova < database/seed.sql
```

### 3. Configure Database Connection

Edit `config/database.php`:
```php
'host' => 'localhost',
'database' => 'votingnova',
'username' => 'root',
'password' => ''  // Your MySQL password
```

## Testing Methods

### Method 1: USSD Simulator (Recommended)

1. Start your local server
2. Open browser: `http://localhost:8000/simulator/`
3. Click "Start New Session"
4. Follow the USSD flow:
   - Select category (1-12)
   - Select gender (1=Male, 2=Female)
   - Select nominee
   - Enter votes
   - Confirm payment

**Advantages:**
- Visual interface
- See full conversation
- Easy to test different flows
- No external tools needed

### Method 2: Postman/API Client

1. Import `postman_collection.json` into Postman
2. Update URLs to match your local server
3. Test each endpoint individually

**Test Sequence:**
1. Initial request (empty INPUT)
2. Select category (INPUT=1)
3. Select gender (INPUT=1*1)
4. Select nominee (INPUT=1*1*1)
5. Enter votes (INPUT=1*1*1*10)
6. Confirm (INPUT=1*1*1*10*1)

### Method 3: cURL Commands

```bash
# Initial request
curl "http://localhost:8000/api/ussd.php?SESSIONID=TEST123&USSDCODE=*415#&MSISDN=254712345678&INPUT="

# Select category 1
curl "http://localhost:8000/api/ussd.php?SESSIONID=TEST123&USSDCODE=*415#&MSISDN=254712345678&INPUT=1"

# Select Male
curl "http://localhost:8000/api/ussd.php?SESSIONID=TEST123&USSDCODE=*415#&MSISDN=254712345678&INPUT=1*1"

# Select first nominee
curl "http://localhost:8000/api/ussd.php?SESSIONID=TEST123&USSDCODE=*415#&MSISDN=254712345678&INPUT=1*1*1"

# Enter 10 votes
curl "http://localhost:8000/api/ussd.php?SESSIONID=TEST123&USSDCODE=*415#&MSISDN=254712345678&INPUT=1*1*1*10"

# Confirm
curl "http://localhost:8000/api/ussd.php?SESSIONID=TEST123&USSDCODE=*415#&MSISDN=254712345678&INPUT=1*1*1*10*1"
```

## Testing Admin Dashboard

1. Access: `http://localhost:8000/admin/`
2. Login: `admin` / `admin123`
3. Verify:
   - Dashboard loads
   - Statistics display
   - Categories page works
   - Transactions page loads

## Testing M-Pesa Integration

### Using Safaricom Daraja Sandbox

1. **Get Sandbox Credentials**
   - Register at https://developer.safaricom.co.ke
   - Create an app
   - Get Consumer Key and Secret

2. **Update Config**
   ```php
   // config/mpesa.php
   'environment' => 'sandbox',
   'consumer_key' => 'your_sandbox_key',
   'consumer_secret' => 'your_sandbox_secret',
   'shortcode' => '174379',  // Sandbox shortcode
   'passkey' => 'your_passkey'
   ```

3. **Test STK Push**
   - Use test phone numbers from Safaricom
   - Test numbers: 254708374149, etc.
   - Check sandbox documentation for current test numbers

4. **Test Callback**
   - Use Postman to send test callback
   - Or use ngrok to expose local server:
     ```bash
     ngrok http 8000
     # Use ngrok URL in M-Pesa callback configuration
     ```

## Testing Checklist

- [ ] Database connection works
- [ ] USSD simulator loads
- [ ] Can complete full USSD flow
- [ ] Sessions are created in database
- [ ] Admin dashboard loads
- [ ] Can view categories and nominees
- [ ] Can see transaction history
- [ ] M-Pesa STK Push initiates (if configured)
- [ ] M-Pesa callback processes (if configured)

## Common Issues

### "Database connection failed"
- Check MySQL is running
- Verify credentials in config/database.php
- Ensure database exists

### "404 Not Found"
- Check server is running
- Verify file paths
- Check .htaccess (if using Apache)

### "Session expired"
- Sessions expire after 5 minutes
- Start a new session

### "No nominees found"
- Run seed.sql to populate nominees
- Check database has data

## Next Steps

1. Test complete USSD flow
2. Verify database records
3. Test admin dashboard features
4. Configure M-Pesa sandbox (optional)
5. Test payment flow (if M-Pesa configured)
