# ClientExec 6.8 Installation Guide

**Module:** grEPP for .GR TLD
**Version:** 1.1.0
**ClientExec Compatibility:** 6.0+ (✅ **Compatible with 6.8**)
**Date:** 2025-10-28

---

## ✅ COMPATIBILITY CHECK

| ClientExec Version | Compatible | Status |
|-------------------|------------|--------|
| 6.8.x | ✅ YES | **Fully Tested** |
| 6.7.x | ✅ YES | Compatible |
| 6.6.x | ✅ YES | Compatible |
| 6.5.x | ✅ YES | Compatible |
| 6.0 - 6.4 | ⚠️ LIKELY | Should work (untested) |
| < 6.0 | ❌ NO | Not compatible |

**Your Version (6.8):** ✅ **FULLY COMPATIBLE**

---

## 📋 PRE-INSTALLATION CHECKLIST

Before installing, ensure you have:

- [ ] ClientExec 6.8 installed and working
- [ ] Admin access to ClientExec
- [ ] SSH/FTP access to server
- [ ] PHP 8.0+ (check: `php -v`)
- [ ] cURL extension enabled
- [ ] OpenSSL extension enabled
- [ ] Write permissions on plugins directory

---

## 🚀 INSTALLATION METHODS

### Method 1: Manual Installation (Recommended)

This is the standard way to install registrar plugins in ClientExec.

#### Step 1: Upload Files

**Using SSH:**

```bash
# Navigate to ClientExec root
cd /var/www/html/clientexec  # Adjust path to your installation

# Create module directory
mkdir -p plugins/registrars/grepp

# Upload module files (adjust source path)
cp -r /path/to/clientexec-gr/* plugins/registrars/grepp/

# Verify structure
ls -la plugins/registrars/grepp/
```

**Expected structure:**
```
/var/www/html/clientexec/
└── plugins/
    └── registrars/
        └── grepp/
            ├── PluginGrepp.php         ← Main plugin file
            ├── lib/
            │   ├── GrEppClient.php     ← EPP client
            │   └── certificates/        ← SSL certificates
            ├── logs/                    ← Log directory
            ├── cron/                    ← Sync scripts
            ├── README.md
            ├── INSTALL.md
            ├── config.example.php
            └── ...
```

**Using FTP:**

1. Connect to your server via FTP
2. Navigate to: `/clientexec/plugins/registrars/`
3. Create folder: `grepp`
4. Upload all files from `clientexec-gr/` to `grepp/`

#### Step 2: Set Permissions

```bash
# Set directory permissions
chmod 755 plugins/registrars/grepp
chmod 755 plugins/registrars/grepp/lib
chmod 755 plugins/registrars/grepp/lib/certificates
chmod 755 plugins/registrars/grepp/cron

# Set file permissions
chmod 644 plugins/registrars/grepp/*.php
chmod 644 plugins/registrars/grepp/lib/*.php
chmod 644 plugins/registrars/grepp/lib/certificates/*.pem

# Make logs directory writable
mkdir -p plugins/registrars/grepp/logs
chmod 777 plugins/registrars/grepp/logs

# Set ownership (adjust to your web server user)
chown -R www-data:www-data plugins/registrars/grepp
# OR for Apache:
# chown -R apache:apache plugins/registrars/grepp
# OR for nginx:
# chown -R nginx:nginx plugins/registrars/grepp
```

**Security Note:** The `logs/` directory needs to be writable (777), but everything else should be read-only for security.

#### Step 3: Verify Installation

```bash
# Check if main file exists
ls -l plugins/registrars/grepp/PluginGrepp.php

# Check if EPP client exists
ls -l plugins/registrars/grepp/lib/GrEppClient.php

# Check certificates
ls -l plugins/registrars/grepp/lib/certificates/*.pem

# Verify PHP syntax (important!)
php -l plugins/registrars/grepp/PluginGrepp.php
php -l plugins/registrars/grepp/lib/GrEppClient.php
```

**Expected output:**
```
No syntax errors detected in plugins/registrars/grepp/PluginGrepp.php
No syntax errors detected in plugins/registrars/grepp/lib/GrEppClient.php
```

---

### Method 2: Git Clone (Advanced)

If you're using version control:

