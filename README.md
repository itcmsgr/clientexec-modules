# ClientExec Modules & Extensions

**Professional modules for ClientExec billing and automation platform**

[![License: MPL 2.0](https://img.shields.io/badge/License-MPL%202.0-brightgreen.svg)](https://opensource.org/licenses/MPL-2.0)
[![ClientExec](https://img.shields.io/badge/ClientExec-6.8.1+-blue.svg)](https://www.clientexec.com/)
[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4.svg)](https://www.php.net/)

---

## 📦 Available Modules

### 🌐 [grepp-gr-registry](modules/grepp-gr-registry/)
**Greek (.GR/.ΕΛ) Domain Registrar Module**

Complete domain lifecycle management for Greek domains through ICS FORTH EPP registry.

- Domain registration, renewal, transfer
- DACOR token support
- Nameserver & glue record management
- Contact management (WHOIS updates)
- Greek IDN support (.ελ domains)
- Full EPP protocol integration

**Version:** 1.1.0 | **Status:** Production Ready

---

### 🔔 [dns-change-alert](modules/dns-change-alert/)
**DNS Change Monitoring & Alert System (NIS2 Compliant)**

Monitor DNS changes and notify domain owners before and after modifications. Fully compliant with EU NIS2 Directive requirements.

- Pre-change notifications (60-minute warning)
- Post-change confirmations
- Unexpected change detection
- User opt-in model (privacy-first, disabled by default)
- 730-day audit trail (NIS2 requirement)
- Multi-channel alerts (Email/SMS/Webhooks)

**Version:** 1.0.0 | **Status:** Production Ready | **Compliance:** EU NIS2 Directive

---

## 🚀 Quick Start

```bash
# Clone repository
git clone https://github.com/yourusername/clientexec-modules.git
cd clientexec-modules/modules

# For Greek domain registrar
cd grepp-gr-registry
cat INSTALL.md

# For DNS monitoring
cd dns-change-alert
cat INSTALL.md
```

---

## 📋 Requirements

- **ClientExec:** 6.8.1 or later
- **PHP:** 8.0 or later
- **Database:** MySQL 5.7+ / MariaDB 10.3+
- **PHP Extensions:** curl, openssl, mbstring, xml, pdo_mysql

---

## 👨‍💻 Author

**Antonios Voulvoulis**
ITCMS.GR - IT Consulting & Management Services

- 🌐 Website: [https://itcms.gr](https://itcms.gr)
- 📧 Email: contact@itcms.gr

---

## 📜 License

**Mozilla Public License 2.0 (MPL 2.0)**

Free to use for commercial and personal purposes. You can modify and distribute these modules. Modified source files must remain under MPL 2.0.

See [LICENSE](LICENSE) file for details.

---

## 🎯 Designed For ClientExec

These modules are specifically designed for seamless integration with:

- ClientExec billing platform core
- Domain management lifecycle
- Customer portal features
- Admin panel configuration
- Automation and cron jobs
- API integration (REST/SOAP)

---

## 🛠️ Module Compatibility

| Module | ClientExec 6.8+ | ClientExec 7.0+ | ClientExec 8.0+ |
|--------|----------------|----------------|----------------|
| grepp-gr-registry | ✅ | ✅ | ✅ |
| dns-change-alert | ✅ | ✅ | ✅ |

---

## 📚 Documentation

Each module includes:

- **README.md** - Overview and features
- **INSTALL.md** - Installation guide
- **CONFIGURATION.md** - Configuration options
- **TROUBLESHOOTING.md** - Common issues

---

## 🔒 Security

### Reporting Vulnerabilities

Email: contact@itcms.gr with:
- Module name and version
- Reproduction steps
- Security impact

**Response time:** 48 hours

### Security Features

- Input validation and sanitization
- Prepared statements (SQL injection prevention)
- CSRF protection
- XSS prevention
- Secure API communication (SSL/TLS)

---

## 🌍 NIS2 Compliance

The **dns-change-alert** module implements EU NIS2 Directive requirements:

- **Directive (EU) 2022/2555** (NIS2 Directive)
- **Article 21** - Cybersecurity risk management
- Pre-notification of changes
- Post-confirmation of changes
- Audit trail with 730-day retention
- User consent model (opt-in)

---

## 📞 Support

### Community Support
- Read documentation
- Search GitHub issues
- Open new issue

### Professional Support
Email: contact@itcms.gr for:
- Custom implementation
- Professional customization
- Priority support

---

## ⭐ Support This Project

- ⭐ Star this repository
- 🐦 Share with colleagues
- 📝 Contribute improvements

---

**Made with ❤️ by [Antonios Voulvoulis](https://itcms.gr) for the ClientExec community**
