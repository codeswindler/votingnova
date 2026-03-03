# Tailing logs while testing

Use these on your **VPS** (where the app runs) to watch errors in real time while you test USSD, payments, or the voting page.

## 1. Nginx + PHP built-in server (voting-nova service on port 8080)

If your app runs as a systemd service (`voting-nova`) using the PHP built-in server, PHP `error_log()` goes to the service’s stderr and is captured by journald. Tail it with:

```bash
sudo journalctl -u voting-nova -f
```

Leave this running and trigger USSD or a payment; errors appear as they happen.

To see the last 100 lines then keep following:

```bash
sudo journalctl -u voting-nova -n 100 -f
```

## 2. Apache + PHP error log (if you use Apache)

Your deploy script configures a dedicated error log for the Voting Nova site. Tail it:

```bash
sudo tail -f /var/log/apache2/voting-nova-error.log
```

This shows:

- `error_log()` output from PHP (USSD, Paystack, M-Pesa, SMS, etc.)
- Apache errors for that vhost

Leave this running in one terminal, then trigger USSD or a payment in another; you’ll see failures as they happen.

### If voting-nova-error.log doesn’t exist (Apache only)

Use the main Apache error log (all sites). PHP `error_log()` goes here too:

```bash
sudo tail -f /var/log/apache2/error.log
```

To create the dedicated log so it’s used from now on:

```bash
sudo touch /var/log/apache2/voting-nova-error.log
sudo chown root:adm /var/log/apache2/voting-nova-error.log
sudo chmod 640 /var/log/apache2/voting-nova-error.log
sudo systemctl reload apache2
```

Then tail it:

```bash
sudo tail -f /var/log/apache2/voting-nova-error.log
```

On some setups PHP may log to a different path. To check:

```bash
php -i | grep error_log
```

## 3. Follow and filter (e.g. only USSD / payment)

**voting-nova service (journalctl):**
```bash
sudo journalctl -u voting-nova -f | grep --line-buffered -E 'USSD|Payment|Paystack|M-Pesa|STK'
```

**Apache:**
```bash
sudo tail -f /var/log/apache2/voting-nova-error.log | grep --line-buffered -E 'USSD|Payment|Paystack|M-Pesa|STK'
```

## 4. Last N lines before tailing

```bash
# Show last 50 lines, then keep following
sudo tail -n 50 -f /var/log/apache2/voting-nova-error.log
```

## 5. Rotate / clear (optional)

If the log gets large and you want a clean view:

```bash
sudo truncate -s 0 /var/log/apache2/voting-nova-error.log
sudo systemctl reload apache2
# then tail again
sudo tail -f /var/log/apache2/voting-nova-error.log
```

---

**Quick reference**

| What you’re testing | Log messages to look for |
|----------------------|---------------------------|
| USSD flow            | `USSD Request`, `USSD Response`, `USSD Payment` |
| Paystack             | `Paystack Charge`, `Paystack Error` |
| M-Pesa STK           | `STK Push`, `M-Pesa Token` |
| Payment failed       | `Both providers failed`, `Payment initiation failed` |
