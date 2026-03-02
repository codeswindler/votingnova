@echo off
echo ========================================
echo Database Setup Script
echo ========================================
echo.

set MYSQL_PATH="C:\Program Files\MariaDB 12.1\bin\mysql.exe"
set DB_USER=root
set DB_PASS=RootPass123!
set DB_NAME=votingnova

echo Step 1: Creating database and tables...
%MYSQL_PATH% -u %DB_USER% -p%DB_PASS% < database\schema.sql
if errorlevel 1 (
    echo ERROR: Failed to create database schema
    pause
    exit /b 1
)
echo Database schema created successfully!
echo.

echo Step 2: Seeding initial data (categories and nominees)...
%MYSQL_PATH% -u %DB_USER% -p%DB_PASS% %DB_NAME% < database\seed.sql
if errorlevel 1 (
    echo ERROR: Failed to seed database
    pause
    exit /b 1
)
echo Data seeded successfully!
echo.

echo Step 3: Seeding system settings...
%MYSQL_PATH% -u %DB_USER% -p%DB_PASS% %DB_NAME% < database\seed_settings.sql
if errorlevel 1 (
    echo ERROR: Failed to seed settings
    pause
    exit /b 1
)
echo Settings seeded successfully!
echo.

echo Step 4: Verifying setup...
%MYSQL_PATH% -u %DB_USER% -p%DB_PASS% %DB_NAME% -e "SELECT COUNT(*) as categories FROM categories; SELECT COUNT(*) as nominees FROM nominees; SELECT COUNT(*) as admin_users FROM admin_users;"
if errorlevel 1 (
    echo ERROR: Failed to verify database
    pause
    exit /b 1
)
echo.

echo ========================================
echo Database setup completed successfully!
echo ========================================
echo.
echo Database: %DB_NAME%
echo User: %DB_USER%
echo.
echo You can now start the server with: start-local.bat
echo.
pause
