@echo off
echo ========================================
echo User Management Tables Migration
echo ========================================
echo.

set MYSQL_PATH="C:\Program Files\MariaDB 12.1\bin\mysql.exe"
set DB_USER=root
set DB_PASS=RootPass123!
set DB_NAME=votingnova

echo Running migration to create user management tables...
%MYSQL_PATH% -u %DB_USER% -p%DB_PASS% %DB_NAME% < database\migrate_user_tables.sql
if errorlevel 1 (
    echo ERROR: Failed to run migration
    pause
    exit /b 1
)
echo.
echo Migration completed successfully!
echo.
echo Created tables:
echo   - system_users
echo   - otp_codes
echo   - system_settings
echo.
pause
