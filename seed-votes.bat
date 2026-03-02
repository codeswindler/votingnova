@echo off
echo ========================================
echo Seed Votes Script (Simulation Data)
echo ========================================
echo.
echo This script adds sample votes and transactions for dashboard testing.
echo These are simulation data only - will be cleared before going live.
echo.

set MYSQL_PATH="C:\Program Files\MariaDB 12.1\bin\mysql.exe"
set DB_USER=root
set DB_PASS=RootPass123!
set DB_NAME=votingnova

echo Seeding sample votes and transactions...
%MYSQL_PATH% -u %DB_USER% -p%DB_PASS% %DB_NAME% < database\seed_votes.sql
if errorlevel 1 (
    echo ERROR: Failed to seed votes
    pause
    exit /b 1
)
echo.
echo Sample votes seeded successfully!
echo You can now view stats in the admin dashboard.
echo.
pause