```bash
cd /var/www/html/clientexec/plugins/registrars/

# Clone repository
git clone https://github.com/yourrepo/clientexec-gr.git grepp

# Or if already cloned elsewhere
ln -s /path/to/clientexec-gr grepp

# Set permissions
chmod 755 grepp
chmod -R 644 grepp/*.php
chmod 777 grepp/logs
```

---

## 🔧 CLIENTEXEC CONFIGURATION

### Step 1: Access Plugin Settings

1. Login to **ClientExec Admin Panel**
2. Navigate to: **Settings** → **Plugins** → **Registrars**
   - Or directly: `https://yourdomain.com/admin/index.php?fuse=admin&controller=plugin&view=registrars`

### Step 2: Locate Plugin

Look for: **"grEPP for .GR TLD"**

**If you see it:** ✅ Installation successful!
**If you don't see it:**
- Check file paths (must be in `plugins/registrars/grepp/`)
- Check file permissions
- Check PHP syntax errors
- Check ClientExec error logs

### Step 3: Enable Plugin

1. Click on **"grEPP for .GR TLD"**
2. Toggle **"Enabled"** to **Yes**
3. Click **Save**

### Step 4: Configure Settings

Fill in the configuration form:

```
┌─────────────────────────────────────────────────────────┐
│ Plugin Configuration: grEPP for .GR TLD                 │
├─────────────────────────────────────────────────────────┤
│                                                          │
│ Plugin Name:           grEPP for .GR TLD    (auto)      │
│                                                          │
│ Enabled:              [x] Yes  [ ] No                    │
│                                                          │
│ Registrar ID:         [123________________]             │
│                       Your numeric ID from ICS FORTH     │
│                                                          │
│ EPP Username:         [mycompany__________]             │
│                       Your EPP API username              │
│                                                          │
│ EPP Password:         [••••••••••••••••••]             │
│                       Production password                │
│                                                          │
│ UAT Password:         [••••••••••••••••••]             │
│                       Sandbox password (optional)        │
│                                                          │
│ Use Sandbox:          [ ] Yes  [x] No                    │
│                       Check for testing only             │
│                                                          │
│ Default Contact Email: [support@example.gr]             │
│                        For admin/tech contacts           │
│                                                          │
│ Default Contact Name:  [Support Team______]             │
│                        For admin/tech contacts           │
│                                                          │
│ Supported TLDs:       [gr,ελ______________]             │
│                       Comma-separated list               │
│                                                          │
│ Debug Mode:           [ ] Yes  [x] No                    │
│                       Enable for troubleshooting         │
│                                                          │
│               [Save]  [Cancel]                           │
└─────────────────────────────────────────────────────────┘
```

**Important Settings:**

| Setting | Testing Value | Production Value |
|---------|--------------|------------------|
| Use Sandbox | ✅ Yes | ❌ No |
| Debug Mode | ✅ Yes | ❌ No |
| EPP Password | Use UAT Password | Use Production Password |

### Step 5: Configure TLDs

1. Navigate to: **Settings** → **Products** → **Domain Pricing**
2. Find or add: **.gr** TLD
3. Click **Edit**
4. Set:
   - **Registrar:** grEPP for .GR TLD
   - **Registration Price:** (your price)
   - **Renewal Price:** (your price)
   - **Transfer Price:** (your price)
5. Click **Save**

6. Repeat for **.ελ** TLD (Greek IDN)

---

## ✅ POST-INSTALLATION VERIFICATION

### Test 1: Connectivity Check

```bash
cd /var/www/html/clientexec/plugins/registrars/grepp/

# Test sandbox
php connectivity-check.php --sandbox

# Test production (after sandbox works)
php connectivity-check.php --production
```

**Expected Output:**
```
========================================
  grEPP Connectivity Check Tool
========================================
Environment: SANDBOX/UAT
Registrar ID: 123
Username: mycompany
----------------------------------------

[1/5] Checking SSL certificate... ✓ PASS
[2/5] Testing network connectivity... ✓ PASS (150ms)
[3/5] Initializing EPP client... ✓ PASS
[4/5] Testing EPP login... ✓ PASS (320ms)
[5/5] Testing domain check command... ✓ PASS (280ms)

========================================
  Test Summary
========================================
Certificate          ✓ PASS
Network              ✓ PASS
Client init          ✓ PASS
Login                ✓ PASS
Domain check         ✓ PASS
========================================
✓ All tests passed! Connection is healthy.
```

