@echo off
echo ========================================
echo USSD Voting System - Local Server
echo ========================================
echo.

REM Check if PHP is available
php -v >nul 2>&1
if errorlevel 1 (
    echo ERROR: PHP is not installed or not in PATH
    echo Please install PHP or use XAMPP/WAMP
    pause
    exit /b 1
)

echo Starting PHP development server...
echo.
echo Access the application at:
echo   - Admin Dashboard: http://localhost:8000/admin/
echo   - USSD Simulator: http://localhost:8000/simulator/
echo   - API Endpoint: http://localhost:8000/api/ussd.php
echo.
echo Press Ctrl+C to stop the server
echo.

php -S localhost:8000

pause
