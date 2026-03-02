#!/bin/bash
# Setup Nginx reverse proxy for HTTPS on port 443 -> PHP on port 8080

echo "Setting up Nginx reverse proxy for HTTPS..."

# Install nginx if not installed
if ! command -v nginx &> /dev/null; then
    apt update
    apt install -y nginx
fi

# Create nginx config
cat > /etc/nginx/sites-available/voting-nova <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name voting.novotechafrica.co.ke www.voting.novotechafrica.co.ke;

    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name voting.novotechafrica.co.ke www.voting.novotechafrica.co.ke;

    # SSL certificates (will be set up by Certbot)
    ssl_certificate /etc/letsencrypt/live/voting.novotechafrica.co.ke/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/voting.novotechafrica.co.ke/privkey.pem;
    
    # SSL configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

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
nginx -t

if [ $? -eq 0 ]; then
    echo "✓ Nginx configuration is valid"
    echo ""
    echo "Next steps:"
    echo "1. Install SSL certificate:"
    echo "   sudo certbot --nginx -d voting.novotechafrica.co.ke -d www.voting.novotechafrica.co.ke"
    echo ""
    echo "2. Start/restart nginx:"
    echo "   sudo systemctl restart nginx"
    echo ""
    echo "3. Update Advanta callback URL to:"
    echo "   https://voting.novotechafrica.co.ke/api/ussd.php"
else
    echo "✗ Nginx configuration has errors. Please fix them first."
    exit 1
fi
