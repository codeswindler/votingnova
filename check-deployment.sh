#!/bin/bash
# Quick script to check deployment status

echo "=== Checking Voting Nova Deployment ==="
echo ""

# Check service status
echo "1. Service Status:"
sudo systemctl status voting-nova --no-pager -l | head -15
echo ""

# Check if port is listening
echo "2. Port 8080 Status:"
if command -v ss &> /dev/null; then
    sudo ss -tuln | grep :8080 || echo "Port 8080 not found in listening ports"
else
    sudo netstat -tuln 2>/dev/null | grep :8080 || echo "Port 8080 not found (netstat not available)"
fi
echo ""

# Check firewall
echo "3. Firewall Status:"
if command -v ufw &> /dev/null; then
    sudo ufw status | grep 8080 || echo "Port 8080 not in firewall rules"
else
    echo "UFW not installed or not active"
fi
echo ""

# Test local connection
echo "4. Testing local connection:"
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" http://localhost:8080/admin/ || echo "Connection failed"
echo ""

# Check service logs
echo "5. Recent Service Logs:"
sudo journalctl -u voting-nova -n 10 --no-pager
echo ""

echo "=== Done ==="
