#!/bin/bash

echo "========================================"
echo "USSD Voting System - Local Server"
echo "========================================"
echo ""

# Check if PHP is available
if ! command -v php &> /dev/null; then
    echo "ERROR: PHP is not installed"
    echo "Please install PHP first"
    exit 1
fi

echo "Starting PHP development server..."
echo ""
echo "Access the application at:"
echo "  - Admin Dashboard: http://localhost:8000/admin/"
echo "  - USSD Simulator: http://localhost:8000/simulator/"
echo "  - API Endpoint: http://localhost:8000/api/ussd.php"
echo ""
echo "Press Ctrl+C to stop the server"
echo ""

php -S localhost:8000
