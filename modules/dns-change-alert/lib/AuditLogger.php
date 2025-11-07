<?php
// SPDX-License-Identifier: LicenseRef-ITCMS-Free-1.0
// ITCMS.GR Free License – All Rights Reserved
// Copyright (c) 2025 Antonios Voulvoulis
// Free to use (including commercial use), but redistribution,
// resale, modification, or cloning is strictly prohibited.
//
// Purpose: NIS2-compliant audit logging for DNS changes
//
// meta:name=audit-logger
// meta:type=library
// meta:version=1.0.0
// meta:owner="Antonios Voulvoulis <contact@itcms.gr>"
// meta:homepage=https://itcms.gr/

namespace DnsAlert;

use PDO;
use Exception;

/**
 * AuditLogger - NIS2 Compliance Audit Trail
 *
 * Features:
 * - Immutable audit logs with 2-year retention
 * - Detailed change tracking
 * - Actor and IP address logging
 * - Compliance reporting
 */
class AuditLogger
{
    protected $config;
    protected $db;

    public function __construct($config)
    {
        $this->config = $config;
        $this->db = $this->getDbConnection();
    }

    /**
     * Log DNS change event
     *
     * @param array $data {
     *     @type int $domain_id Domain ID
     *     @type string $domain Domain name
     *     @type string $type Change type: pre_change|post_change|unexpected_change
     *     @type array $old_records Old DNS records
     *     @type array $new_records New DNS records
     *     @type array $changes Detected changes
     *     @type bool $notification_sent Whether notification was sent
     *     @type string $owner_email Domain owner email
     *     @type int $user_id Domain owner user ID
     *     @type int $actor_user_id Who initiated the change
     *     @type string $status Status: PENDING|APPLIED|FAILED
     * }
     * @return int|false Audit ID or false on failure
     */
    public function logDnsChange($data)
    {
        try {
            // Determine change type
            $changeType = 'MANUAL'; // Default
            if (isset($data['change_source'])) {
                $changeType = $data['change_source'];
            } elseif (isset($data['type']) && $data['type'] === 'unexpected_change') {
                $changeType = 'CRON_DETECTED';
            }

            // Get client IP and user agent
            $ipAddress = $this->getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI/Cron';

            $stmt = $this->db->prepare("
                INSERT INTO dns_change_audit (
                    domain_id,
                    domain_name,
                    user_id,
                    actor_user_id,
                    action,
                    change_type,
                    old_zone,
                    new_zone,
                    status,
                    pre_notice_sent,
                    post_notice_sent,
                    ip_address,
                    user_agent
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $result = $stmt->execute([
                $data['domain_id'] ?? null,
                $data['domain'] ?? '',
                $data['user_id'] ?? null,
                $data['actor_user_id'] ?? null,
                $data['action'] ?? 'UPDATE_ZONE',
                $changeType,
                json_encode($data['old_records'] ?? []),
                json_encode($data['new_records'] ?? []),
                $data['status'] ?? 'PENDING',
                ($data['type'] === 'pre_change' && $data['notification_sent']) ? 1 : 0,
                ($data['type'] === 'post_change' && $data['notification_sent']) ? 1 : 0,
                $ipAddress,
                $userAgent
            ]);

            $auditId = $this->db->lastInsertId();

            $this->log("Audit logged: ID={$auditId}, Domain={$data['domain']}, Type={$changeType}");

            return $auditId;
        } catch (Exception $e) {
            $this->logError("Failed to create audit log: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update audit status
     */
    public function updateAuditStatus($auditId, $status, $newRecords = null, $errorMessage = null)
    {
        try {
            $updates = ['status' => $status];

            if ($newRecords !== null) {
                $updates['new_zone'] = json_encode($newRecords);
            }

            if ($errorMessage !== null) {
                $updates['error_message'] = $errorMessage;
            }

            $setClause = [];
            $values = [];
            foreach ($updates as $key => $value) {
                $setClause[] = "{$key} = ?";
                $values[] = $value;
            }
            $values[] = $auditId;

            $stmt = $this->db->prepare("
                UPDATE dns_change_audit
                SET " . implode(', ', $setClause) . ", updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute($values);

            $this->log("Audit updated: ID={$auditId}, Status={$status}");
            return true;
        } catch (Exception $e) {
            $this->logError("Failed to update audit: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark pre-change notification sent
     */
    public function markPreNoticeSent($auditId)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE dns_change_audit
                SET pre_notice_sent = 1, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$auditId]);
            return true;
        } catch (Exception $e) {
            $this->logError("Failed to mark pre-notice sent: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark post-change notification sent
     */
    public function markPostNoticeSent($auditId)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE dns_change_audit
                SET post_notice_sent = 1, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$auditId]);
            return true;
        } catch (Exception $e) {
            $this->logError("Failed to mark post-notice sent: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get audit trail for domain
     */
    public function getDomainAuditTrail($domainId, $limit = 100)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM dns_change_audit
                WHERE domain_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$domainId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logError("Failed to fetch audit trail: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get latest audit for domain
     */
    public function getLatestAudit($domainId, $action = 'UPDATE_ZONE')
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM dns_change_audit
                WHERE domain_id = ? AND action = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$domainId, $action]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logError("Failed to fetch latest audit: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate NIS2 compliance report
     */
    public function generateComplianceReport($startDate, $endDate)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    DATE(created_at) as date,
                    change_type,
                    status,
                    COUNT(*) as total_changes,
                    SUM(pre_notice_sent) as pre_notices,
                    SUM(post_notice_sent) as post_notices,
                    COUNT(DISTINCT domain_id) as affected_domains
                FROM dns_change_audit
                WHERE created_at BETWEEN ? AND ?
                GROUP BY DATE(created_at), change_type, status
                ORDER BY date DESC, change_type
            ");
            $stmt->execute([$startDate, $endDate]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logError("Failed to generate compliance report: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get failed changes requiring attention
     */
    public function getFailedChanges($days = 7)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM dns_change_audit
                WHERE status = 'FAILED'
                  AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY created_at DESC
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logError("Failed to fetch failed changes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get changes without notifications
     */
    public function getChangesWithoutNotifications($days = 7)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM dns_change_audit
                WHERE (pre_notice_sent = 0 OR post_notice_sent = 0)
                  AND status = 'APPLIED'
                  AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY created_at DESC
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logError("Failed to fetch changes without notifications: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clean up old audit logs (respecting retention period)
     */
    public function cleanupOldLogs()
    {
        try {
            $retentionDays = $this->config['compliance']['audit_retention_days'] ?? 730;

            $stmt = $this->db->prepare("
                DELETE FROM dns_change_audit
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$retentionDays]);

            $deletedRows = $stmt->rowCount();
            $this->log("Cleaned up {$deletedRows} old audit logs (retention: {$retentionDays} days)");

            return $deletedRows;
        } catch (Exception $e) {
            $this->logError("Failed to cleanup old logs: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Export audit logs to CSV for compliance
     */
    public function exportToCSV($startDate, $endDate, $outputFile = null)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM dns_change_audit
                WHERE created_at BETWEEN ? AND ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$startDate, $endDate]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($records)) {
                return false;
            }

            $csv = fopen($outputFile ?? 'php://output', 'w');

            // Header
            fputcsv($csv, array_keys($records[0]));

            // Data
            foreach ($records as $record) {
                fputcsv($csv, $record);
            }

            fclose($csv);
            return true;
        } catch (Exception $e) {
            $this->logError("Failed to export audit logs: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get client IP address (handles proxies)
     */
    protected function getClientIp()
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',    // Cloudflare
            'HTTP_X_FORWARDED_FOR',     // Standard proxy header
            'HTTP_X_REAL_IP',           // Nginx proxy
            'REMOTE_ADDR'               // Direct connection
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // Handle comma-separated IPs (X-Forwarded-For)
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }

        return '0.0.0.0';
    }

    /**
     * Get database connection
     */
    protected function getDbConnection()
    {
        global $db;
        if (isset($db)) {
            return $db;
        }

        // Fallback
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s",
            $this->config['database']['host'] ?? 'localhost',
            $this->config['database']['name'] ?? 'clientexec'
        );

        return new PDO(
            $dsn,
            $this->config['database']['user'] ?? 'root',
            $this->config['database']['pass'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    /**
     * Log message
     */
    protected function log($message)
    {
        $logFile = __DIR__ . '/../logs/audit.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        @file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Log error
     */
    protected function logError($message)
    {
        $this->log("ERROR: {$message}");
    }
}
