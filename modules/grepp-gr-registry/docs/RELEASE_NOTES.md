# ClientExec .GR Registrar Module - Release Notes

**Version:** 1.1.0
**Release Date:** 2025-10-30
**Status:** Production Ready ✅

---

## 🎉 WHAT'S IN THIS RELEASE

A complete, professional ClientExec registrar module for managing .GR and .ελ (Greek) domain names through the ICS FORTH EPP registry.

### Core Features
- ✅ **Domain Registration** - Register .gr and .ελ domains
- ✅ **Domain Renewal** - Renew existing domains
- ✅ **Domain Transfer** - Transfer domains from other registrars
- ✅ **Domain Recall** - Cancel registration within 5 days (.GR specific)
- ✅ **Domain Deletion** - Request domain deletion from registry
- ✅ **EPP Code Retrieval** - Get transfer auth codes (DACOR tokens)

### Contact Management
- ✅ **4 Contact Types** - Registrant, Admin, Tech, Billing
- ✅ **Full Contact Info** - All 10 fields supported
- ✅ **Contact Updates** - Modify WHOIS information
- ✅ **Auto Contact Creation** - Automatic during registration

### Nameserver Management
- ✅ **Get/Set Nameservers** - Up to 5 nameservers
- ✅ **Glue Records** - Register nameservers with IPs
- ✅ **IPv4 + IPv6** - Full dual-stack support
- ✅ **Modify/Delete** - Update or remove nameservers

### Date Synchronization
- ✅ **Registration Date** - Track when domain was registered
- ✅ **Expiration Date** - Monitor domain expiration
- ✅ **Update Date** - Last modification timestamp
- ✅ **Auto-Sync** - Automated cron synchronization

### Technical Excellence
- ✅ **EPP 4.3 Protocol** - Latest registry protocol
- ✅ **DACOR Tokens** - Modern secure transfer system
- ✅ **IDN Support** - Greek script (.ελ) domains
- ✅ **Sandbox Mode** - UAT environment for testing
- ✅ **Production Mode** - Live registry support

---

## 🚀 INSTALLATION

### Quick Start

**1. Upload Files:**
```bash
cd /var/www/html/clientexec
mkdir -p plugins/registrars/grepp
cp -r /path/to/clientexec-gr/* plugins/registrars/grepp/
chmod -R 755 plugins/registrars/grepp
chmod 777 plugins/registrars/grepp/logs
```

**2. Enable Module:**
- Login to ClientExec Admin
- Settings → Plugins → Registrars
- Find "grEPP for .GR TLD"
- Set Enabled = Yes
- Enter credentials
- Save

**3. Configure TLDs:**
- Settings → Products → Domain Pricing
- Add .gr → Set registrar to "grEPP for .GR TLD"
- Add .ελ → Set registrar to "grEPP for .GR TLD"

**4. Setup Cron:**
```bash
crontab -e
# Add: 0 2 * * * /usr/bin/php /path/to/plugins/registrars/grepp/cron/sync-domains.php
```

**Full installation guide:** See [INSTALLATION.md](INSTALLATION.md)

---

## 📋 REQUIREMENTS

### Server Requirements
- ClientExec 6.0+ (tested with 6.8)
- PHP 8.0 or higher
- cURL extension
- OpenSSL extension
- SimpleXML extension

### Registry Requirements
- ICS FORTH EPP credentials
- Registrar ID (numeric)
- EPP username
- EPP password
- Optional: UAT credentials for testing

---

## 📁 FILE STRUCTURE

```
plugins/registrars/grepp/
├── PluginGrepp.php                 ← Main plugin file
├── lib/
│   ├── GrEppClient.php            ← EPP client library
│   └── certificates/              ← SSL certificates
├── cron/
│   └── sync-domains.php           ← Daily sync script
├── logs/                           ← Auto-created logs
├── docs/
│   ├── INSTALLATION.md            ← Installation guide
│   ├── QUICK_REFERENCE.md         ← Quick reference
│   └── ...                         ← More documentation
├── README.md                       ← User documentation
├── CHANGELOG.md                    ← Version history
└── connectivity-check.php          ← Test tool
```

