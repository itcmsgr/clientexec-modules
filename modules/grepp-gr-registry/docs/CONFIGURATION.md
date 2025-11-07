# ClientExec .GR Module - Configuration Parameters

**Version:** 1.1.0
**Date:** 2025-10-30
**Module:** ClientExec .GR Registrar (grEPP)

---

## 📋 REQUIRED PARAMETERS

### 1. GREPP REGISTRY CREDENTIALS (EPP API)

These credentials are provided by **ICS FORTH** (.GR Registry):

| Parameter | Type | Required | Description | Example |
|-----------|------|----------|-------------|---------|
| **Registrar ID** | Integer | ✅ Yes | Your numeric registrar ID | `123` |
| **EPP Username** | String | ✅ Yes | EPP API username | `mycompany` |
| **EPP Password (Production)** | String | ✅ Yes | Production EPP password | `••••••••••••` |
| **EPP Password (UAT/Sandbox)** | String | ⚠️ Optional | Sandbox password (if different) | `••••••••••••` |

**How to get these:**
1. Register as .GR registrar with ICS FORTH
2. Complete accreditation process
3. Receive credentials via email from registry
4. Production credentials ≠ Sandbox credentials (usually)

---

### 2. DATABASE CREDENTIALS (ClientExec)

**NOT NEEDED** - ClientExec handles all database operations automatically.

The module uses ClientExec's native database layer:
- ✅ No separate database configuration needed
- ✅ No database username/password required
- ✅ Uses existing ClientExec database connection
- ✅ All data stored in ClientExec tables

**What this means:**
- Module inherits ClientExec's database settings
- No additional database setup required
- Data stored alongside other registrar data

---

### 3. ENVIRONMENT SETTINGS

| Parameter | Type | Required | Description | Options |
|-----------|------|----------|-------------|---------|
| **Use Sandbox** | Boolean | ✅ Yes | Which environment to use | `true` = Sandbox/UAT<br>`false` = Production |
| **Debug Mode** | Boolean | ⚠️ Optional | Enable detailed logging | `true` = Full XML logs<br>`false` = Normal logs |

**Recommendation:**
- Start with **Sandbox = true** for testing
- Switch to **Sandbox = false** for production
- Keep **Debug = false** in production (security)

---

### 4. DEFAULT CONTACT SETTINGS

These are used for admin/tech/billing contacts during registration:

| Parameter | Type | Required | Description | Example |
|-----------|------|----------|-------------|---------|
| **Default Contact Email** | Email | ⚠️ Optional | Email for admin contacts | `support@example.gr` |
| **Default Contact Name** | String | ⚠️ Optional | Name for admin contacts | `Support Team` |

**How it works:**
- If provided: Uses these default contacts for admin/tech/billing
- If empty: Uses registrant details for all contact types

---

### 5. SUPPORTED TLDs

| Parameter | Type | Required | Description | Default |
|-----------|------|----------|-------------|---------|
| **Supported TLDs** | String (CSV) | ⚠️ Optional | TLDs to handle | `gr,ελ` |

**Format:** Comma-separated list
**Examples:**
- `gr` - Only ASCII .gr domains
- `gr,ελ` - Both .gr and .ελ (Greek IDN)
- `ελ` - Only Greek script domains

---

### 6. SSL CERTIFICATES

| File | Location | Required | Purpose |
|------|----------|----------|---------|
| **Registry Certificate** | `lib/certificates/regepp_chain.pem` | ✅ Yes | SSL verification for production |
| **Full Chain** | `lib/certificates/regepp-and-uat-regepp_ics_forth_gr_chain.pem` | ✅ Yes | SSL verification for both environments |

**Certificate Details:**
- ✅ Already included in module
- ✅ ICS FORTH official certificates
- ✅ Valid for both production and sandbox
- ✅ No configuration needed
- ✅ Auto-selected based on environment

**Certificate Verification:**
```bash
openssl verify lib/certificates/regepp_chain.pem
```

**Update Certificates:**
```bash
# If registry provides new certificates
cp new_certificate.pem lib/certificates/regepp_chain.pem
chmod 644 lib/certificates/regepp_chain.pem
```

---

## 🔧 COMPLETE CONFIGURATION CHECKLIST

