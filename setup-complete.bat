@echo off
echo ========================================
echo Complete Setup Script
echo ========================================
echo.

echo This script will:
echo 1. Setup the database
echo 2. Verify the installation
echo 3. Start the local server
echo.

pause

echo.
echo ========================================
echo Step 1: Database Setup
echo ========================================
call setup-database.bat
if errorlevel 1 (
    echo Database setup failed. Please check the errors above.
    pause
    exit /b 1
)

echo.
echo ========================================
echo Step 2: Verify Installation
echo ========================================
php setup.php
if errorlevel 1 (
    echo Verification found issues. Please review above.
    pause
    exit /b 1
)

echo.
echo ========================================
echo Step 3: Starting Server
echo ========================================
echo.
echo Server will start in 3 seconds...
timeout /t 3 /nobreak >nul

call start-local.bat
