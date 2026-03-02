#!/bin/bash
# Fix Apache to only use port 6000

echo "=== Fixing Apache Port Configuration ==="

# 1. Check what's using ports 80 and 443
echo ""
echo "Checking what's using ports 80 and 443..."
netstat -tulpn | grep -E ':(80|443) ' || echo "No process found (might need sudo)"

# 2. Backup ports.conf
echo ""
echo "Backing up ports.conf..."
cp /etc/apache2/ports.conf /etc/apache2/ports.conf.backup

# 3. Comment out Listen 80 and Listen 443, ensure Listen 6000 exists
echo ""
echo "Updating ports.conf to only use port 6000..."
sed -i 's/^Listen 80/#Listen 80/' /etc/apache2/ports.conf
sed -i 's/^Listen 443/#Listen 443/' /etc/apache2/ports.conf

# Ensure Listen 6000 exists
if ! grep -q "^Listen 6000" /etc/apache2/ports.conf; then
    echo "Listen 6000" >> /etc/apache2/ports.conf
fi

echo "✓ Updated ports.conf"
echo ""
echo "Current ports.conf content:"
grep "^Listen" /etc/apache2/ports.conf || echo "(no Listen directives found)"

# 4. Disable default site if it exists (it uses port 80)
if [ -f /etc/apache2/sites-enabled/000-default.conf ]; then
    echo ""
    echo "Disabling default site (uses port 80)..."
    a2dissite 000-default.conf
    echo "✓ Default site disabled"
fi

if [ -f /etc/apache2/sites-enabled/default-ssl.conf ]; then
    echo ""
    echo "Disabling default SSL site (uses port 443)..."
    a2dissite default-ssl.conf
    echo "✓ Default SSL site disabled"
fi

# 5. Test configuration
echo ""
echo "Testing Apache configuration..."
apache2ctl configtest

# 6. Start Apache
echo ""
echo "Starting Apache..."
systemctl start apache2

# 7. Check status
echo ""
echo "Apache status:"
systemctl status apache2 --no-pager -l

# 8. Verify port 6000 is listening
echo ""
echo "Checking if port 6000 is listening..."
netstat -tulpn | grep :6000 || echo "Port 6000 not found (might need a moment)"

echo ""
echo "=== Done ==="