### Minimum Required (Production):
- [ ] **Registrar ID** - From ICS FORTH
- [ ] **EPP Username** - From ICS FORTH
- [ ] **EPP Password** - Production password
- [ ] **Use Sandbox** - Set to `false`

### Recommended for Testing:
- [ ] **EPP Password (UAT)** - Sandbox password
- [ ] **Use Sandbox** - Set to `true` initially
- [ ] **Debug Mode** - Set to `true` for testing

### Optional Enhancements:
- [ ] **Default Contact Email** - For automated contact creation
- [ ] **Default Contact Name** - For automated contact creation
- [ ] **Supported TLDs** - Customize if needed (default: `gr,ελ`)

---

## 📝 CONFIGURATION METHODS

### Method 1: ClientExec Admin Panel (Recommended)

1. Login to ClientExec admin
2. Navigate to: **Settings → Plugins → Domain Registrars**
3. Find: **"grEPP for .GR TLD"**
4. Click: **Configure**
5. Fill in the form:

```
Plugin Name:           grEPP for .GR TLD (auto-filled)
Enabled:              Yes
Registrar ID:         123
EPP Username:         mycompany
EPP Password:         ••••••••••••
UAT Password:         •••••••••••• (optional)
Use Sandbox:          No (Yes for testing)
Default Contact Email: support@example.gr (optional)
Default Contact Name:  Support Team (optional)
Supported TLDs:       gr,ελ
Debug Mode:           No (Yes for troubleshooting)
```

6. Click: **Save**

---

### Method 2: Environment Variables (Advanced)

For security, you can use environment variables:

```bash
# Add to .env or server environment
export GR_REGISTRAR_ID="123"
export GR_EPP_USERNAME="mycompany"
export GR_EPP_PASSWORD="production_password"
export GR_EPP_PASSWORD_UAT="sandbox_password"
```

**Note:** Module prioritizes environment variables over database settings

---

### Method 3: Configuration File (Standalone Scripts)

For cron jobs and standalone scripts:

1. Copy config template:
```bash
cp config.example.php config.php
```

2. Edit `config.php`:
```php
return [
    'registrar_id' => '123',
    'epp_username' => 'mycompany',
    'epp_password' => 'production_password',
    'epp_password_uat' => 'sandbox_password',
    'use_sandbox' => false,
    'default_contact_email' => 'support@example.gr',
    'default_contact_name' => 'Support Team',
    'supported_tlds' => ['gr', 'ελ'],
    'debug_mode' => false,
];
```

3. Secure the file:
```bash
chmod 600 config.php
chown www-data:www-data config.php
```

---

## 🔐 SECURITY BEST PRACTICES

### Credential Storage

1. **Production Passwords:**
   - ✅ Use ClientExec's encrypted storage
   - ✅ Never commit to version control
   - ✅ Restrict file permissions (600)
   - ✅ Consider environment variables

2. **Configuration Files:**
   ```bash
   # Secure config.php
   chmod 600 config.php

   # Add to .gitignore
   echo "config.php" >> .gitignore
   echo "logs/*.log" >> .gitignore
   ```

3. **Debug Mode:**
   - ⚠️ NEVER enable in production
   - ⚠️ Logs contain XML requests/responses
   - ⚠️ May expose passwords/tokens
   - ✅ Only use in sandbox for troubleshooting

### Certificate Security

```bash
# Verify certificate permissions
chmod 644 lib/certificates/*.pem

# Verify certificate integrity
openssl x509 -in lib/certificates/regepp_chain.pem -text -noout
```

---

## 🧪 TESTING CONFIGURATION

### 1. Test Connectivity

```bash
cd /path/to/clientexec/plugins/registrars/grepp/
php connectivity-check.php --sandbox
```

**Expected Output:**
```
✓ PASS - Certificate valid
✓ PASS - Network connection (150ms)
✓ PASS - EPP client initialized
✓ PASS - EPP login successful (320ms)
✓ PASS - Domain check command works (280ms)
```

### 2. Test in ClientExec

1. Navigate to domain search
2. Search for a test domain: `test12345.gr`
3. Verify availability check works
4. Try registering a test domain
5. Check logs: `logs/grepp_YYYY-MM-DD.log`