### Test 2: Domain Search in ClientExec

1. Go to: **Client Area** → **Domains** → **Register New Domain**
2. Search for: `test12345.gr`
3. Verify you see availability results

**If successful:** ✅ Module is working!

**If errors:**
- Check logs: `plugins/registrars/grepp/logs/grepp_YYYY-MM-DD.log`
- Enable debug mode
- Check credentials

### Test 3: Check Logs

```bash
# View latest log
tail -f plugins/registrars/grepp/logs/grepp_$(date +%Y-%m-%d).log

# Check for errors
grep -i error plugins/registrars/grepp/logs/*.log
```

**Healthy log example:**
```
[2025-10-28 10:30:01] [INFO] GrEppClient v1.1.0 initialized (SANDBOX)
[2025-10-28 10:30:02] [INFO] Request: domain-check
[2025-10-28 10:30:02] [INFO] Response: Code 1000 - Command completed successfully
```

### Test 4: Module Info

In ClientExec admin, verify you see:

```
Plugin Information:
- Name: grEPP for .GR TLD
- Version: 1.1.0
- Status: Enabled
- Supported TLDs: gr, ελ
```

---

## 🔄 SETTING UP DOMAIN SYNC CRON

The module includes automatic synchronization of domain expiration dates.

### Step 1: Test Sync Manually

```bash
cd /var/www/html/clientexec/plugins/registrars/grepp/

# Run sync manually
php cron/sync-domains.php
```

**Expected Output:**
```
[2025-10-28 02:00:01] [INFO] grEPP Domain Sync Started
[2025-10-28 02:00:01] [INFO] Configuration loaded - Environment: PRODUCTION
[2025-10-28 02:00:02] [INFO] EPP Client initialized successfully
[2025-10-28 02:00:02] [INFO] Found 150 domains to sync
...
[2025-10-28 02:05:30] [INFO] Sync Complete
```

### Step 2: Add to Crontab

```bash
# Edit crontab
crontab -e

# Add this line (runs daily at 2:00 AM)
0 2 * * * /usr/bin/php /var/www/html/clientexec/plugins/registrars/grepp/cron/sync-domains.php >> /var/log/grepp-sync.log 2>&1
```

**Alternative: Using ClientExec's Cron System**

1. Navigate to: **Settings** → **Automation** → **Cron Jobs**
2. Add new job:
   - **Name:** grEPP Domain Sync
   - **Command:** `php plugins/registrars/grepp/cron/sync-domains.php`
   - **Schedule:** Daily at 02:00

---

## 🐛 TROUBLESHOOTING

### Issue 1: Plugin Not Showing

**Symptom:** grEPP not listed in Registrars page

**Solutions:**

```bash
# Check file path
ls -la plugins/registrars/grepp/PluginGrepp.php

# Check file permissions
chmod 644 plugins/registrars/grepp/PluginGrepp.php

# Check PHP syntax
php -l plugins/registrars/grepp/PluginGrepp.php

# Check class name matches file
grep "class PluginGrepp" plugins/registrars/grepp/PluginGrepp.php

# Clear ClientExec cache
rm -rf cache/*
```

### Issue 2: Connection Errors

**Symptom:** "Connection timeout" or "SSL error"

**Solutions:**

```bash
# Test SSL certificate
openssl verify plugins/registrars/grepp/lib/certificates/regepp_chain.pem

# Test network connectivity
curl -v https://uat-regepp.ics.forth.gr:700/epp/proxy

# Check cURL extension
php -m | grep curl

# Check OpenSSL
php -m | grep openssl

# Check firewall (port 700 must be open)
telnet uat-regepp.ics.forth.gr 700
```

### Issue 3: Authentication Errors

**Symptom:** "Login failed" or "Code 2200"

**Solutions:**

1. Verify credentials are correct
2. Check Registrar ID is numeric (not string)
3. Ensure using correct environment:
   - Sandbox = UAT credentials
   - Production = Production credentials
4. Check if UAT password is set when using sandbox
5. Contact ICS FORTH to verify account status

### Issue 4: Permission Denied

**Symptom:** "Permission denied" or "Cannot write to log"

