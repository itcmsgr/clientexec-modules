# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-10-30

### Added
- **RecallApplication functionality** - Cancel domain registration within 5 days (.GR specific)
- Protocol ID parsing from EPP domain-info responses
- ROID (Registry Object ID) parsing from domain-info
- Domain password parsing for deletion operations
- `.GR extension support` for domain-delete operations
- `domain-recall-application` EPP command
- `buildDomainRecallApplicationXML()` method in GrEppClient
- Enhanced `parseExtensionData()` to extract protocol IDs
- `recallApplication()` method in PluginGrepp
- Comprehensive RecallApplication documentation in installation guide

### Changed
- Enhanced EPP client to parse .GR specific extension data
- Updated installation guide with RecallApplication usage instructions
- Improved feature list documentation with all date synchronization details

### Technical Details
- EPP Extension: `http://www.ics.forth.gr/gr-domain-ext-1.0`
- New EPP operation: `recallApplication` via domain-delete with extension
- Authentication: Uses protocol ID (not password) for recall operations
- Time limit: 5 days from registration date

## [1.0.0] - 2025-10-28

### Added
- Initial release for ClientExec
- Complete EPP client library (GrEppClient.php)
- ClientExec plugin integration (PluginGrepp.php)
- Domain registration functionality
- Domain renewal functionality
- Domain transfer functionality
- Domain availability check
- Domain information retrieval
- Contact management (Registrant, Admin, Tech, Billing)
- WHOIS contact information display
- Contact update functionality (owner details)
- Automatic contact creation
- Nameserver management (add/remove/update)
- EPP 4.3 protocol support
- Sandbox/UAT environment support
- Production environment support
- Automated domain synchronization cron job
- Registration date tracking
- Expiration date monitoring
- Updated date display
- Next due date auto-calculation
- Connectivity diagnostic tool
- SSL certificate verification
- Comprehensive logging system
- IDN support for .ελ (Greek script) domains
- Greek character sanitization
- Accent removal for Greek text
- Final sigma (ς/σ) conversion
- Punycode encoding/decoding
- Session-based authentication
- Cookie management
- Password masking in logs
- Secure random password generation
- Configuration template (config.example.php)
- Complete documentation (README.md)
- Installation guide (INSTALL.md)
- Quick start guide (QUICKSTART.md)
- Contributing guidelines (CONTRIBUTING.md)
- Project information file (PROJECT_INFO.txt)
- MPL-2.0 License (LICENSE)
- Git configuration (.gitignore, .gitattributes)

### Security
- SSL/TLS certificate pinning
- Encrypted credential storage
- Input sanitization
- SQL injection prevention
- XSS protection
- Secure session management

### Documentation
- Comprehensive README with all features
- Step-by-step installation guide
- 5-minute quick start guide
- Troubleshooting section
- API reference
- Greek character handling guide
- Security best practices
- Inline code documentation
- Meta blocks in all files

## [1.1.0] - 2025-10-28

### Added
- **DACOR Token Support** - Full implementation of EPP 4.3+ DACOR (Domain Authorization Code Registry) system
- **EPP Code Retrieval** - Proper `getEPPCode()` function using DACOR tokens for secure domain transfers
- **Nameserver Glue Records Management**:
  - `registerNameserver()` - Create glue records with IPv4/IPv6 addresses
  - `modifyNameserver()` - Update nameserver IP addresses
  - `deleteNameserver()` - Remove nameserver glue records
- **Domain Deletion** - `requestDelete()` function for domain deletion requests
- **Host EPP Commands**:
  - `host-update` - Update nameserver IP addresses
  - `host-delete` - Delete nameservers
  - Enhanced `host-info` - Get nameserver details with IP address parsing
- **Domain Delete Command** - `domain-delete` EPP command support
- **Extension Data Parsing** - Proper parsing of EPP extension responses for DACOR tokens and other extensions

### Changed
- **getEPPCode()** - Replaced placeholder implementation with full DACOR token retrieval system
- **GrEppClient** - Added support for 5 new EPP commands (dacor-issue-token, host-update, host-delete, domain-delete)
- **Response Parser** - Enhanced to handle EPP extension data and host information
- **parseResData()** - Now properly parses host info including IP addresses
- **parseExtensionData()** - New method to handle EPP extension responses

### Fixed
- Host info parsing now correctly extracts IPv4 and IPv6 addresses
- Extension data is properly merged with standard response data
- DACOR tokens are correctly extracted from EPP extension responses

### Security
- DACOR tokens provide time-limited transfer authorization instead of static passwords
- Improved security for domain transfers using registry-generated tokens

## [Unreleased]

### Planned
- Unit tests
- Integration tests
- Bulk domain operations
- Advanced reporting
- Email notifications
- Multi-language support
- CLI tools for domain management

---

## Version Format

- **Major.Minor.Patch** (e.g., 1.0.0)
- **Major**: Breaking changes
- **Minor**: New features (backwards compatible)
- **Patch**: Bug fixes (backwards compatible)

## Categories

- **Added**: New features
- **Changed**: Changes to existing functionality
- **Deprecated**: Soon-to-be removed features
- **Removed**: Removed features
- **Fixed**: Bug fixes
- **Security**: Security improvements
