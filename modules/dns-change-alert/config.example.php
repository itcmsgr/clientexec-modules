<?php
// SPDX-License-Identifier: LicenseRef-ITCMS-Free-1.0
// ITCMS.GR Free License – All Rights Reserved
// Copyright (c) 2025 Antonios Voulvoulis
// Free to use (including commercial use), but redistribution,
// resale, modification, or cloning is strictly prohibited.
//
// DNS Change Alert Module - Configuration Example
//
// Instructions:
// 1. Copy this file to config/config.php
// 2. Edit the values below to match your environment
// 3. Ensure config/config.php is NOT committed to version control

return [
    // =====================================================
    // Module Settings
    // =====================================================
    'enabled' => true,
    'debug_mode' => false,
    'base_url' => 'https://your-clientexec-installation.com',

    // =====================================================
    // DEFAULT SETTING: Alerts are DISABLED for users
    // Users must explicitly ENABLE DNS alerts in their profile
    // =====================================================
    'default_user_enabled' => false,  // IMPORTANT: Keep false for opt-in model

    // =====================================================
    // DNS Monitoring
    // =====================================================
    'monitor' => [
        'enabled' => true,
        'check_interval' => 300,  // Seconds (300 = 5 minutes)
        'record_types' => ['A', 'AAAA', 'MX', 'CNAME', 'TXT', 'NS', 'SOA'],
        'dig_command' => '/usr/bin/dig',
        'timeout' => 10,  // DNS query timeout in seconds
    ],

    // =====================================================
    // Notifications
    // =====================================================
    'notifications' => [
        // Pre-change alerts (before DNS modification)
        'pre_change' => true,

        // Post-change confirmations (after DNS modification)
        'post_change' => true,

        // Delay before applying changes (minutes)
        // This gives users time to cancel unwanted changes
        'delay_minutes' => 60,

        // Email settings
        'from_email' => 'dns-alerts@yourdomain.com',
        'from_name' => 'DNS Alert System',

        // Maximum retry attempts for failed deliveries
        'max_retry_attempts' => 5,

        // Retry backoff (minutes): 5, 15, 30, 60, 120
        'retry_backoff' => [5, 15, 30, 60, 120],
    ],

    // =====================================================
    // Email Configuration
    // =====================================================
    'email' => [
        // Method: 'smtp', 'sendmail', 'mail', 'clientexec'
        'method' => 'clientexec',  // Use ClientExec's built-in mailer

        // SMTP settings (if method = 'smtp')
        'smtp_host' => 'mail.yourdomain.com',
        'smtp_port' => 587,
        'smtp_user' => 'dns-alerts@yourdomain.com',
        'smtp_pass' => 'your-secure-password-here',
        'smtp_encryption' => 'tls',  // 'tls', 'ssl', or 'none'
        'smtp_auth' => true,
    ],

    // =====================================================
    // SMS Configuration (Optional)
    // =====================================================
    'sms' => [
        'enabled' => false,
        'provider' => 'twilio',  // 'twilio', 'vonage', 'custom'

        // Twilio settings
        'twilio_account_sid' => 'your-twilio-account-sid',
        'twilio_auth_token' => 'your-twilio-auth-token',
        'twilio_from_number' => '+1234567890',

        // Vonage settings
        'vonage_api_key' => '',
        'vonage_api_secret' => '',
        'vonage_from_name' => 'DNS Alert',
    ],

    // =====================================================
    // Webhook Configuration (Optional)
    // =====================================================
    'webhook' => [
        'enabled' => false,
        'default_url' => null,  // Default webhook URL
        'timeout' => 10,        // Webhook request timeout (seconds)
        'verify_ssl' => true,   // Verify SSL certificates
        'secret_key' => '',     // HMAC signing key for webhooks
    ],

    // =====================================================
    // NIS2 Compliance
    // =====================================================
    'compliance' => [
        // Enable NIS2 compliance mode
        'nis2_mode' => true,

        // Audit log retention (days)
        // NIS2 requires minimum 2 years (730 days)
        'audit_retention_days' => 730,

        // Require owner email for all domains
        'require_owner_email' => true,

        // Require manual confirmation for critical changes
        'require_confirmation' => false,

        // Export audit logs automatically
        'auto_export_enabled' => false,
        'export_path' => '/var/backups/dns-alerts/',
        'export_interval_days' => 30,
    ],

    // =====================================================
    // Security
    // =====================================================
    'security' => [
        // Enable API access
        'api_enabled' => true,

        // API authentication key
        // Generate with: openssl rand -hex 32
        'api_key' => 'your-random-api-key-here-replace-this',

        // Encrypt sensitive notification data
        'encrypt_notifications' => false,

        // Allowed IP addresses for API access (empty = all)
        'allowed_ips' => [],

        // Rate limiting
        'rate_limit_enabled' => true,
        'rate_limit_max_requests' => 100,  // Per hour
    ],

    // =====================================================
    // Database (usually auto-detected from ClientExec)
    // =====================================================
    'database' => [
        'host' => 'localhost',
        'name' => 'clientexec',
        'user' => 'clientexec_user',
        'pass' => 'clientexec_password',
        'charset' => 'utf8mb4',
    ],

    // =====================================================
    // Advanced Options
    // =====================================================
    'advanced' => [
        // Queue processing batch size
        'queue_batch_size' => 100,

        // Maximum concurrent notifications
        'max_concurrent_notifications' => 10,

        // Enable detailed logging
        'verbose_logging' => false,

        // Log file rotation
        'log_rotation_days' => 30,
        'log_max_size_mb' => 100,

        // Performance
        'cache_dns_results' => true,
        'cache_ttl_seconds' => 300,

        // Parallel DNS checks (experimental)
        'parallel_dns_checks' => false,
    ],

    // =====================================================
    // User Interface
    // =====================================================
    'ui' => [
        // Show DNS alert settings in user profile
        'show_in_profile' => true,

        // Allow users to configure their own alert preferences
        'allow_user_customization' => true,

        // Show audit trail to users
        'show_audit_trail' => true,

        // Allow users to export their audit logs
        'allow_user_export' => true,
    ],

    // =====================================================
    // Cron Jobs
    // =====================================================
    'cron' => [
        // Monitoring cron interval (minutes)
        'monitor_interval' => 5,

        // Queue processing cron interval (minutes)
        'queue_interval' => 1,

        // Cleanup cron interval (days)
        'cleanup_interval' => 7,

        // Cron job locking (prevent overlapping runs)
        'enable_locking' => true,
        'lock_timeout_minutes' => 15,
    ],
];
