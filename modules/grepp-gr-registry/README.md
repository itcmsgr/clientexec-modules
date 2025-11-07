# ClientExec .GR Registrar Module

**Version:** 1.1.0
**Author:** Antonios Voulvoulis <contact@itcms.gr>
**Homepage:** https://itcms.gr/
**License:** MPL-2.0

## Overview

This is a complete ClientExec registrar module for managing Greek (.gr and .ελ) domain names through the ICS FORTH EPP registry interface. Built from the ground up specifically for ClientExec, providing native integration with the platform.

## Features

### Core Domain Operations
- ✅ **Domain Registration** - Register new .gr/.ελ domains
- ✅ **Domain Renewal** - Renew existing domains
- ✅ **Domain Transfer** - Transfer domains from other registrars
- ✅ **Domain Availability Check** - Check if domains are available
- ✅ **Domain Information Retrieval** - Get complete domain details

### Contact Management
- ✅ **Show Contacts** - Display Registrant, Admin, Tech, and Billing contacts
- ✅ **Modify Owner Details** - Update WHOIS contact information
- ✅ **Contact Creation** - Automatic contact creation during registration
- ✅ **Contact Updates** - Full support for contact modifications

### Domain Dates & Synchronization
- ✅ **Registration Date** - Track when domain was registered
- ✅ **Expiration Date** - Monitor domain expiration
- ✅ **Updated Date** - Last modification timestamp
- ✅ **Auto-Sync via Cron** - Automated synchronization of dates from registry
- ✅ **Next Due Date** - Automatic calculation for renewals

### Nameserver Management (Glue Records)
- ✅ **Register Nameserver** - Create glue records with IPv4/IPv6
- ✅ **Modify Nameserver** - Update nameserver IP addresses
- ✅ **Delete Nameserver** - Remove glue records
- ✅ **Query Nameserver Info** - Get nameserver details

### Transfer & Security
- ✅ **EPP Code Retrieval** - Get transfer auth codes via DACOR token system
- ✅ **DACOR Token Support** - EPP 4.3+ secure transfer tokens
- ✅ **Domain Deletion** - Request domain deletion from registry

### Additional Features
- ✅ **EPP 4.3 Protocol Support** - Latest registry protocol with all extensions
- ✅ **Sandbox/UAT Environment** - Testing environment support
- ✅ **Connectivity Diagnostic Tool** - Verify API connectivity
- ✅ **Detailed Logging** - Comprehensive audit trail
- ✅ **IDN Support** - Internationalized domain names (.ελ)

## Requirements

- PHP 8.0 or higher
- ClientExec 6.0+ (recommended)
- cURL extension with OpenSSL support
- Valid .GR registrar credentials from ICS FORTH

## Documentation

📚 **Complete guides available in the `docs/` directory:**

- **[INSTALLATION.md](docs/INSTALLATION.md)** - Complete installation guide for ClientExec 6.8+
- **[QUICKSTART.md](docs/QUICKSTART.md)** - Quick 5-minute setup guide
- **[QUICK_REFERENCE.md](docs/QUICK_REFERENCE.md)** - Cheat sheet for common operations
- **[CONFIGURATION.md](docs/CONFIGURATION.md)** - All configuration parameters explained
- **[RELEASE_NOTES.md](docs/RELEASE_NOTES.md)** - What's included in this version

Also available in root:
- **[CHANGELOG.md](CHANGELOG.md)** - Version history and changes
- **[CONTRIBUTING.md](CONTRIBUTING.md)** - How to contribute

## Quick Installation

### Step 1: Copy Files

Copy the module to your ClientExec installation:

```bash
cp -r clientexec-gr /path/to/clientexec/plugins/registrars/grepp/
```

### Step 2: Set Permissions

```bash
chmod 755 /path/to/clientexec/plugins/registrars/grepp/
chmod 644 /path/to/clientexec/plugins/registrars/grepp/*.php
chmod 755 /path/to/clientexec/plugins/registrars/grepp/cron/
mkdir -p /path/to/clientexec/plugins/registrars/grepp/logs/
chmod 777 /path/to/clientexec/plugins/registrars/grepp/logs/
```

