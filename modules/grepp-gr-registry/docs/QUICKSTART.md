# Quick Start Guide - ClientExec .GR Registrar

## 5-Minute Setup

### 1. Upload & Setup (2 minutes)

```bash
# Upload files to ClientExec
cd /path/to/clientexec/plugins/registrars/
mkdir -p grepp
# Upload files to grepp/

# Set permissions
chmod -R 755 grepp/
chmod 777 grepp/logs/
```

### 2. Test Connection (1 minute)

```bash
cd grepp/

# Set credentials
export GR_REGISTRAR_ID="123"
export GR_EPP_USERNAME="your_username"
export GR_EPP_PASSWORD="your_password"

# Test
php connectivity-check.php --sandbox
```

### 3. Configure ClientExec (2 minutes)

1. **Admin Panel** → **Settings** → **Plugins** → **Domain Registrars**
2. Find **grEPP for .GR TLD** → Click **Configure**
3. Fill in:
   - Registrar ID: `123`
   - EPP Username: `your_username`
   - EPP Password: `your_password`
   - Use Sandbox: `Yes` (for testing)
4. **Save**

### 4. Add TLD

1. **Settings** → **Products/Services** → **Domain Names**
2. **Add New TLD**: `.gr`
3. Select Registrar: **grEPP for .GR TLD**
4. Set pricing
5. **Save**

### 5. Test Registration

1. Search for domain: `test-` + timestamp + `.gr`
2. Complete test order
3. Verify in sandbox

### 6. Go Live

1. Change **Use Sandbox** to `No`
2. Update password to production
3. Test connectivity: `php connectivity-check.php --production`
4. Done!

## Common Commands

```bash
# Test connectivity (sandbox)
php connectivity-check.php --sandbox

# Test connectivity (production)
php connectivity-check.php --production

# Run domain sync manually
php cron/sync-domains.php

# View logs
tail -f logs/grepp_*.log
tail -f logs/sync_*.log

# Set up daily sync cron
crontab -e
# Add: 0 2 * * * /usr/bin/php /path/to/grepp/cron/sync-domains.php
```

## Troubleshooting Quick Fixes

**Connection Failed?**
```bash
# Check firewall
curl -v https://uat-regepp.ics.forth.gr:700/epp/proxy

# Check certificate
ls -la lib/certificates/regepp_chain.pem
```

**Permission Denied?**
```bash
chmod 777 logs/
```

**Module Not Showing?**
```bash
# Check main file
ls -la PluginGrepp.php
chmod 644 PluginGrepp.php
```

## Support

- **Docs:** README.md
- **Installation:** INSTALL.md
- **Email:** contact@itcms.gr

## Features Checklist

- ✅ Domain registration (.gr, .ελ)
- ✅ Domain renewal
- ✅ Domain transfer
- ✅ Availability check
- ✅ Contact management (Registrant, Admin, Tech, Billing)
- ✅ WHOIS updates
- ✅ Nameserver management
- ✅ Automated date sync via cron
- ✅ EPP 4.3 protocol
- ✅ Sandbox/Production modes
- ✅ Connectivity diagnostics
- ✅ Detailed logging

---

**Ready in 5 minutes. Enterprise-grade domain management for Greek TLDs.**
