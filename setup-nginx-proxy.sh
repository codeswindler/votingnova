#!/bin/bash
# Setup Nginx reverse proxy for HTTPS on port 443 -> PHP on port 8080

echo "Setting up Nginx reverse proxy for HTTPS..."

# Install nginx if not installed
if ! command -v nginx &> /dev/null; then
    apt update
    apt install -y nginx
fi

# Create nginx config (HTTP only first, SSL will be added by certbot)
cat > /etc/nginx/sites-available/voting-nova <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name voting.novotechafrica.co.ke www.voting.novotechafrica.co.ke;

    # Proxy to PHP built-in server
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
        
        # Timeout settings
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
}
EOF

# Enable site
ln -sf /etc/nginx/sites-available/voting-nova /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Test nginx config
if nginx -t 2>&1 | grep -q "test is successful"; then
    echo "✓ Nginx configuration is valid"
    
    # Start/restart nginx
    systemctl restart nginx
    systemctl enable nginx
    
    echo ""
    echo "✓ Nginx started"
    echo ""
    echo "Next steps:"
    echo "1. Install SSL certificate (this will automatically configure HTTPS):"
    echo "   sudo certbot --nginx -d voting.novotechafrica.co.ke -d www.voting.novotechafrica.co.ke"
    echo ""
    echo "2. After SSL is installed, update Advanta callback URL to:"
    echo "   https://voting.novotechafrica.co.ke/api/ussd.php"
    echo ""
    echo "3. For now, you can test with HTTP:"
    echo "   http://voting.novotechafrica.co.ke/api/ussd.php"
else
    echo "✗ Nginx configuration has errors:"
    nginx -t
    echo ""
    echo "Please check existing nginx configurations for conflicts."
    exit 1
fi
