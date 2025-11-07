<?php
// SPDX-License-Identifier: LicenseRef-ITCMS-Free-1.0
// ITCMS.GR Free License – All Rights Reserved
// Copyright (c) 2025 Antonios Voulvoulis
// Free to use (including commercial use), but redistribution,
// resale, modification, or cloning is strictly prohibited.
//
// Purpose: Multi-channel notification manager with queue and retry system
//
// meta:name=notification-manager
// meta:type=library
// meta:version=1.0.0
// meta:owner="Antonios Voulvoulis <contact@itcms.gr>"
// meta:homepage=https://itcms.gr/

namespace DnsAlert;

use PDO;
use Exception;

/**
 * NotificationManager - Queue-based notification system with retry logic
 *
 * Features:
 * - Multi-channel delivery (Email, SMS, Webhooks)
 * - Automatic retry with exponential backoff
 * - Template-based email rendering
 * - NIS2 compliance tracking
 */
class NotificationManager
{
    protected $config;
    protected $db;
    protected $templatesDir;
    protected $mailer;

    public function __construct($config)
    {
        $this->config = $config;
        $this->db = $this->getDbConnection();
        $this->templatesDir = __DIR__ . '/../templates/email/';
    }

    /**
     * Send pre-change alert and queue for delivery
     */
    public function sendPreChangeAlert($data)
    {
        return $this->queueNotification(
            $data['audit_id'] ?? null,
            'PRE',
            $data['owner_email'],
            'EMAIL',
            $this->buildPreChangePayload($data)
        );
    }

    /**
     * Send post-change confirmation and queue for delivery
     */
    public function sendPostChangeConfirmation($data)
    {
        return $this->queueNotification(
            $data['audit_id'] ?? null,
            'POST',
            $data['owner_email'],
            'EMAIL',
            $this->buildPostChangePayload($data)
        );
    }

    /**
     * Send unexpected change alert
     */
    public function sendUnexpectedChangeAlert($data)
    {
        return $this->queueNotification(
            $data['audit_id'] ?? null,
            'UNEXPECTED',
            $data['owner_email'],
            'EMAIL',
            $this->buildUnexpectedChangePayload($data)
        );
    }

