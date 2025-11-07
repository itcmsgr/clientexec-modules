<?php
// SPDX-License-Identifier: LicenseRef-ITCMS-Free-1.0
// ITCMS.GR Free License – All Rights Reserved
// Copyright (c) 2025 Antonios Voulvoulis
// Free to use (including commercial use), but redistribution,
// resale, modification, or cloning is strictly prohibited.
//
// Purpose: DNS monitoring engine for detecting changes
//
// meta:name=dns-monitor
// meta:type=library
// meta:version=1.0.0
// meta:owner="Antonios Voulvoulis <contact@itcms.gr>"
// meta:homepage=https://itcms.gr/

namespace DnsAlert;

class DnsMonitor
{
    protected $config;
    protected $digCommand;
    protected $db;

    public function __construct($config)
    {
        $this->config = $config;
        $this->digCommand = $config['monitor']['dig_command'] ?? '/usr/bin/dig';
        $this->db = $this->getDbConnection();
    }

    /**
     * Check domain for DNS changes
     */
    public function checkDomain($domain)
    {
        // Fetch current DNS records
        $currentRecords = $this->fetchDnsRecords($domain);

        // Get stored snapshot
        $previousRecords = $this->getSnapshot($domain);

        if (!$previousRecords) {
            // First time checking - create snapshot
            $this->saveSnapshot($domain, $currentRecords);
            return ['changed' => false];
        }

        // Compare records
        $changes = $this->compareRecords($previousRecords, $currentRecords);

        if (!empty($changes)) {
            // Update snapshot
            $this->saveSnapshot($domain, $currentRecords);

            return [
                'changed' => true,
                'changes' => $changes,
                'old_records' => $previousRecords,
                'new_records' => $currentRecords,
            ];
        }

        return ['changed' => false];
    }

    /**
     * Fetch current DNS records using dig
     */
    public function fetchDnsRecords($domain)
    {
        $records = [];
        $types = $this->config['monitor']['record_types'] ?? ['A', 'MX', 'NS'];

        foreach ($types as $type) {
            $command = sprintf(
                '%s +short %s %s 2>&1',
                escapeshellcmd($this->digCommand),
                escapeshellarg($type),
                escapeshellarg($domain)
            );

            $output = shell_exec($command);

            if ($output && trim($output) !== '') {
                $lines = array_filter(explode("\n", trim($output)));
                $records[$type] = $lines;
            }
        }

        return $records;
    }

    /**
     * Compare two sets of DNS records
     */
    public function compareRecords($oldRecords, $newRecords)
    {
        $changes = [];

        // Check all record types
        $allTypes = array_unique(array_merge(array_keys($oldRecords), array_keys($newRecords)));

        foreach ($allTypes as $type) {
            $old = $oldRecords[$type] ?? [];
            $new = $newRecords[$type] ?? [];

            // Normalize arrays
            sort($old);
            sort($new);

            if ($old !== $new) {
                $changes[] = [
                    'type' => $type,
                    'old_value' => implode(', ', $old),
                    'new_value' => implode(', ', $new),
                    'detected_at' => date('Y-m-d H:i:s'),
                ];
            }
        }

        return $changes;
    }

    /**
     * Get stored DNS snapshot
     */
    protected function getSnapshot($domain)
    {
        $stmt = $this->db->prepare(
            "SELECT records FROM dns_alert_snapshots WHERE domain = ? ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([$domain]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? json_decode($result['records'], true) : null;
    }

    /**
     * Save DNS snapshot
     */
    protected function saveSnapshot($domain, $records)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO dns_alert_snapshots (domain, records, created_at) VALUES (?, ?, NOW())"
        );
        $stmt->execute([$domain, json_encode($records)]);
    }

    /**
     * Get database connection
     */
    protected function getDbConnection()
    {
        // Use ClientExec's DB or create new connection
        global $db;
        if (isset($db)) {
            return $db;
        }

        // Fallback: create new connection
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s",
            $this->config['database']['host'] ?? 'localhost',
            $this->config['database']['name'] ?? 'clientexec'
        );

        return new \PDO(
            $dsn,
            $this->config['database']['user'] ?? 'root',
            $this->config['database']['pass'] ?? '',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }
}