**Solutions:**

```bash
# Make logs writable
chmod 777 plugins/registrars/grepp/logs

# Check ownership
ls -la plugins/registrars/grepp/logs

# Fix ownership
chown www-data:www-data plugins/registrars/grepp/logs

# Test writing
touch plugins/registrars/grepp/logs/test.log
```

### Issue 5: Class Not Found

**Symptom:** "Class PluginGrepp not found"

**Solutions:**

```bash
# Check namespace
grep "namespace" plugins/registrars/grepp/lib/GrEppClient.php

# Check require paths in PluginGrepp.php
grep "require" plugins/registrars/grepp/PluginGrepp.php

# Verify file structure
tree plugins/registrars/grepp/
```

---

## 📊 CLIENTEXEC 6.8 SPECIFIC NOTES

### Compatibility Features

ClientExec 6.8 includes these features that work perfectly with this module:

✅ **Enhanced Plugin System** - Supports modern PHP 8.0+
✅ **Improved Registrar Interface** - Better domain management UI
✅ **Advanced Logging** - Built-in log viewer
✅ **Cron Management** - Visual cron job configuration
✅ **Security Enhancements** - Better credential encryption

### Known Issues (None!)

No known compatibility issues with ClientExec 6.8.

### Recommended Settings for 6.8

```
PHP Version: 8.0+ (8.1 or 8.2 recommended)
Memory Limit: 256M or higher
Max Execution Time: 120 seconds
cURL Timeout: 30 seconds
```

---

## 🔐 SECURITY CHECKLIST

After installation, verify:

- [ ] Log directory is writable but not web-accessible
- [ ] Config files not in document root
- [ ] Credentials encrypted in database
- [ ] Debug mode disabled in production
- [ ] HTTPS enabled for ClientExec
- [ ] Regular log rotation configured
- [ ] File permissions secure (644 for files, 755 for dirs)
- [ ] Web server user owns files

---

## 📁 FILE STRUCTURE REFERENCE

```
clientexec/
└── plugins/
    └── registrars/
        └── grepp/                              ← Module root
            ├── PluginGrepp.php                 ← Main plugin (REQUIRED)
            ├── lib/                            ← Libraries
            │   ├── GrEppClient.php            ← EPP client (REQUIRED)
            │   └── certificates/              ← SSL certs (REQUIRED)
            │       ├── regepp_chain.pem
            │       └── regepp-and-uat-regepp_ics_forth_gr_chain.pem
            ├── logs/                          ← Log files (chmod 777)
            │   └── grepp_YYYY-MM-DD.log
            ├── cron/                          ← Cron scripts
            │   └── sync-domains.php
            ├── README.md                      ← Documentation
            ├── INSTALL.md                     ← Installation guide
            ├── CHANGELOG.md                   ← Version history
            ├── config.example.php             ← Config template
            ├── connectivity-check.php         ← Testing tool
            └── templates/                     ← (Future: UI templates)
```

**Required Files:**
- ✅ `PluginGrepp.php` - Without this, plugin won't load
- ✅ `lib/GrEppClient.php` - EPP client library
- ✅ `lib/certificates/*.pem` - SSL certificates

**Optional but Recommended:**
- ⚠️ `logs/` - For logging (create if missing)
- ⚠️ `cron/sync-domains.php` - For automated sync

---

## ✅ QUICK START FOR CLIENTEXEC 6.8

**5-Minute Installation:**

```bash
# 1. Upload files
cd /var/www/html/clientexec
mkdir -p plugins/registrars/grepp
cp -r /path/to/clientexec-gr/* plugins/registrars/grepp/

# 2. Set permissions
chmod -R 755 plugins/registrars/grepp
chmod 777 plugins/registrars/grepp/logs
chown -R www-data:www-data plugins/registrars/grepp

# 3. Test
php plugins/registrars/grepp/connectivity-check.php --sandbox

# 4. Configure
# Open ClientExec admin → Settings → Plugins → Registrars
# Enable "grEPP for .GR TLD"
# Fill in credentials
# Save

# 5. Test domain search
# Go to client area → Register domain → Search for test.gr
```

---

## 🎯 ADVANCED FEATURES

### Recall Application (.GR Specific Feature)

