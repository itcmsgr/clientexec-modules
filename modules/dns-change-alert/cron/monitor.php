#!/usr/bin/env php
<?php
// SPDX-License-Identifier: LicenseRef-ITCMS-Free-1.0
// ITCMS.GR Free License – All Rights Reserved
// Copyright (c) 2025 Antonios Voulvoulis
// Free to use (including commercial use), but redistribution,
// resale, modification, or cloning is strictly prohibited.
//
// Purpose: Cron script for DNS monitoring and notification queue processing
//
// Usage: php cron/monitor.php
// Crontab: */5 * * * * /usr/bin/php /path/to/clientexec/plugins/dns_alert/cron/monitor.php

// Prevent web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

// Start timing
$startTime = microtime(true);

// Bootstrap ClientExec
define('EXEC_DIR', realpath(__DIR__ . '/../../../../'));
require_once EXEC_DIR . '/config.php';
require_once EXEC_DIR . '/library/CE/Bootstrap.php';

// Load DNS Alert module
require_once __DIR__ . '/../PluginDnsAlert.php';
require_once __DIR__ . '/../lib/DnsMonitor.php';
require_once __DIR__ . '/../lib/NotificationManager.php';
require_once __DIR__ . '/../lib/AuditLogger.php';

// Load configuration
$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    error_log('[DNS Alert] Configuration file not found: ' . $configFile);
    exit(1);
}
$config = require $configFile;

// Check if module is enabled
if (!($config['enabled'] ?? true)) {
    echo "DNS Alert module is disabled\n";
    exit(0);
}

// Initialize logger
$logFile = __DIR__ . '/../logs/cron.log';
$log = function ($message) use ($logFile) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    echo $logMessage;
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
};

$log('=== DNS Alert Cron Job Started ===');

// Check for lock file (prevent overlapping runs)
$lockFile = __DIR__ . '/../logs/cron.lock';
if (file_exists($lockFile)) {
    $lockAge = time() - filemtime($lockFile);
    $lockTimeout = ($config['cron']['lock_timeout_minutes'] ?? 15) * 60;

    if ($lockAge < $lockTimeout) {
        $log('Another cron instance is still running (lock age: ' . $lockAge . 's)');
        exit(0);
    } else {
        $log('Stale lock file detected, removing...');
        @unlink($lockFile);
    }
}

// Create lock file
file_put_contents($lockFile, getmypid());

try {
    // Initialize components
    $monitor = new DnsAlert\DnsMonitor($config);
    $notifier = new DnsAlert\NotificationManager($config);
    $auditor = new DnsAlert\AuditLogger($config);

    $log('Components initialized successfully');

    // === TASK 1: Process Notification Queue ===
    $log('--- Processing notification queue ---');

    $queueStats = $notifier->processQueue($config['advanced']['queue_batch_size'] ?? 100);
    $log(sprintf(
        'Queue: %d processed, %d delivered, %d failed',
        $queueStats['processed'],
        $queueStats['delivered'],
        $queueStats['failed']
    ));

    // === TASK 2: Monitor DNS Changes ===
    if ($config['monitor']['enabled'] ?? true) {
        $log('--- Monitoring DNS changes ---');

        // Get database connection
        global $db;
        if (!isset($db)) {
            throw new Exception('Database connection not available');
        }

        // Get all domains with DNS alerts enabled
        $stmt = $db->prepare("
            SELECT DISTINCT
                d.id as domain_id,
                d.name as domain_name,
                d.user_id,
                p.owner_email,
                p.cron_interval
            FROM domains d
            INNER JOIN dns_notifications_prefs p ON d.user_id = p.user_id
            WHERE d.status = 'active'
              AND p.enabled = 1
              AND (p.domain_id IS NULL OR p.domain_id = d.id)
        ");
        $stmt->execute();
        $domains = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $log(sprintf('Found %d domains with DNS alerts enabled', count($domains)));

        $checked = 0;
        $changed = 0;
        $errors = 0;

        foreach ($domains as $domain) {
            try {
                // Check if enough time has passed since last check
                $intervalSeconds = $domain['cron_interval'] ?? 300;
                $lastCheckStmt = $db->prepare("
                    SELECT MAX(created_at) as last_check
                    FROM dns_alert_snapshots
                    WHERE domain = ?
                ");
                $lastCheckStmt->execute([$domain['domain_name']]);
                $lastCheck = $lastCheckStmt->fetchColumn();

                if ($lastCheck && (time() - strtotime($lastCheck)) < $intervalSeconds) {
                    continue; // Skip this domain, not enough time has passed
                }

                // Check domain for changes
                $result = $monitor->checkDomain($domain['domain_name']);

                if ($result['changed']) {
                    $log(sprintf(
                        'DNS change detected: %s (%d changes)',
                        $domain['domain_name'],
                        count($result['changes'])
                    ));

                    // Create audit log
                    $auditId = $auditor->logDnsChange([
                        'domain_id' => $domain['domain_id'],
                        'domain' => $domain['domain_name'],
                        'user_id' => $domain['user_id'],
                        'type' => 'unexpected_change',
                        'change_source' => 'CRON_DETECTED',
                        'old_records' => $result['old_records'],
                        'new_records' => $result['new_records'],
                        'changes' => $result['changes'],
                        'status' => 'DETECTED',
                        'notification_sent' => false,
                    ]);

                    // Queue unexpected change notification
                    $notifier->sendUnexpectedChangeAlert([
                        'audit_id' => $auditId,
                        'domain' => $domain['domain_name'],
                        'owner_email' => $domain['owner_email'],
                        'changes' => $result['changes'],
                        'detected_time' => date('Y-m-d H:i:s'),
                        'support_url' => ($config['base_url'] ?? '') . '/support',
                        'verify_url' => ($config['base_url'] ?? '') . '/client/domains/dns?domain=' . $domain['domain_name'],
                    ]);

                    $changed++;
                }

                $checked++;

                // Rate limiting: small delay between checks
                usleep(100000); // 0.1 seconds

            } catch (Exception $e) {
                $log(sprintf('Error checking %s: %s', $domain['domain_name'], $e->getMessage()));
                $errors++;
            }
        }

        $log(sprintf('DNS monitoring complete: %d checked, %d changed, %d errors', $checked, $changed, $errors));
    }

    // === TASK 3: Cleanup Old Audit Logs (once daily) ===
    $lastCleanupFile = __DIR__ . '/../logs/last_cleanup.txt';
    $lastCleanup = file_exists($lastCleanupFile) ? file_get_contents($lastCleanupFile) : '2000-01-01';

    if (date('Y-m-d') !== $lastCleanup) {
        $log('--- Running daily cleanup ---');

        $deletedRows = $auditor->cleanupOldLogs();
        $log(sprintf('Cleaned up %d old audit log entries', $deletedRows));

        file_put_contents($lastCleanupFile, date('Y-m-d'));
    }

    // Remove lock file
    @unlink($lockFile);

    // Calculate execution time
    $executionTime = round(microtime(true) - $startTime, 2);
    $log(sprintf('=== Cron job completed in %s seconds ===', $executionTime));

    exit(0);

} catch (Exception $e) {
    $log('FATAL ERROR: ' . $e->getMessage());
    $log('Stack trace: ' . $e->getTraceAsString());

    // Remove lock file on error
    @unlink($lockFile);

    exit(1);
}
