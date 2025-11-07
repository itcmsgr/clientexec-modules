<?php
// SPDX-License-Identifier: LicenseRef-ITCMS-Free-1.0
// ITCMS.GR Free License – All Rights Reserved
// Copyright (c) 2025 Antonios Voulvoulis
// Free to use (including commercial use), but redistribution,
// resale, modification, or cloning is strictly prohibited.
//
// Purpose: Cron job to sync domain expiration dates from .GR registry
//
// meta:name=grepp-cron-sync
// meta:type=cron
// meta:header=grEPP Domain Sync Cron
// meta:version=1.0.0
// meta:owner="Antonios Voulvoulis <contact@itcms.gr>"
// meta:homepage=https://itcms.gr/
//
// meta:description=Syncs domain registration/expiration/update dates from registry to ClientExec
// meta:usage=Add to crontab: 0 2 * * * /usr/bin/php /path/to/sync-domains.php
// meta:schedule=Daily at 2:00 AM recommended
// meta:created_date=2025-10-25
// meta:updated_date=2025-10-29

// Adjust path to your ClientExec installation
define('CLIENTEXEC_PATH', dirname(__DIR__, 3));

// Load ClientExec bootstrap
if (!file_exists(CLIENTEXEC_PATH . '/config.php')) {
    die("ERROR: ClientExec not found at " . CLIENTEXEC_PATH . "\n");
}

require_once CLIENTEXEC_PATH . '/config.php';
require_once CLIENTEXEC_PATH . '/library/CE/NE_Network.php';

require_once dirname(__DIR__) . '/lib/GrEppClient.php';

use ITCMS\ClientExec\GR\GrEppClient;

/**
 * Domain Synchronization Cron Job
 *
 * This script:
 * 1. Retrieves all .gr/.ελ domains from ClientExec database
 * 2. Queries the registry for current domain information
 * 3. Updates expiration dates and status in ClientExec
 * 4. Logs all changes for audit purposes
 */
class GreppDomainSync
{
    private $db;
    private $client;
    private $logFile;
    private $config;
    private $stats;

    public function __construct()
    {
        global $db;
        $this->db = $db;

        $this->logFile = dirname(__DIR__) . '/logs/sync_' . date('Y-m-d') . '.log';
        $this->stats = [
            'total' => 0,
            'updated' => 0,
            'expired' => 0,
            'transferred_away' => 0,
            'errors' => 0,
            'skipped' => 0
        ];

        $this->log("========================================");
        $this->log("grEPP Domain Sync Started");
        $this->log("========================================");
    }

    /**
     * Load configuration from ClientExec plugin settings
     */
    private function loadConfig()
    {
        // Query plugin configuration from ClientExec database
        $query = "SELECT `key`, `value` FROM plugin_grepp_settings WHERE `key` IN (?, ?, ?, ?, ?)";
        $result = $this->db->query($query, [
            'Registrar ID',
            'EPP Username',
            'EPP Password',
            'UAT Password',
            'Use Sandbox'
        ]);

        $settings = [];
        while ($row = $result->fetch()) {
            $settings[$row['key']] = $row['value'];
        }

        $useSandbox = ($settings['Use Sandbox'] ?? '0') == '1';
        $password = $useSandbox && !empty($settings['UAT Password'])
            ? $settings['UAT Password']
            : $settings['EPP Password'];

        $this->config = [
            'registrarId' => $settings['Registrar ID'] ?? '',
            'username' => $settings['EPP Username'] ?? '',
            'password' => $password,
            'production' => !$useSandbox,
            'logFile' => $this->logFile
        ];

        if (empty($this->config['registrarId']) || empty($this->config['username']) || empty($this->config['password'])) {
            throw new Exception('Missing required configuration parameters');
        }

        $this->log("Configuration loaded - Environment: " . ($useSandbox ? 'SANDBOX' : 'PRODUCTION'));
    }