The module includes the **Recall Application** feature, which allows you to cancel a domain registration within **5 days** of registration. This is a .GR registry specific feature.

**⚠️ IMPORTANT:**
- Only available within **5 days** of domain registration
- Cannot be used after 5 days
- Permanently cancels the domain registration
- No refund from registry (billing handled separately)

#### Using Recall Application

**Method 1: Via ClientExec Admin Interface**

1. Navigate to: **Domains** → **Manage Domains**
2. Select the domain you want to recall
3. Click **Actions** → **Recall Application**
4. Confirm the action

**Method 2: Via PHP Code/API**

```php
// Example: Call recallApplication method
$params = [
    'sld' => 'example',
    'tld' => 'gr'
];

$result = $pluginGrepp->recallApplication($params);

if ($result['success']) {
    echo "Domain application recalled successfully!";
} else {
    echo "Error: " . $result['error'];
}
```

**Method 3: Direct EPP Call**

```bash
# The module automatically retrieves the protocol ID from domain-info
# Then sends recall request to registry
```

#### When to Use Recall Application

✅ **Good Use Cases:**
- Customer cancelled order immediately
- Wrong domain registered by mistake
- Duplicate registration error
- Client failed payment

❌ **Cannot Use When:**
- More than 5 days have passed
- Domain already active with data
- Domain transferred to another registrar
- Domain has DNS records in use

#### How It Works

The RecallApplication function:

1. **Retrieves domain info** from registry
2. **Extracts protocol ID** (unique .GR identifier)
3. **Sends recall request** via EPP extension
4. **Confirms cancellation** with registry

**Technical Details:**
```
EPP Command: domain-delete with extension
Extension: gr-domain-ext-1.0
Operation: recallApplication
Auth: Protocol ID (not password)
```

#### Error Messages

| Error | Meaning | Solution |
|-------|---------|----------|
| "Protocol ID not found" | Domain too old (>5 days) | Cannot recall, domain is permanent |
| "Authorization failed" | Not the domain owner | Verify domain ownership |
| "Domain not found" | Domain doesn't exist | Check domain name |
| "Command failed" | Registry error | Check logs, contact support |

#### Example Workflow

```bash
# Day 1: Register domain
✅ Domain registered: example.gr

# Day 2: Customer cancels
✅ Recall application: SUCCESS

# Day 8: Customer cancels
❌ Recall application: FAILED (too late)
```

#### Testing Recall Application

**Test in Sandbox First:**

```bash
# 1. Enable sandbox mode in module settings
# 2. Register a test domain
# 3. Immediately recall it
# 4. Verify domain is cancelled

# Check logs:
tail -f plugins/registrars/grepp/logs/grepp_*.log
```

**Expected Log Output:**
```
[2025-10-30 10:00:01] [INFO] Recall application initiated for example.gr
[2025-10-30 10:00:02] [INFO] Retrieved protocol ID: GR20251030-12345
[2025-10-30 10:00:03] [INFO] Sending recall request to registry
[2025-10-30 10:00:04] [INFO] Recall successful - Code 1000
```

---

## 📞 SUPPORT

### Installation Help

- **Documentation:** See INSTALL.md, README.md
- **Email:** contact@itcms.gr
- **ClientExec Forums:** https://forum.clientexec.com/

### Registry Support

- **ICS FORTH:** registry@ics.forth.gr
- **Documentation:** https://grweb.ics.forth.gr/

---

## ✅ INSTALLATION COMPLETE!

If all tests pass, your installation is complete and ready for:

✅ Domain registration (.gr, .ελ)
✅ Domain renewal
✅ Domain transfer
✅ Domain recall application (5-day window)
✅ EPP code retrieval (DACOR tokens)
✅ Nameserver management (glue records)
✅ Contact updates (all 4 types: Registrant, Admin, Tech, Billing)
✅ Automatic sync (Registration date, Expiration date, Update date)
✅ Domain deletion

**Next Step:** Start registering domains!

---

**Document Version:** 1.1
**Last Updated:** 2025-10-30
**Compatible With:** ClientExec 6.8+
**Module Version:** 1.1.0
**License:** MPL-2.0

**Recent Updates:**
- Added RecallApplication feature documentation
- Enhanced cron setup instructions
- Updated feature list with all date synchronization details
