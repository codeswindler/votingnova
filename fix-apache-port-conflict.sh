#!/bin/bash
# Quick fix for Apache port conflict

echo "Checking what's using ports 80 and 443..."

echo ""
echo "=== Port 80 ==="
sudo netstat -tulpn | grep :80 || sudo ss -tulpn | grep :80
sudo lsof -i :80 2>/dev/null || echo "lsof not available"

echo ""
echo "=== Port 443 ==="
sudo netstat -tulpn | grep :443 || sudo ss -tulpn | grep :443
sudo lsof -i :443 2>/dev/null || echo "lsof not available"

echo ""
echo "=== Checking for Nginx ==="
if systemctl is-active --quiet nginx; then
    echo "⚠️  Nginx is running and likely using ports 80/443"
    echo "Options:"
    echo "  1. Stop Nginx: sudo systemctl stop nginx"
    echo "  2. Or configure Apache to use different ports"
    echo "  3. Or use Nginx as reverse proxy for Apache"
elif [ -f /etc/nginx/nginx.conf ]; then
    echo "⚠️  Nginx is installed but not running"
else
    echo "✓ Nginx not found"
fi

echo ""
echo "=== Checking for other web servers ==="
if systemctl list-units --type=service | grep -E "(httpd|lighttpd|caddy)"; then
    echo "Found other web server services"
else
    echo "No other web servers found"
fi