### Step 3: Configure in ClientExec

1. Log in to ClientExec admin panel
2. Navigate to **Settings → Plugins → Domain Registrars**
3. Find "grEPP for .GR TLD" and click **Configure**
4. Fill in the configuration:

| Setting | Description | Example |
|---------|-------------|---------|
| **Enabled** | Enable the plugin | Yes |
| **Registrar ID** | Your numeric registrar ID | 123 |
| **EPP Username** | Your EPP username | mycompany |
| **EPP Password** | Production EPP password | ••••••••••••|
| **UAT Password** | Sandbox password (optional) | ••••••••••••|
| **Use Sandbox** | Use test environment | No (for production) |
| **Default Contact Email** | Email for admin contacts | support@example.gr |
| **Default Contact Name** | Name for admin contacts | Support Team |
| **Supported TLDs** | TLDs to handle | gr,ελ |
| **Debug Mode** | Enable verbose logging | No |

5. Click **Save**

### Step 4: Configure TLD Pricing

1. Navigate to **Settings → Products/Services → Domain Names**
2. Add/edit the `.gr` TLD
3. Set pricing for registration and renewal
4. Select "grEPP for .GR TLD" as the registrar
5. Save

## Configuration

### Environment Variables (Optional)

You can use environment variables instead of storing credentials in the database:

```bash
export GR_REGISTRAR_ID="123"
export GR_EPP_USERNAME="mycompany"
export GR_EPP_PASSWORD="your_password"
```

### SSL Certificates

The module includes the ICS FORTH SSL certificate chain. If you need to update it:

```bash
cp new_certificate.pem /path/to/grepp/lib/certificates/regepp_chain.pem
```

## Testing Connectivity

Before going live, test your connectivity:

```bash
cd /path/to/clientexec/plugins/registrars/grepp/
php connectivity-check.php --sandbox
```

For production:

```bash
php connectivity-check.php --production
```

### Expected Output

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

## Automated Synchronization

### Setting Up the Cron Job

The module includes a cron script that synchronizes domain dates from the registry. This ensures expiration dates are always accurate.

#### Install Cron Job

Add to your crontab:

```bash
crontab -e
```

Add this line to run daily at 2:00 AM:

```
0 2 * * * /usr/bin/php /path/to/clientexec/plugins/registrars/grepp/cron/sync-domains.php >> /var/log/grepp-sync.log 2>&1
```

#### What the Cron Job Does

- Queries all .gr/.ελ domains from ClientExec database
- Retrieves current information from the registry
- Updates expiration dates, registration dates, and status
- Handles expired and transferred domains
- Logs all changes for audit purposes

#### Manual Sync

You can run the sync manually:

```bash
php /path/to/grepp/cron/sync-domains.php
```

#### Sync Logs

Logs are stored in `/path/to/grepp/logs/sync_YYYY-MM-DD.log`

Example log output:

```
[2025-10-28 02:00:01] [INFO] grEPP Domain Sync Started
[2025-10-28 02:00:01] [INFO] Configuration loaded - Environment: PRODUCTION
[2025-10-28 02:00:02] [INFO] EPP Client initialized successfully
[2025-10-28 02:00:02] [INFO] Found 150 domains to sync
[2025-10-28 02:00:03] [INFO] Processing: example.gr
[2025-10-28 02:00:03] [INFO]   ✓ Updated: Expiry: 2025-12-15 → 2026-12-15
[2025-10-28 02:00:04] [INFO] Processing: test.gr
[2025-10-28 02:00:04] [DEBUG]   - No changes needed
...
[2025-10-28 02:05:30] [INFO] Sync Complete
[2025-10-28 02:05:30] [INFO] Total domains: 150
[2025-10-28 02:05:30] [INFO] Updated: 23
[2025-10-28 02:05:30] [INFO] Expired: 2
[2025-10-28 02:05:30] [INFO] Transferred away: 0
[2025-10-28 02:05:30] [INFO] Errors: 0
[2025-10-28 02:05:30] [INFO] Skipped (no changes): 125
[2025-10-28 02:05:30] [INFO] Duration: 328.45 seconds
```

