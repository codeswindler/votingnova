# Apache Startup Troubleshooting

If Apache fails to start during deployment, follow these steps:

## 1. Check Apache Error

```bash
sudo systemctl status apache2.service
sudo journalctl -xeu apache2.service
```

## 2. Common Issues and Fixes

### Issue: Port Already in Use
If another service is using port 80 or 443:

```bash
# Check what's using port 80
sudo netstat -tulpn | grep :80
# or
sudo lsof -i :80

# Check what's using port 443
sudo netstat -tulpn | grep :443
# or
sudo lsof -i :443
```

**Fix**: Stop the conflicting service or configure Apache to use different ports.

### Issue: Configuration Error
Test Apache configuration:

```bash
sudo apache2ctl configtest
```

**Fix**: Fix any syntax errors shown.

### Issue: Missing Modules
If modules are missing:

```bash
sudo a2enmod rewrite ssl headers
sudo systemctl restart apache2
```

### Issue: Permission Problems
Check Apache error log:

```bash
sudo tail -f /var/log/apache2/error.log
```

## 3. Manual Apache Start

After fixing issues, try starting Apache manually:

```bash
sudo systemctl start apache2
sudo systemctl enable apache2
sudo systemctl status apache2
```

## 4. Continue Deployment

Once Apache is running, you can continue with the deployment script or run it again (it will skip already completed steps).