---

## 🎯 NEW IN VERSION 1.1.0

### Added Features
- **RecallApplication** - Cancel domain within 5 days of registration
- Protocol ID parsing from EPP responses
- ROID (Registry Object ID) support
- Enhanced .GR extension parsing
- Improved error handling

### Technical Improvements
- Better EPP 4.3 compliance
- Enhanced extension data parsing
- More comprehensive logging
- Updated documentation

---

## 🔧 CONFIGURATION

### Required Settings
| Setting | Description | Example |
|---------|-------------|---------|
| Registrar ID | Your numeric ID from ICS FORTH | 123 |
| EPP Username | EPP API username | mycompany |
| EPP Password | Production password | ••••••••• |
| UAT Password | Sandbox password (optional) | ••••••••• |
| Use Sandbox | Enable for testing | Yes/No |

### Optional Settings
| Setting | Description | Default |
|---------|-------------|---------|
| Default Contact Email | For admin/tech/billing | - |
| Default Contact Name | For admin/tech/billing | - |
| Supported TLDs | Comma-separated list | gr,ελ |
| Debug Mode | Enable detailed logging | No |

---

## 🔐 SECURITY

### Built-in Security
- SSL/TLS certificate verification
- Secure credential storage
- Password masking in logs
- Input sanitization
- Session-based authentication

### Best Practices
- Always use HTTPS for ClientExec
- Keep debug mode off in production
- Restrict log directory access
- Regular log rotation
- Use strong EPP passwords

---

## 📞 SUPPORT

### Documentation
- **Installation Guide:** [INSTALLATION.md](INSTALLATION.md)
- **Quick Reference:** [QUICK_REFERENCE.md](QUICK_REFERENCE.md)
- **Full Manual:** [README.md](../README.md)
- **Configuration:** [CONFIGURATION.md](CONFIGURATION.md)

### Contact
- **Email:** contact@itcms.gr
- **Homepage:** https://itcms.gr/
- **License:** MPL-2.0

### Registry Support
- **ICS FORTH:** registry@ics.forth.gr
- **Documentation:** https://grweb.ics.forth.gr/

---

## ✅ COMPATIBILITY

### ClientExec Versions
| Version | Compatible | Status |
|---------|------------|--------|
| 6.8.x | ✅ YES | Fully tested |
| 6.7.x | ✅ YES | Compatible |
| 6.6.x | ✅ YES | Compatible |
| 6.5.x | ✅ YES | Compatible |
| 6.0-6.4 | ⚠️ LIKELY | Should work |
| < 6.0 | ❌ NO | Not compatible |

### PHP Versions
- PHP 8.2 - ✅ Recommended
- PHP 8.1 - ✅ Fully supported
- PHP 8.0 - ✅ Minimum required
- PHP 7.x - ❌ Not supported

---

## 🐛 KNOWN ISSUES

None at this time.

---

## 📜 LICENSE

This module is licensed under the Mozilla Public License 2.0 (MPL-2.0).

- ✅ Free to use (including commercial use)
- ✅ Modify for your own use
- ❌ Redistribution restrictions apply
- ❌ No resale without permission

See LICENSE file for full terms.

---

## 🎉 READY TO USE

The module is:
- ✅ **100% Complete** - All features implemented
- ✅ **Production Tested** - Ready for live use
- ✅ **Fully Documented** - Comprehensive guides
- ✅ **Clean Code** - Professional quality
- ✅ **ClientExec Native** - Built specifically for ClientExec

**Start managing .GR domains today!**

---

**Version:** 1.1.0
**Release Date:** 2025-10-30
**Author:** Antonios Voulvoulis
**Copyright:** © 2025 ITCMS.GR
**License:** MPL-2.0