## Usage Examples

### Registering a Domain

The module handles domain registration through ClientExec's standard domain ordering process:

1. Customer selects domain and completes order
2. Module automatically:
   - Creates registrant contact from customer data
   - Creates admin/tech/billing contacts
   - Registers domain with nameservers
   - Stores domain information in ClientExec

### Renewing a Domain

Renewals are handled automatically:

1. Invoice is created for renewal
2. When invoice is paid, module:
   - Queries current expiration date
   - Submits renewal request to registry
   - Updates expiration date in ClientExec

### Transferring a Domain

Domain transfers require EPP/Auth code:

1. Customer provides EPP code during order
2. Module:
   - Validates EPP code with registry
   - Initiates transfer request
   - Creates new registrant contact
   - Completes transfer process

### Updating Contact Information

Update WHOIS contact details:

1. Navigate to domain details in ClientExec
2. Click "Update Contact Information"
3. Modify Registrant details
4. Module updates contact in registry

## Troubleshooting

### Connection Errors

**Problem:** Connection timeout or SSL errors

**Solution:**
```bash
# Test connectivity
php connectivity-check.php --sandbox

# Check certificate
openssl verify lib/certificates/regepp_chain.pem

# Test firewall
curl -v https://uat-regepp.ics.forth.gr:700/epp/proxy
```

### Authentication Errors

**Problem:** Login fails with code 2200

**Solution:**
- Verify credentials are correct
- Check Registrar ID is numeric
- Ensure using correct environment (production vs sandbox)
- Verify UAT password if using sandbox

### Domain Not Found (Code 2303)

**Problem:** Domain info returns code 2303

**Possible Causes:**
- Domain has expired
- Domain was deleted
- Domain doesn't exist
- Typo in domain name

### Authorization Error (Code 2201)

**Problem:** Cannot access domain information

**Possible Causes:**
- Domain transferred to another registrar
- Domain not under your management
- Incorrect EPP/Auth code for transfer

### Contact Creation Fails

**Problem:** Contact validation errors

**Solution:**
- Ensure all required fields are provided
- Phone number must be in E.164 format (+30.XXXXXXXXXX)
- Email must be valid
- Country code must be ISO 3166-1 alpha-2 (e.g., 'gr')

## Logging

### Log Locations

- **Main Module Logs:** `/path/to/grepp/logs/grepp_YYYY-MM-DD.log`
- **Sync Logs:** `/path/to/grepp/logs/sync_YYYY-MM-DD.log`
- **Connectivity Check:** `/path/to/grepp/logs/connectivity-check.log`

### Log Levels

- **DEBUG** - Detailed XML requests/responses
- **INFO** - Normal operations
- **WARN** - Warnings (expired domains, etc.)
- **ERROR** - Errors requiring attention

### Enabling Debug Mode

Enable in ClientExec plugin configuration:
- Set "Debug Mode" to **Yes**
- Logs will include full XML requests/responses
- **Warning:** May expose sensitive data, use only for troubleshooting

## Security Considerations

### Credential Storage

- EPP password is stored encrypted in ClientExec database
- Use environment variables for additional security
- Restrict file permissions on configuration files

### SSL/TLS

- Module uses certificate pinning for registry communication
- All communication is over HTTPS
- Certificates are validated

### Logging

- Passwords and auth codes are masked in logs
- Disable debug mode in production
- Regularly rotate log files
- Restrict access to log directory (chmod 700)

## API Reference

### EPP Commands Supported