    /**
     * Queue notification for delivery
     */
    protected function queueNotification($auditId, $type, $recipient, $channel, $payload)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO dns_notification_queue
                (audit_id, type, channel, recipient, payload, next_attempt_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $auditId,
                $type,
                $channel,
                $recipient,
                json_encode($payload)
            ]);

            $this->log("Queued {$type} notification to {$recipient} via {$channel}");
            return true;
        } catch (Exception $e) {
            $this->logError("Failed to queue notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process notification queue (called by cron)
     */
    public function processQueue($batchSize = 100)
    {
        $items = $this->fetchDueQueueItems($batchSize);
        $processed = 0;
        $delivered = 0;
        $failed = 0;

        foreach ($items as $item) {
            try {
                $success = $this->deliverNotification($item);

                if ($success) {
                    $this->markDelivered($item['id']);
                    $delivered++;
                } else {
                    $this->bumpRetry($item['id'], 'Delivery returned false');
                    $failed++;
                }

                $processed++;
            } catch (Exception $e) {
                $this->bumpRetry($item['id'], $e->getMessage());
                $failed++;
                $processed++;
            }
        }

        $this->log("Queue processed: {$processed} total, {$delivered} delivered, {$failed} failed/retrying");
        return ['processed' => $processed, 'delivered' => $delivered, 'failed' => $failed];
    }

    /**
     * Deliver a single notification
     */
    protected function deliverNotification($item)
    {
        $payload = json_decode($item['payload'], true);

        switch ($item['channel']) {
            case 'EMAIL':
                return $this->sendEmail(
                    $item['recipient'],
                    $payload['subject'],
                    $payload['body'],
                    $payload['html'] ?? false
                );

            case 'SMS':
                return $this->sendSms($item['recipient'], $payload['message']);

            case 'WEBHOOK':
                return $this->sendWebhook($item['recipient'], $payload);

            default:
                throw new Exception("Unknown channel: {$item['channel']}");
        }
    }

    /**
     * Send email notification
     */
    protected function sendEmail($to, $subject, $body, $isHtml = false)
    {
        // Use ClientExec's mailer if available
        global $mail;
        if (isset($mail)) {
            try {
                $from = $this->config['notifications']['from_email'] ?? 'dns-alerts@yourdomain.com';
                $fromName = $this->config['notifications']['from_name'] ?? 'DNS Alert System';

                $mail->setFrom($from, $fromName);
                $mail->addAddress($to);
                $mail->Subject = $subject;

                if ($isHtml) {
                    $mail->isHTML(true);
                    $mail->Body = $body;
                } else {
                    $mail->isHTML(false);
                    $mail->Body = $body;
                }

                return $mail->send();
            } catch (Exception $e) {
                $this->logError("Email send failed: " . $e->getMessage());
                return false;
            }
        }

        // Fallback to PHP mail()
        $headers = "From: " . ($this->config['notifications']['from_email'] ?? 'dns-alerts@yourdomain.com') . "\r\n";
        if ($isHtml) {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        }

        return mail($to, $subject, $body, $headers);
    }

    /**
     * Send SMS notification (placeholder - implement with your SMS provider)
     */
    protected function sendSms($phone, $message)
    {
        // TODO: Implement with SMS provider (Twilio, Vonage, etc.)
        $this->log("SMS sending not yet implemented: {$phone}");
        return false;
    }

    /**
     * Send webhook notification
     */
    protected function sendWebhook($url, $payload)
    {
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'User-Agent: ITCMS-DNS-Alert/1.0'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode >= 200 && $httpCode < 300;
        } catch (Exception $e) {
            $this->logError("Webhook send failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Build pre-change notification payload
     */
    protected function buildPreChangePayload($data)
    {
        $subject = sprintf(
            '[ACTION REQUIRED] DNS change pending for %s',
            $data['domain']
        );

        $templateData = [
            'domain' => $data['domain'],
            'owner_email' => $data['owner_email'],
            'changes' => $data['changes'],
            'initiated_by' => $data['initiated_by'] ?? 'System',
            'scheduled_time' => $data['scheduled_time'] ?? date('Y-m-d H:i:s'),
            'cancel_url' => $data['cancel_url'] ?? '#',
        ];

        $body = $this->renderTemplate('pre-change', $templateData);

        return [
            'subject' => $subject,
            'body' => $body,
            'html' => true,
            'type' => 'pre_change'
        ];
    }

    /**
     * Build post-change notification payload
     */
    protected function buildPostChangePayload($data)
    {
        $subject = sprintf(
            '[COMPLETED] DNS change applied for %s',
            $data['domain']
        );

        $templateData = [
            'domain' => $data['domain'],
            'owner_email' => $data['owner_email'],
            'changes' => $data['changes'],
            'initiated_by' => $data['initiated_by'] ?? 'System',
            'applied_time' => $data['applied_time'] ?? date('Y-m-d H:i:s'),
            'verify_url' => $data['verify_url'] ?? '#',
        ];

        $body = $this->renderTemplate('post-change', $templateData);

        return [
            'subject' => $subject,
            'body' => $body,
            'html' => true,
            'type' => 'post_change'
        ];
    }

    /**
     * Build unexpected change notification payload
     */
    protected function buildUnexpectedChangePayload($data)
    {
        $subject = sprintf(
            '[SECURITY ALERT] Unexpected DNS change detected for %s',
            $data['domain']
        );

        $templateData = [
            'domain' => $data['domain'],
            'owner_email' => $data['owner_email'],
            'changes' => $data['changes'],
            'detected_time' => $data['detected_time'] ?? date('Y-m-d H:i:s'),
        ];

        $body = $this->renderTemplate('unexpected', $templateData);

        return [
            'subject' => $subject,
            'body' => $body,
            'html' => true,
            'type' => 'unexpected'
        ];
    }

    /**
     * Render email template
     */
    protected function renderTemplate($templateName, $data)
    {
        $templateFile = $this->templatesDir . $templateName . '.html';

        if (!file_exists($templateFile)) {
            // Fallback to plain text
            return $this->renderPlainTextTemplate($templateName, $data);
        }

        $template = file_get_contents($templateFile);

        // Simple template variable replacement
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Handle changes array
                if ($key === 'changes') {
                    $value = $this->formatChangesHtml($value);
                } else {
                    $value = json_encode($value, JSON_PRETTY_PRINT);
                }
            }
            $template = str_replace('{{' . $key . '}}', htmlspecialchars($value), $template);
        }

        return $template;
    }

    /**
     * Render plain text template (fallback)
     */
    protected function renderPlainTextTemplate($type, $data)
    {
        $text = "DNS Alert: {$data['domain']}\n";
        $text .= str_repeat('=', 50) . "\n\n";

        if ($type === 'pre-change') {
            $text .= "A DNS change has been requested for your domain.\n\n";
            $text .= "Scheduled Time: {$data['scheduled_time']}\n";
            $text .= "Initiated By: {$data['initiated_by']}\n\n";
        } elseif ($type === 'post-change') {
            $text .= "DNS changes have been applied to your domain.\n\n";
            $text .= "Applied Time: {$data['applied_time']}\n";
            $text .= "Initiated By: {$data['initiated_by']}\n\n";
        } else {
            $text .= "⚠️ UNEXPECTED DNS changes detected!\n\n";
            $text .= "Detected Time: {$data['detected_time']}\n\n";
        }

        $text .= "Changes:\n";
        $text .= $this->formatChangesText($data['changes']);
        $text .= "\n\n";
        $text .= "---\n";
        $text .= "ITCMS DNS Alert System\n";
        $text .= "https://itcms.gr\n";

        return $text;
    }

    /**
     * Format changes as HTML table
     */
    protected function formatChangesHtml($changes)
    {
        $html = '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse;">';
        $html .= '<tr><th>Record Type</th><th>Old Value</th><th>New Value</th></tr>';

        foreach ($changes as $change) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($change['type']) . '</td>';
            $html .= '<td>' . htmlspecialchars($change['old_value'] ?? 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($change['new_value'] ?? 'N/A') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';
        return $html;
    }

    /**
     * Format changes as plain text
     */
    protected function formatChangesText($changes)
    {
        $text = '';
        foreach ($changes as $change) {
            $text .= "  - {$change['type']}: ";
            $text .= ($change['old_value'] ?? 'N/A') . ' → ' . ($change['new_value'] ?? 'N/A');
            $text .= "\n";
        }
        return $text;
    }

    /**
     * Fetch due queue items
     */
    protected function fetchDueQueueItems($limit)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM dns_notification_queue
            WHERE next_attempt_at <= NOW()
              AND delivered_at IS NULL
              AND attempt < max_attempts
            ORDER BY next_attempt_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark notification as delivered
     */
    protected function markDelivered($queueId)
    {
        $stmt = $this->db->prepare("
            UPDATE dns_notification_queue
            SET delivered_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$queueId]);
    }

    /**
     * Bump retry attempt with exponential backoff
     */
    protected function bumpRetry($queueId, $error)
    {
        // Get current attempt
        $stmt = $this->db->prepare("SELECT attempt, max_attempts FROM dns_notification_queue WHERE id = ?");
        $stmt->execute([$queueId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            return;
        }

        $newAttempt = $item['attempt'] + 1;

        // Exponential backoff: 5, 15, 30, 60, 120 minutes
        $backoffMinutes = [5, 15, 30, 60, 120];
        $delayMinutes = $backoffMinutes[min($newAttempt - 1, count($backoffMinutes) - 1)];

        if ($newAttempt >= $item['max_attempts']) {
            // Max attempts reached - escalate to admin
            $this->logError("Notification delivery failed after {$newAttempt} attempts: {$error}");
            // TODO: Send admin alert
        }

        $stmt = $this->db->prepare("
            UPDATE dns_notification_queue
            SET attempt = ?,
                last_error = ?,
                next_attempt_at = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$newAttempt, $error, $delayMinutes, $queueId]);
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
        $logFile = __DIR__ . '/../logs/notifications.log';
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
