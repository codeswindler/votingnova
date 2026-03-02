@echo off
echo ========================================
echo Clear Seed Data Script
echo ========================================
echo.
echo WARNING: This will delete ALL votes, transactions, and sessions!
echo This should only be run before going live with real votes.
echo.
set /p confirm="Are you sure you want to clear all seed data? (yes/no): "
if /i not "%confirm%"=="yes" (
    echo Operation cancelled.
    pause
    exit /b 0
)
echo.

set MYSQL_PATH="C:\Program Files\MariaDB 12.1\bin\mysql.exe"
set DB_USER=root
set DB_PASS=RootPass123!
set DB_NAME=votingnova

echo Clearing all seed data...
%MYSQL_PATH% -u %DB_USER% -p%DB_PASS% %DB_NAME% < database\clear_seed_data.sql
if errorlevel 1 (
    echo ERROR: Failed to clear seed data
    pause
    exit /b 1
)
echo.
echo Seed data cleared successfully!
echo The system is now ready for live votes.
echo.
pause