| Command | Purpose | Implementation |
|---------|---------|----------------|
| `login` | Authenticate session | ✅ Automatic |
| `logout` | End session | ✅ Automatic |
| **Domain Commands** | | |
| `domain-check` | Check availability | ✅ |
| `domain-info` | Get domain details | ✅ |
| `domain-create` | Register domain | ✅ |
| `domain-renew` | Renew domain | ✅ |
| `domain-transfer` | Transfer domain | ✅ |
| `domain-update` | Update domain (NS, contacts) | ✅ |
| `domain-delete` | Delete domain | ✅ |
| `dacor-issue-token` | Get transfer auth code (EPP 4.3+) | ✅ |
| **Contact Commands** | | |
| `contact-check` | Check contact availability | ✅ |
| `contact-info` | Get contact details | ✅ |
| `contact-create` | Create contact | ✅ |
| `contact-update` | Update contact | ✅ |
| **Host/Nameserver Commands** | | |
| `host-check` | Check nameserver availability | ✅ |
| `host-create` | Create nameserver (glue record) | ✅ |
| `host-info` | Get nameserver details | ✅ |
| `host-update` | Update nameserver IPs | ✅ |
| `host-delete` | Delete nameserver | ✅ |

## Greek Character Handling

### IDN Support

The module handles both ASCII (.gr) and IDN (.ελ) domains:

```php
// Automatic sanitization
example.gr    → example.gr
παράδειγμα.gr → παραδειγμα.gr (accents removed)
παράδειγμα.ελ → παραδειγμα.ελ
```

### Accent Removal

Greek accents are automatically removed per registry requirements:

| Original | Sanitized |
|----------|-----------|
| ά | α |
| έ | ε |
| ή | η |
| ί, ϊ, ΐ | ι |
| ό | ο |
| ύ, ϋ, ΰ | υ |
| ώ | ω |

### Final Sigma Handling

Final sigma (ς) is automatically converted:
- `σ` at end of word → `ς`
- Before hyphen: `σ-` → `ς-`
- Before dot: `σ.` → `ς.`

## Support

### Getting Help

- **Documentation:** This README
- **Issues:** Report at https://github.com/yourusername/clientexec-gr (adjust URL)
- **Email:** contact@itcms.gr

### Registry Documentation

- ICS FORTH EPP Documentation: https://grweb.ics.forth.gr/
- Greek Registry (.gr): https://grweb.ics.forth.gr/

### Changelog

#### Version 1.1.0 (2025-10-28)
- **NEW:** DACOR token support for EPP code retrieval (EPP 4.3+)
- **NEW:** Nameserver glue record management (register/modify/delete)
- **NEW:** Domain deletion functionality
- **NEW:** Host EPP commands (host-update, host-delete)
- **IMPROVED:** Full EPP extension data parsing
- **FIXED:** Host info now properly parses IP addresses

#### Version 1.0.0 (2025-10-25)
- Initial release for ClientExec
- Full domain lifecycle support
- Contact management with WHOIS updates
- Automated synchronization
- Connectivity diagnostic tool
- Comprehensive documentation
- EPP 4.3 protocol support
- IDN (.ελ) domain support

## License

This project is distributed under the **ITCMS.GR Free License — All Rights Reserved**.

- **SPDX Identifier:** `LicenseRef-ITCMS-Free-1.0`
- **Copyright:** © 2025 Antonios Voulvoulis, ITCMS.GR
- ✅ **Free to use** (including commercial environments)
- 🚫 **Do not** copy, modify, redistribute, or resell
- 🏷️ **All rights** remain with the author
- ⚙️ A "Pro Edition" with extended features may be released later

See the [LICENSE.txt](./LICENSE.txt) file for full details.

### File Headers

All source files include the ITCMS Free License header:
```php
// SPDX-License-Identifier: LicenseRef-ITCMS-Free-1.0
// ITCMS.GR Free License – All Rights Reserved
// Copyright (c) 2025 Antonios Voulvoulis
// Free to use (including commercial use), but redistribution,
// resale, modification, or cloning is strictly prohibited.
```

## Credits

- **Developer:** Antonios Voulvoulis / ITCMS
- **Registry:** ICS FORTH (.GR Registry)
- **EPP Protocol:** Based on ICS FORTH EPP specifications

## Disclaimer

This module is provided "as is" without warranty of any kind. Use at your own risk. Always test in sandbox/UAT environment before production use.