### 3. Verify Parameters

Check what ClientExec has stored:

```sql
-- Connect to ClientExec database
SELECT * FROM ce_plugins WHERE name = 'grEPP for .GR TLD';
```

---

## 📊 PARAMETER SUMMARY TABLE

| Category | Parameter | Required | Source | Stored In |
|----------|-----------|----------|--------|-----------|
| **Registry** | Registrar ID | ✅ | ICS FORTH | ClientExec DB |
| | EPP Username | ✅ | ICS FORTH | ClientExec DB |
| | EPP Password (Prod) | ✅ | ICS FORTH | ClientExec DB (encrypted) |
| | EPP Password (UAT) | ⚠️ | ICS FORTH | ClientExec DB (encrypted) |
| **Database** | DB Username | ❌ NOT NEEDED | N/A | N/A |
| | DB Password | ❌ NOT NEEDED | N/A | N/A |
| **Environment** | Use Sandbox | ✅ | Admin choice | ClientExec DB |
| | Debug Mode | ⚠️ | Admin choice | ClientExec DB |
| **Contacts** | Default Email | ⚠️ | Admin sets | ClientExec DB |
| | Default Name | ⚠️ | Admin sets | ClientExec DB |
| **TLDs** | Supported TLDs | ⚠️ | Admin sets | ClientExec DB |
| **Certificates** | Registry Certs | ✅ | Module includes | Filesystem |

---

## 🎯 QUICK START VALUES

### For Testing (Sandbox):
```
Registrar ID:         <your_test_id>
EPP Username:         <your_test_username>
EPP Password:         <leave_empty>
UAT Password:         <your_test_password>
Use Sandbox:          Yes
Default Contact Email: test@example.gr
Default Contact Name:  Test User
Supported TLDs:       gr,ελ
Debug Mode:           Yes
```

### For Production:
```
Registrar ID:         <your_registrar_id>
EPP Username:         <your_username>
EPP Password:         <your_production_password>
UAT Password:         <leave_empty>
Use Sandbox:          No
Default Contact Email: support@yourdomain.gr
Default Contact Name:  Support Team
Supported TLDs:       gr,ελ
Debug Mode:           No
```

---

## ❓ FREQUENTLY ASKED QUESTIONS

**Q: Do I need database credentials?**
A: **NO** - Module uses ClientExec's existing database connection

**Q: Where do I get EPP credentials?**
A: From **ICS FORTH** after completing .GR registrar accreditation

**Q: Are production and sandbox passwords different?**
A: **Usually YES** - Registry provides separate credentials

**Q: What if I don't have UAT password?**
A: Leave empty - module will use production password for both

**Q: Do I need to update certificates?**
A: **NO** - Certificates are included and auto-selected

**Q: Can I use environment variables?**
A: **YES** - Module supports both database and environment variables

**Q: Is config.php required?**
A: **NO** - Only for standalone scripts (cron). ClientExec uses database storage

---

## 📞 GETTING CREDENTIALS

### ICS FORTH (.GR Registry)

**Website:** https://grweb.ics.forth.gr/

**Process:**
1. Apply for .GR registrar accreditation
2. Complete legal/technical requirements
3. Pass testing phase
4. Receive production credentials
5. Sign agreements
6. Go live

**Contact:**
- Registry: ICS-FORTH
- Email: registry@ics.forth.gr
- Documentation: https://grweb.ics.forth.gr/

---

## ✅ VALIDATION CHECKLIST

Before going live, verify:

- [ ] All parameters configured in ClientExec admin
- [ ] Connectivity test passed in sandbox
- [ ] Test domain registered successfully
- [ ] EPP code retrieval works (DACOR tokens)
- [ ] Nameserver updates work
- [ ] Contact updates work
- [ ] Sync cron job configured
- [ ] Debug mode disabled for production
- [ ] Certificates present and valid
- [ ] Logs directory writable (chmod 777)
- [ ] No forbidden terms in any files
- [ ] Production credentials tested
- [ ] Switched to production environment

---

**Document Version:** 1.0
**Last Updated:** 2025-10-28
**Author:** Antonios Voulvoulis / ITCMS
**License:** MPL-2.0
