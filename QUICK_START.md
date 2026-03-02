# Quick Start Guide

## ✅ Setup Complete!

Your database has been successfully configured:
- ✅ Database: `votingnova` created
- ✅ 12 Categories loaded
- ✅ 196 Nominees loaded (Male & Female)
- ✅ Admin user created (admin/admin123)

## 🚀 Access Your Application

The server is now running! Open these URLs in your browser:

### 1. USSD Simulator (Test Voting Flow)
**http://localhost:8000/simulator/**

- Click "Start New Session"
- Test the complete voting flow
- See how users interact with the USSD system

### 2. Admin Dashboard
**http://localhost:8000/admin/**

- Login: `admin` / `admin123`
- View statistics and voting data
- See categories and nominees
- Check transaction history

### 3. API Endpoint
**http://localhost:8000/api/ussd.php**

- USSD callback endpoint
- Test with Postman or the simulator

## 📋 Test the System

### Step 1: Test USSD Simulator
1. Go to http://localhost:8000/simulator/
2. Click "Start New Session"
3. Follow the prompts:
   - Select a category (1-12)
   - Choose gender (1=Male, 2=Female)
   - Pick a nominee
   - Enter number of votes (e.g., 10)
   - Confirm payment

### Step 2: Check Admin Dashboard
1. Go to http://localhost:8000/admin/
2. Login with: `admin` / `admin123`
3. Explore:
   - Dashboard overview
   - Categories & Winners
   - Transactions

### Step 3: Verify Database
The database contains:
- 12 Categories (Innovation, Media, Education, etc.)
- 196 Nominees (across all categories)
- Admin user ready to use

## 🔧 Configuration

### Database (Already Configured)
- Host: localhost
- Database: votingnova
- User: root
- Password: RootPass123!

### Next Steps (Optional)
1. **Configure M-Pesa** (for payment testing):
   - Get Safaricom Daraja API credentials
   - Update `config/mpesa.php` or create `.env`

2. **Configure Advanta SMS** (for SMS notifications):
   - Get Advanta API credentials
   - Update `config/advanta.php` or create `.env`

3. **Change Admin Password**:
   ```sql
   UPDATE admin_users 
   SET password_hash = '$2y$10$...' 
   WHERE username = 'admin';
   ```
   Generate hash: `php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"`

## 🛠️ Troubleshooting

### Server Not Running?
```bash
php -S localhost:8000
```

### Database Connection Issues?
- Check MariaDB is running
- Verify password in `config/database.php`

### Can't Access Pages?
- Make sure server is running on port 8000
- Check firewall settings
- Try http://127.0.0.1:8000 instead

## 📚 Documentation

- **Local Setup**: See `LOCAL_SETUP.md`
- **Testing Guide**: See `test/README.md`
- **Full Documentation**: See `README.md`

## 🎉 You're Ready!

Everything is set up and ready to test. Start with the USSD Simulator to see the voting flow in action!