    /**
     * Initialize EPP client
     */
    private function initClient()
    {
        try {
            $this->client = new GrEppClient($this->config);
            $this->log("EPP Client initialized successfully");
        } catch (Exception $e) {
            $this->log("FATAL: Failed to initialize EPP client - " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Get all .gr and .ελ domains from ClientExec
     */
    private function getDomains()
    {
        $query = "SELECT
            d.id,
            d.name,
            d.status,
            d.dateregistered,
            d.expires,
            d.recurringamount
        FROM
            domains d
        WHERE
            (d.name LIKE '%.gr' OR d.name LIKE '%.ελ')
            AND d.pluginused = 'grepp'
            AND d.status IN ('Active', 'Pending', 'Suspended')
        ORDER BY d.expires ASC";

        $result = $this->db->query($query);
        $domains = [];

        while ($row = $result->fetch()) {
            $domains[] = $row;
        }

        $this->log("Found " . count($domains) . " domains to sync");
        return $domains;
    }

    /**
     * Sync a single domain
     */
    private function syncDomain($domain)
    {
        $this->stats['total']++;
        $domainName = $domain['name'];

        $this->log("Processing: {$domainName}");

        try {
            // Query registry for domain info
            $result = $this->client->exec('domain-info', ['domain' => $domainName]);

            if (!$result['success']) {
                return $this->handleError($domain, $result);
            }

            $data = $result['data'];

            // Extract dates
            $expiryDate = substr($data['exDate'], 0, 10);
            $regDate = substr($data['crDate'], 0, 10);
            $updatedDate = isset($data['upDate']) ? substr($data['upDate'], 0, 10) : null;

            // Check current database values
            $currentExpiry = $domain['expires'];
            $needsUpdate = false;
            $changes = [];

            // Compare expiry date
            if ($currentExpiry !== $expiryDate) {
                $changes[] = "Expiry: {$currentExpiry} → {$expiryDate}";
                $needsUpdate = true;
            }

            // Check domain status
            if (isset($data['status'])) {
                if (in_array('pendingDelete', $data['status'])) {
                    $changes[] = "Status: Pending Delete";
                    $needsUpdate = true;
                    $this->stats['expired']++;
                }
            }

            if ($needsUpdate) {
                $this->updateDomain($domain['id'], [
                    'expires' => $expiryDate,
                    'dateregistered' => $regDate,
                    'lastupdated' => $updatedDate ?? date('Y-m-d H:i:s')
                ]);

                $this->log("  ✓ Updated: " . implode(', ', $changes), 'INFO');
                $this->stats['updated']++;
            } else {
                $this->log("  - No changes needed", 'DEBUG');
                $this->stats['skipped']++;
            }

        } catch (Exception $e) {
            $this->log("  ✗ ERROR: " . $e->getMessage(), 'ERROR');
            $this->stats['errors']++;
        }
    }

    /**
     * Handle error responses from registry
     */
    private function handleError($domain, $result)
    {
        $errorCode = $result['error']['code'] ?? 0;
        $errorMsg = $result['error']['msg'] ?? 'Unknown error';

        switch ($errorCode) {
            case 2303: // Domain not found
                $this->log("  ! Domain not found in registry (code 2303) - May be expired", 'WARN');
                $this->updateDomain($domain['id'], [
                    'status' => 'Expired',
                    'notes' => 'Domain not found in registry as of ' . date('Y-m-d')
                ]);
                $this->stats['expired']++;
                break;

            case 2201: // Authorization error - possibly transferred away
                $this->log("  ! Domain authorization failed (code 2201) - May be transferred away", 'WARN');
                $this->updateDomain($domain['id'], [
                    'status' => 'Transferred Away',
                    'notes' => 'Domain transferred away as of ' . date('Y-m-d')
                ]);
                $this->stats['transferred_away']++;
                break;

            default:
                $this->log("  ✗ Registry error: {$errorMsg} (code {$errorCode})", 'ERROR');
                $this->stats['errors']++;
                break;
        }
    }

    /**
     * Update domain in database
     */
    private function updateDomain($domainId, $data)
    {
        $setClauses = [];
        $values = [];

        foreach ($data as $field => $value) {
            $setClauses[] = "{$field} = ?";
            $values[] = $value;
        }

        $values[] = $domainId;

        $query = "UPDATE domains SET " . implode(', ', $setClauses) . " WHERE id = ?";
        $this->db->query($query, $values);
    }

    /**
     * Run the sync process
     */
    public function run()
    {
        try {
            $startTime = microtime(true);

            $this->loadConfig();
            $this->initClient();

            $domains = $this->getDomains();

            if (empty($domains)) {
                $this->log("No domains found to sync");
                return;
            }

            foreach ($domains as $domain) {
                $this->syncDomain($domain);

                // Small delay to avoid overwhelming the registry
                usleep(250000); // 250ms
            }

            $duration = microtime(true) - $startTime;

            $this->log("========================================");
            $this->log("Sync Complete");
            $this->log("========================================");
            $this->log("Total domains: " . $this->stats['total']);
            $this->log("Updated: " . $this->stats['updated']);
            $this->log("Expired: " . $this->stats['expired']);
            $this->log("Transferred away: " . $this->stats['transferred_away']);
            $this->log("Errors: " . $this->stats['errors']);
            $this->log("Skipped (no changes): " . $this->stats['skipped']);
            $this->log("Duration: " . round($duration, 2) . " seconds");
            $this->log("========================================");

        } catch (Exception $e) {
            $this->log("FATAL ERROR: " . $e->getMessage(), 'ERROR');
            exit(1);
        }
    }

    /**
     * Log message
     */
    private function log($message, $level = 'INFO')
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

        // Write to file
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);

        // Also output to console
        echo $logEntry;
    }
}

// Run the sync
try {
    $sync = new GreppDomainSync();
    $sync->run();
    exit(0);
} catch (Exception $e) {
    echo "FATAL: " . $e->getMessage() . "\n";
    exit(1);
}
