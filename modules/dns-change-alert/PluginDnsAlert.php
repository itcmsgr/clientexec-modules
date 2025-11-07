<?php
// SPDX-License-Identifier: LicenseRef-ITCMS-Free-1.0
// ITCMS.GR Free License – All Rights Reserved
// Copyright (c) 2025 Antonios Voulvoulis
// Free to use (including commercial use), but redistribution,
// resale, modification, or cloning is strictly prohibited.
//
// Purpose: ClientExec plugin for DNS change monitoring and alerting (NIS2 compliant)
//
// meta:name=dns-change-alert
// meta:type=plugin
// meta:header=DNS Change Alert
// meta:version=1.0.0
// meta:owner="Antonios Voulvoulis <contact@itcms.gr>"
// meta:homepage=https://itcms.gr/
//
// meta:description=Monitor DNS changes and alert domain owners before/after modifications (EU NIS2 compliant)
// meta:input=Automatic hooks into ClientExec domain/DNS operations
// meta:depends=PHP 8.0+, MySQL 5.7+, dig utility
// meta:requires_env=SMTP or mail server
// meta:created_date=2025-10-29

require_once 'modules/admin/models/Plugin.php';
require_once __DIR__ . '/lib/DnsMonitor.php';
require_once __DIR__ . '/lib/NotificationManager.php';
require_once __DIR__ . '/lib/AuditLogger.php';

use DnsAlert\DnsMonitor;
use DnsAlert\NotificationManager;
use DnsAlert\AuditLogger;

/**
 * PluginDnsAlert - ClientExec Plugin for DNS Change Monitoring
 *
 * Features:
 * - Real-time DNS monitoring
 * - Pre-change and post-change notifications
 * - EU NIS2 compliance
 * - Complete audit trail
 * - Multi-channel notifications (email, SMS, webhooks)
 */
class PluginDnsAlert extends Plugin
{
    public $name = 'DNS Change Alert';
    public $description = 'Monitor DNS changes and notify domain owners (NIS2 compliant)';
    public $version = '1.0.0';
    public $author = 'Antonios Voulvoulis / ITCMS';
    public $email = 'contact@itcms.gr';

    protected $config;
    protected $monitor;
    protected $notifier;
    protected $logger;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        // Load configuration
        $configFile = __DIR__ . '/config/config.php';
        if (file_exists($configFile)) {
            $this->config = require $configFile;
        } else {
            $this->config = $this->getDefaultConfig();
        }

