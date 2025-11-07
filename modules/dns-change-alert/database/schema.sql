-- SPDX-License-Identifier: LicenseRef-ITCMS-Free-1.0
-- ITCMS.GR Free License – All Rights Reserved
-- Copyright (c) 2025 Antonios Voulvoulis
-- Free to use (including commercial use), but redistribution,
-- resale, modification, or cloning is strictly prohibited.
--
-- Database Schema for DNS Change Alert Module
-- NIS2 Compliance: Audit trail, notification tracking, user preferences
-- Version: 1.0.0

-- =====================================================
-- Table: dns_notifications_prefs
-- Purpose: Per-domain or per-user notification preferences
-- DEFAULT: Alerts are DISABLED unless explicitly enabled
-- =====================================================
CREATE TABLE IF NOT EXISTS dns_notifications_prefs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  domain_id INT NULL,                    -- NULL = applies to all user's domains
  enabled TINYINT(1) NOT NULL DEFAULT 0, -- DEFAULT: DISABLED
  notify_before TINYINT(1) NOT NULL DEFAULT 1,
  notify_after TINYINT(1) NOT NULL DEFAULT 1,
  cron_interval INT NOT NULL DEFAULT 300, -- Seconds (300 = 5 minutes)
  owner_email VARCHAR(255) NOT NULL,
  preferred_channels JSON NULL,          -- e.g., {"email":true,"sms":false,"webhook":true}
  webhook_url VARCHAR(512) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_domain_id (domain_id),
  INDEX idx_enabled (enabled),
  UNIQUE KEY unique_user_domain (user_id, domain_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: dns_change_audit
-- Purpose: Immutable audit trail for NIS2 compliance
-- Retention: 730 days (2 years) minimum
-- =====================================================
CREATE TABLE IF NOT EXISTS dns_change_audit (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  domain_id INT NOT NULL,
  domain_name VARCHAR(255) NOT NULL,
  user_id INT NULL,                     -- Domain owner
  actor_user_id INT NULL,               -- Who initiated change
  action VARCHAR(64) NOT NULL,          -- UPDATE_ZONE|CREATE|DELETE
  change_type VARCHAR(32) NOT NULL,     -- MANUAL|CRON_DETECTED|AUTOMATIC
  old_zone TEXT NULL,                   -- JSON snapshot of old DNS records
  new_zone TEXT NULL,                   -- JSON snapshot of new DNS records
  status VARCHAR(32) NOT NULL,          -- PENDING|APPLIED|FAILED|ROLLED_BACK
  pre_notice_sent TINYINT(1) DEFAULT 0,
  post_notice_sent TINYINT(1) DEFAULT 0,
  attempts INT DEFAULT 0,
  error_message TEXT NULL,
  ip_address VARCHAR(45) NULL,          -- IPv4 or IPv6
  user_agent TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_domain_id (domain_id),
  INDEX idx_domain_name (domain_name),
  INDEX idx_user_id (user_id),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at),
  INDEX idx_change_type (change_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: dns_notification_queue
-- Purpose: Retryable notification deliveries
-- Supports: Email, SMS, Webhooks with exponential backoff
-- =====================================================
CREATE TABLE IF NOT EXISTS dns_notification_queue (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  audit_id BIGINT NOT NULL,
  type VARCHAR(16) NOT NULL,            -- PRE|POST|UNEXPECTED
  channel VARCHAR(16) NOT NULL,         -- EMAIL|SMS|WEBHOOK
  recipient VARCHAR(255) NOT NULL,      -- Email, phone, or webhook URL
  payload JSON NOT NULL,
  attempt INT DEFAULT 0,
  max_attempts INT DEFAULT 5,
  next_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_error TEXT NULL,
  delivered_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_audit_id (audit_id),
  INDEX idx_next_attempt (next_attempt_at),
  INDEX idx_delivered (delivered_at),
  INDEX idx_type_channel (type, channel),
  FOREIGN KEY (audit_id) REFERENCES dns_change_audit(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: dns_alert_snapshots
-- Purpose: Store DNS record snapshots for comparison
-- Used by: DnsMonitor.php for change detection
-- =====================================================
CREATE TABLE IF NOT EXISTS dns_alert_snapshots (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  domain_id INT NOT NULL,
  domain VARCHAR(255) NOT NULL,
  records TEXT NOT NULL,                -- JSON: {"A":["1.2.3.4"],"MX":["10 mail.example.com"]}
  record_hash VARCHAR(64) NOT NULL,     -- SHA256 for quick comparison
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_domain (domain),
  INDEX idx_domain_id (domain_id),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: dns_alert_config
-- Purpose: Global module configuration
-- =====================================================
CREATE TABLE IF NOT EXISTS dns_alert_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  config_key VARCHAR(128) NOT NULL UNIQUE,
  config_value TEXT NULL,
  config_type VARCHAR(32) DEFAULT 'string', -- string|int|bool|json
  description TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_config_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Insert default configuration
-- =====================================================
INSERT INTO dns_alert_config (config_key, config_value, config_type, description) VALUES
('module_enabled', '1', 'bool', 'Enable/disable entire DNS alert module'),
('default_user_enabled', '0', 'bool', 'DEFAULT: Users have alerts DISABLED unless they enable'),
('audit_retention_days', '730', 'int', 'NIS2: Keep audit logs for 2 years minimum'),
('max_retry_attempts', '5', 'int', 'Maximum notification delivery attempts'),
('retry_backoff_minutes', '5,15,30,60,120', 'string', 'Exponential backoff for retries (minutes)'),
('from_email', 'dns-alerts@yourdomain.com', 'string', 'Sender email address'),
('from_name', 'DNS Alert System', 'string', 'Sender name'),
('debug_mode', '0', 'bool', 'Enable verbose logging'),
('nis2_compliance_mode', '1', 'bool', 'Enable NIS2 compliance features')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- =====================================================
-- Cleanup job for old audit records (run monthly)
-- =====================================================
DELIMITER $$
CREATE EVENT IF NOT EXISTS cleanup_old_dns_audits
ON SCHEDULE EVERY 1 MONTH
DO BEGIN
  DELETE FROM dns_change_audit
  WHERE created_at < DATE_SUB(NOW(), INTERVAL
    (SELECT CAST(config_value AS UNSIGNED) FROM dns_alert_config WHERE config_key = 'audit_retention_days' LIMIT 1)
    DAY);
END$$
DELIMITER ;

-- =====================================================
-- Views for reporting
-- =====================================================

-- Recent changes view
CREATE OR REPLACE VIEW dns_recent_changes AS
SELECT
  a.id,
  a.domain_name,
  a.user_id,
  a.actor_user_id,
  a.change_type,
  a.status,
  a.pre_notice_sent,
  a.post_notice_sent,
  a.created_at,
  COUNT(q.id) as pending_notifications
FROM dns_change_audit a
LEFT JOIN dns_notification_queue q ON a.id = q.audit_id AND q.delivered_at IS NULL
WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY a.id
ORDER BY a.created_at DESC;

-- User notification statistics
CREATE OR REPLACE VIEW dns_user_stats AS
SELECT
  p.user_id,
  p.owner_email,
  COUNT(DISTINCT p.domain_id) as monitored_domains,
  SUM(p.enabled) as enabled_domains,
  COUNT(a.id) as total_changes_30d,
  SUM(CASE WHEN a.status = 'APPLIED' THEN 1 ELSE 0 END) as successful_changes,
  SUM(CASE WHEN a.status = 'FAILED' THEN 1 ELSE 0 END) as failed_changes
FROM dns_notifications_prefs p
LEFT JOIN dns_change_audit a ON p.domain_id = a.domain_id
  AND a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY p.user_id, p.owner_email;

-- =====================================================
-- Schema version tracking
-- =====================================================
INSERT INTO dns_alert_config (config_key, config_value, config_type, description) VALUES
('schema_version', '1.0.0', 'string', 'Database schema version')
ON DUPLICATE KEY UPDATE config_value = '1.0.0', updated_at = CURRENT_TIMESTAMP;
