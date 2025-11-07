# 📦 Installation Guide - DNS Change Alert Module

**Version:** 1.0.0 | **For ClientExec:** 6.0+ | **Updated:** 2025-10-29

---

## 📋 Table of Contents

- [Prerequisites](#prerequisites)
- [Installation Methods](#installation-methods)
- [Database Setup](#database-setup)
- [Configuration](#configuration)
- [Cron Setup](#cron-setup)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)

---

## ✅ Prerequisites

### System Requirements
- **ClientExec** 6.0 or higher
- **PHP** 8.0+ with extensions:
  - `mysqli` or `pdo_mysql`
  - `curl`
  - `json`
  - `mb

string`
- **MySQL** 5.7+ or **MariaDB** 10.3+
- **dig** utility (for DNS lookups)
  ```bash
  # Install on Debian/Ubuntu
  sudo apt-get install dnsutils

  # Install on CentOS/RHEL
  sudo yum install bind-utils
  ```
- **Mail server** (SMTP or local sendmail)

### Permissions
- Write access to ClientExec plugins directory
- Database CREATE TABLE permissions
- Cron access for automated monitoring

---

## 🚀 Installation Methods

### Method 1: Git Clone (Recommended)

```bash
# Navigate to ClientExec plugins directory
cd /var/www/html/clientexec/plugins

# Clone the repository
git clone https://github.com/itcms-gr/clientexec-dns-alert.git dns_alert

# Set permissions
chmod 755 dns_alert
chmod 644 dns_alert/*.php
chmod 777 dns_alert/logs

# Install dependencies (if using Composer)
cd dns_alert
composer install --no-dev
```

### Method 2: Manual Upload

```bash
# Download the latest release
wget https://github.com/itcms-gr/clientexec-dns-alert/archive/v1.0.0.zip

# Extract
unzip v1.0.0.zip

# Move to plugins directory
mv clientexec-dns-alert-1.0.0 /var/www/html/clientexec/plugins/dns_alert

# Set permissions
cd /var/www/html/clientexec/plugins
chmod 755 dns_alert
chmod 644 dns_alert/*.php
chmod 777 dns_alert/logs
```

### Method 3: ClientExec Plugin Manager

```
1. Login to ClientExec Admin
2. Navigate to: Settings → Plugins → Install New
3. Upload: dns-alert-v1.0.0.zip
4. Click: Install
5. Enable the plugin
```

---

## 🗄️ Database Setup

### Automatic Installation

```bash
cd /var/www/html/clientexec/plugins/dns_alert
php install.php
```

**This will create:**
- `dns_alert_snapshots` - DNS record snapshots
- `dns_alert_changes` - Detected changes log
- `dns_alert_notifications` - Notification delivery log
- `dns_alert_config` - Module configuration

### Manual Installation

```sql
-- Connect to ClientExec database
mysql -u clientexec_user -p clientexec_db < database/schema.sql
```

### Verify Installation

```sql
SHOW TABLES LIKE 'dns_alert%';

-- Should show:
-- dns_alert_snapshots
-- dns_alert_changes
-- dns_alert_notifications
-- dns_alert_config
```

---

## ⚙️ Configuration

### Step 1: Copy Configuration File

```bash
cd /var/www/html/clientexec/plugins/dns_alert
cp config.example.php config.php
nano config.php
```

### Step 2: Basic Configuration

```php
<?php
return [
    // Module Settings
    'enabled' => true,
    'debug_mode' => false,

    // DNS Monitoring
    'monitor' => [
        'enabled' => true,
        'check_interval' => 300,  // 5 minutes
        'record_types' => ['A', 'AAAA', 'MX', 'CNAME', 'TXT', 'NS'],
        'dig_command' => '/usr/bin/dig',
    ],

    // Notifications
    'notifications' => [
        'pre_change' => true,
        'post_change' => true,
        'delay_minutes' => 60,  // 1 hour before applying
        'from_email' => 'dns-alerts@yourdomain.com',
        'from_name' => 'DNS Alert System',
    ],

    // Email Settings
    'email' => [
        'method' => 'smtp',  // smtp, sendmail, mail
        'smtp_host' => 'mail.yourdomain.com',
        'smtp_port' => 587,
        'smtp_user' => 'alerts@yourdomain.com',
        'smtp_pass' => 'your-password',
        'smtp_encryption' => 'tls',  // tls, ssl, none
    ],

    // Compliance (NIS2)
    'compliance' => [
        'nis2_mode' => true,
        'audit_retention_days' => 730,  // 2 years
        'require_owner_email' => true,
        'require_confirmation' => false,
    ],

    // Security
    'security' => [
        'api_enabled' => true,
        'api_key' => 'generate-random-key-here',
        'encrypt_notifications' => false,
        'allowed_ips' => [],  // Empty = all IPs
    ],
];
```

### Step 3: Enable in ClientExec

```
1. Login to ClientExec Admin Panel
2. Navigate to: Settings → Plugins
3. Find: "DNS Change Alert"
4. Click: Enable
5. Click: Configure
6. Fill in settings
7. Save
```

---

## ⏰ Cron Setup

### Automated Monitoring

Add to crontab for automated DNS monitoring:

```bash
crontab -e

# Add this line (runs every 5 minutes)
*/5 * * * * /usr/bin/php /var/www/html/clientexec/plugins/dns_alert/cron/monitor.php >> /var/log/dns-alert.log 2>&1
```

### Alternative: Systemd Timer

```bash
# Create service file
sudo nano /etc/systemd/system/dns-alert.service

[Unit]
Description=DNS Change Alert Monitor
After=network.target

[Service]
Type=oneshot
User=www-data
ExecStart=/usr/bin/php /var/www/html/clientexec/plugins/dns_alert/cron/monitor.php

# Create timer file
sudo nano /etc/systemd/system/dns-alert.timer

[Unit]
Description=Run DNS Alert Monitor every 5 minutes

[Timer]
OnBootSec=5min
OnUnitActiveSec=5min
Unit=dns-alert.service

[Install]
WantedBy=timers.target

# Enable and start
sudo systemctl enable dns-alert.timer
sudo systemctl start dns-alert.timer
sudo systemctl status dns-alert.timer
```

---

## 🧪 Testing

### Test 1: DNS Monitoring

```bash
cd /var/www/html/clientexec/plugins/dns_alert
php tests/test-monitor.php
```

**Expected Output:**
```
✓ DNS monitoring engine loaded
✓ Connected to database
✓ Fetching DNS records for test.com
✓ A records found: 192.0.2.1
✓ Snapshot saved
```

### Test 2: Change Detection

```bash
# Create initial snapshot
php tests/test-snapshot.php example.com

# Manually change DNS (in your DNS panel)

# Check for changes
php tests/test-detect.php example.com
```

**Expected Output:**
```
✓ Change detected!
  Type: A
  Old: 192.0.2.1
  New: 192.0.2.2
  Detected at: 2025-10-29 10:30:00
```

### Test 3: Notifications

```bash
php tests/test-notification.php owner@example.com
```

**Expected Output:**
```
✓ Email sent successfully
  To: owner@example.com
  Subject: [ALERT] DNS Change Detected
  Delivery ID: abc123xyz
```

### Test 4: Full Workflow

```bash
php tests/test-workflow.php
```

**This will:**
1. Create DNS snapshot
2. Simulate change
3. Detect change
4. Send pre-change alert
5. Wait (simulated)
6. Apply change
7. Send post-change confirmation
8. Log to audit trail

---

## 🔍 Verification

### Check Module Status

```bash
# Via CLI
php bin/console dns-alert:status

# Via web
https://your-clientexec.com/admin/plugins/dns_alert/status
```

**Should show:**
```
DNS Alert Module Status
========================
✓ Module: Installed & Enabled
✓ Database: 4 tables created
✓ Cron: Running (last: 2 minutes ago)
✓ Monitoring: 156 domains
✓ Notifications: 45 sent today
✓ Compliance: NIS2 Mode Active
```

### Check Logs

```bash
# Module logs
tail -f /var/www/html/clientexec/plugins/dns_alert/logs/dns-alert.log

# System logs
tail -f /var/log/dns-alert.log

# Error logs
tail -f /var/www/html/clientexec/plugins/dns_alert/logs/error.log
```

---

## 🛠️ Troubleshooting

### Issue 1: Module Not Showing

**Problem:** Plugin doesn't appear in ClientExec

**Solutions:**
```bash
# Check file permissions
ls -la /var/www/html/clientexec/plugins/dns_alert/

# Check plugin file exists
ls -la /var/www/html/clientexec/plugins/dns_alert/PluginDnsAlert.php

# Check PHP syntax
php -l /var/www/html/clientexec/plugins/dns_alert/PluginDnsAlert.php

# Clear ClientExec cache
rm -rf /var/www/html/clientexec/cache/*
```

### Issue 2: Database Errors

**Problem:** "Table dns_alert_snapshots doesn't exist"

**Solutions:**
```bash
# Re-run database installation
php install.php

# Or manually
mysql -u user -p database < database/schema.sql

# Check tables exist
mysql -u user -p -e "USE database; SHOW TABLES LIKE 'dns_alert%';"
```

### Issue 3: DNS Lookups Failing

**Problem:** "dig: command not found"

**Solutions:**
```bash
# Install dig
sudo apt-get install dnsutils  # Debian/Ubuntu
sudo yum install bind-utils     # CentOS/RHEL

# Verify installation
which dig
dig google.com

# Check config path
nano config.php
# Set: 'dig_command' => '/usr/bin/dig',
```

### Issue 4: Emails Not Sending

**Problem:** Notifications not being delivered

**Solutions:**
```bash
# Test SMTP connection
php tests/test-smtp.php

# Check email config
nano config.php
# Verify SMTP settings

# Check mail logs
tail -f /var/log/mail.log

# Test sendmail
echo "Test" | mail -s "Test" you@example.com
```

### Issue 5: Permission Denied

**Problem:** Can't write to logs directory

**Solutions:**
```bash
# Fix permissions
chmod 777 /var/www/html/clientexec/plugins/dns_alert/logs

# Fix ownership
chown -R www-data:www-data /var/www/html/clientexec/plugins/dns_alert

# Create logs directory if missing
mkdir -p /var/www/html/clientexec/plugins/dns_alert/logs
chmod 777 /var/www/html/clientexec/plugins/dns_alert/logs
```

### Issue 6: Cron Not Running

**Problem:** Monitoring not happening automatically

**Solutions:**
```bash
# Check crontab
crontab -l

# Test cron script manually
php /var/www/html/clientexec/plugins/dns_alert/cron/monitor.php

# Check cron logs
grep CRON /var/log/syslog

# Ensure correct user
# Cron should run as web server user (www-data, apache, nginx)
sudo -u www-data crontab -e
```

---

## 📊 Post-Installation Checklist

- [ ] Module files uploaded
- [ ] Permissions set (755 for dirs, 644 for files, 777 for logs)
- [ ] Database tables created
- [ ] Configuration file edited
- [ ] SMTP settings configured
- [ ] Module enabled in ClientExec
- [ ] Cron job configured
- [ ] Test monitoring completed
- [ ] Test notification sent
- [ ] First DNS snapshot created
- [ ] Logs directory writable
- [ ] API key generated (if using API)

---

## 🎯 Next Steps

After successful installation:

1. **Read:** [CONFIG.md](CONFIG.md) for detailed configuration options
2. **Setup:** Email templates in `templates/email/`
3. **Configure:** Which domains to monitor
4. **Test:** Send test alerts
5. **Monitor:** Check dashboard for activity
6. **Compliance:** Generate first NIS2 report

---

## 📞 Need Help?

- **Email:** contact@itcms.gr
- **Website:** https://itcms.gr/
- **Documentation:** Full guides in this repository
- **Issues:** Report bugs on GitHub

---

**Installation complete! Your DNS monitoring is now active.** ✅

