#!/bin/bash
# Quick fix to configure Apache for port 6000

echo "Configuring Apache for port 6000..."

# Add Listen 6000 to ports.conf
if ! grep -q "Listen 6000" /etc/apache2/ports.conf; then
    echo "Listen 6000" >> /etc/apache2/ports.conf
    echo "✓ Added Listen 6000 to ports.conf"
else
    echo "✓ Port 6000 already in ports.conf"
fi

# Create/update virtual host for port 6000
cat > /etc/apache2/sites-available/voting-nova.conf <<'EOF'
<VirtualHost *:6000>
    ServerName voting.novotechafrica.co.ke
    ServerAlias www.voting.novotechafrica.co.ke
    DocumentRoot /var/www/novatech

    <Directory /var/www/novatech>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/voting-nova-error.log
    CustomLog ${APACHE_LOG_DIR}/voting-nova-access.log combined
</VirtualHost>
EOF

echo "✓ Virtual host created for port 6000"

# Enable site
a2ensite voting-nova.conf

# Test Apache config
echo ""
echo "Testing Apache configuration..."
apache2ctl configtest

# Start Apache
echo ""
echo "Starting Apache..."
systemctl start apache2
systemctl status apache2

echo ""
echo "✓ Apache configured for port 6000"
echo "Access your site at: http://voting.novotechafrica.co.ke:6000"