        // Initialize components
        $this->monitor = new DnsMonitor($this->config);
        $this->notifier = new NotificationManager($this->config);
        $this->logger = new AuditLogger($this->config);
    }

    /**
     * Get plugin configuration variables
     */
    public function getVariables()
    {
        return [
            lang('Plugin Name') => [
                'type' => 'hidden',
                'description' => 'DNS Change Alert Module',
                'value' => 'DNS Change Alert'
            ],
            lang('Enabled') => [
                'type' => 'yesno',
                'description' => lang('Enable DNS monitoring and alerts'),
                'value' => '1'
            ],
            lang('Monitor Interval') => [
                'type' => 'text',
                'description' => lang('Check DNS every X minutes'),
                'value' => '5'
            ],
            lang('Pre-Change Alerts') => [
                'type' => 'yesno',
                'description' => lang('Send alerts BEFORE applying DNS changes'),
                'value' => '1'
            ],
            lang('Post-Change Alerts') => [
                'type' => 'yesno',
                'description' => lang('Send confirmations AFTER applying DNS changes'),
                'value' => '1'
            ],
            lang('Delay Before Change (minutes)') => [
                'type' => 'text',
                'description' => lang('How long to wait before applying changes'),
                'value' => '60'
            ],
            lang('From Email') => [
                'type' => 'text',
                'description' => lang('Sender email address for alerts'),
                'value' => 'dns-alerts@yourdomain.com'
            ],
            lang('NIS2 Compliance Mode') => [
                'type' => 'yesno',
                'description' => lang('Enable EU NIS2 compliance features'),
                'value' => '1'
            ],
            lang('Audit Log Retention (days)') => [
                'type' => 'text',
                'description' => lang('How long to keep audit logs (730 = 2 years)'),
                'value' => '730'
            ],
            lang('Debug Mode') => [
                'type' => 'yesno',
                'description' => lang('Enable detailed logging'),
                'value' => '0'
            ],
        ];
    }

    /**
     * Hook: Before DNS change is applied
     * This sends pre-change notifications
     *
     * IMPORTANT: Only sends alerts if user has DNS alerts ENABLED in their profile
     * DEFAULT: Alerts are DISABLED for all users unless explicitly enabled
     */
    public function beforeDnsChange($params)
    {
        if (!$this->config['notifications']['pre_change']) {
            return true;
        }

        $domain = $params['domain'];
        $oldRecords = $params['old_records'];
        $newRecords = $params['new_records'];
        $initiatedBy = $params['user_id'];

        // Get domain owner user ID
        $userId = $this->getDomainOwnerUserId($domain);

        // CHECK USER PREFERENCES - DEFAULT: DISABLED
        // Only send alerts if user explicitly enabled DNS alerts in their profile
        if (!$this->isUserAlertsEnabled($userId)) {
            $this->log("DNS alerts disabled for user {$userId}, skipping pre-change notification");
            return true;  // User has alerts disabled, skip notification
        }

        // Detect changes
        $changes = $this->monitor->compareRecords($oldRecords, $newRecords);

        if (empty($changes)) {
            return true;  // No changes, proceed
        }

        // Get domain owner email
        $ownerEmail = $this->getDomainOwnerEmail($domain);

        if (!$ownerEmail) {
            $this->logError("No owner email for domain: {$domain}");
            return true;  // Continue anyway
        }

        // Send pre-change alert
        $alertData = [
            'domain' => $domain,
            'owner_email' => $ownerEmail,
            'changes' => $changes,
            'initiated_by' => $initiatedBy,
            'scheduled_time' => $this->getScheduledTime(),
            'cancel_url' => $this->getCancelUrl($domain, $params['change_id']),
        ];

        $sent = $this->notifier->sendPreChangeAlert($alertData);

        // Log to audit trail
        $this->logger->logDnsChange([
            'domain' => $domain,
            'type' => 'pre_change',
            'old_records' => $oldRecords,
            'new_records' => $newRecords,
            'changes' => $changes,
            'notification_sent' => $sent,
            'owner_email' => $ownerEmail,
            'user_id' => $initiatedBy,
        ]);

        return true;  // Allow change to proceed
    }

    /**
     * Hook: After DNS change is applied
     * This sends post-change confirmations
     *
     * IMPORTANT: Only sends alerts if user has DNS alerts ENABLED in their profile
     * DEFAULT: Alerts are DISABLED for all users unless explicitly enabled
     */
    public function afterDnsChange($params)
    {
        if (!$this->config['notifications']['post_change']) {
            return true;
        }

        $domain = $params['domain'];
        $oldRecords = $params['old_records'];
        $newRecords = $params['new_records'];
        $initiatedBy = $params['user_id'];

        // Get domain owner user ID
        $userId = $this->getDomainOwnerUserId($domain);

        // CHECK USER PREFERENCES - DEFAULT: DISABLED
        // Only send alerts if user explicitly enabled DNS alerts in their profile
        if (!$this->isUserAlertsEnabled($userId)) {
            $this->log("DNS alerts disabled for user {$userId}, skipping post-change notification");
            return true;  // User has alerts disabled, skip notification
        }

        // Detect changes
        $changes = $this->monitor->compareRecords($oldRecords, $newRecords);

        if (empty($changes)) {
            return true;
        }

        // Get domain owner email
        $ownerEmail = $this->getDomainOwnerEmail($domain);

        if (!$ownerEmail) {
            $this->logError("No owner email for domain: {$domain}");
            return true;
        }

        // Send post-change confirmation
        $confirmData = [
            'domain' => $domain,
            'owner_email' => $ownerEmail,
            'changes' => $changes,
            'initiated_by' => $initiatedBy,
            'applied_time' => date('Y-m-d H:i:s'),
            'verify_url' => $this->getVerifyUrl($domain),
        ];

        $sent = $this->notifier->sendPostChangeConfirmation($confirmData);

        // Log to audit trail
        $this->logger->logDnsChange([
            'domain' => $domain,
            'type' => 'post_change',
            'old_records' => $oldRecords,
            'new_records' => $newRecords,
            'changes' => $changes,
            'notification_sent' => $sent,
            'owner_email' => $ownerEmail,
            'user_id' => $initiatedBy,
            'status' => 'applied',
        ]);

        return true;
    }

    /**
     * Cron job: Monitor all domains for DNS changes
     */
    public function cronMonitorDns()
    {
        if (!$this->config['monitor']['enabled']) {
            return;
        }

        $this->log("Starting DNS monitoring cron job");

        // Get all active domains
        $domains = $this->getAllDomains();

        $checked = 0;
        $changed = 0;
        $errors = 0;

        foreach ($domains as $domain) {
            try {
                $result = $this->monitor->checkDomain($domain['name']);

                if ($result['changed']) {
                    $this->handleDetectedChange($domain, $result['changes']);
                    $changed++;
                }

                $checked++;
            } catch (Exception $e) {
                $this->logError("Error checking {$domain['name']}: " . $e->getMessage());
                $errors++;
            }
        }

        $this->log("DNS monitoring complete: {$checked} checked, {$changed} changed, {$errors} errors");
    }

    /**
     * Handle detected DNS change
     */
    protected function handleDetectedChange($domain, $changes)
    {
        $ownerEmail = $this->getDomainOwnerEmail($domain['name']);

        if (!$ownerEmail) {
            return;
        }

        // Send unexpected change alert
        $alertData = [
            'domain' => $domain['name'],
            'owner_email' => $ownerEmail,
            'changes' => $changes,
            'detected_time' => date('Y-m-d H:i:s'),
            'type' => 'unexpected',
        ];

        $this->notifier->sendUnexpectedChangeAlert($alertData);

        // Log
        $this->logger->logDnsChange([
            'domain' => $domain['name'],
            'type' => 'unexpected_change',
            'changes' => $changes,
            'owner_email' => $ownerEmail,
            'status' => 'detected',
        ]);
    }

    /**
     * Get domain owner user ID
     */
    protected function getDomainOwnerUserId($domain)
    {
        $db = $this->getDb();

        $query = "SELECT user_id FROM domains WHERE name = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$domain]);
        $result = $stmt->fetch();

        return $result ? (int)$result['user_id'] : null;
    }

    /**
     * Get domain owner email
     */
    protected function getDomainOwnerEmail($domain)
    {
        $db = $this->getDb();

        $query = "SELECT u.email
                  FROM domains d
                  JOIN users u ON d.user_id = u.id
                  WHERE d.name = ?";

        $stmt = $db->prepare($query);
        $stmt->execute([$domain]);
        $result = $stmt->fetch();

        return $result ? $result['email'] : null;
    }

    /**
     * Check if user has DNS alerts enabled
     *
     * IMPORTANT: DEFAULT is FALSE (disabled)
     * Users must explicitly enable DNS alerts in their profile
     *
     * @param int $userId User ID
     * @return bool True if enabled, False if disabled (default)
     */
    protected function isUserAlertsEnabled($userId)
    {
        if (!$userId) {
            return false;  // No user ID, default to disabled
        }

        $db = $this->getDb();

        try {
            // Check if user has DNS alert preferences
            $stmt = $db->prepare("
                SELECT enabled
                FROM dns_notifications_prefs
                WHERE user_id = ?
                  AND (domain_id IS NULL OR domain_id = 0)
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch();

            if ($result) {
                // User has preferences set, return their choice
                return (bool)$result['enabled'];
            }

            // No preferences found - DEFAULT: DISABLED
            // This ensures opt-in model as required
            return false;

        } catch (Exception $e) {
            $this->logError("Error checking user alert preferences: " . $e->getMessage());
            return false;  // On error, default to disabled for safety
        }
    }

    /**
     * Get all active domains
     */
    protected function getAllDomains()
    {
        $db = $this->getDb();

        $query = "SELECT name, user_id FROM domains WHERE status = 'active'";
        $stmt = $db->query($query);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get scheduled time for change
     */
    protected function getScheduledTime()
    {
        $delay = $this->config['notifications']['delay_minutes'] ?? 60;
        return date('Y-m-d H:i:s', strtotime("+{$delay} minutes"));
    }

    /**
     * Get cancel URL
     */
    protected function getCancelUrl($domain, $changeId)
    {
        $baseUrl = $this->config['base_url'] ?? 'https://your-clientexec.com';
        return "{$baseUrl}/client/dns-alert/cancel/{$changeId}";
    }

    /**
     * Get verify URL
     */
    protected function getVerifyUrl($domain)
    {
        $baseUrl = $this->config['base_url'] ?? 'https://your-clientexec.com';
        return "{$baseUrl}/client/domains/dns?domain={$domain}";
    }

    /**
     * Get default configuration
     */
    protected function getDefaultConfig()
    {
        return [
            'enabled' => true,
            'debug_mode' => false,
            'monitor' => [
                'enabled' => true,
                'check_interval' => 300,
                'record_types' => ['A', 'AAAA', 'MX', 'CNAME', 'TXT', 'NS'],
            ],
            'notifications' => [
                'pre_change' => true,
                'post_change' => true,
                'delay_minutes' => 60,
                'from_email' => 'dns-alerts@yourdomain.com',
                'from_name' => 'DNS Alert System',
            ],
            'compliance' => [
                'nis2_mode' => true,
                'audit_retention_days' => 730,
            ],
        ];
    }

    /**
     * Log message
     */
    protected function log($message)
    {
        $logFile = __DIR__ . '/logs/dns-alert.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Log error
     */
    protected function logError($message)
    {
        $this->log("ERROR: {$message}");
    }

    /**
     * Get database connection
     */
    protected function getDb()
    {
        // ClientExec database connection
        global $db;
        return $db;
    }
}
