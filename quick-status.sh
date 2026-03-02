#!/bin/bash
# Quick status check script

echo "=== Voting Nova Status Check ==="
echo ""

echo "1. Service Status:"
sudo systemctl status voting-nova --no-pager | head -10
echo ""

echo "2. Port 8080:"
sudo ss -tuln | grep 8080 || echo "Port not listening"
echo ""

echo "3. Recent Logs (last 5 entries):"
sudo journalctl -u voting-nova -n 5 --no-pager
echo ""

echo "4. Database Connection Test:"
mysql -u votingnova_user -pwillrocks votingnova -e "SELECT COUNT(*) as admin_count FROM admin_users WHERE username='admin';" 2>/dev/null || echo "Database connection failed"
echo ""

echo "5. Application Files:"
ls -la /var/www/novatech/router.php 2>/dev/null && echo "✓ router.php exists" || echo "✗ router.php missing"
ls -la /var/www/novatech/.env 2>/dev/null && echo "✓ .env exists" || echo "✗ .env missing"
echo ""

echo "=== Done ==="
