<?php
// SPDX-License-Identifier: LicenseRef-ITCMS-Free-1.0
// ITCMS.GR Free License – All Rights Reserved
// Copyright (c) 2025 Antonios Voulvoulis
// Free to use (including commercial use), but redistribution,
// resale, modification, or cloning is strictly prohibited.

/**
 * grEPP Configuration Example
 *
 * Copy this file to config.php and update with your credentials
 * DO NOT commit config.php to version control!
 */

return [
    // Registry Credentials
    'registrar_id' => '123', // Your numeric registrar ID from ICS FORTH
    'epp_username' => 'your_username', // EPP API username
    'epp_password' => 'your_production_password', // Production password
    'epp_password_uat' => 'your_sandbox_password', // Optional: Sandbox/UAT password

    // Environment
    'use_sandbox' => false, // true = UAT/Sandbox, false = Production

    // Default Contacts
    'default_contact_email' => 'support@example.gr',
    'default_contact_name' => 'Support Team',
    'default_contact_org' => 'Your Company Name',

    // Supported TLDs
    'supported_tlds' => ['gr', 'ελ'],

    // Logging
    'debug_mode' => false, // Enable detailed XML logging
    'log_directory' => __DIR__ . '/logs',

    // Sync Settings
    'sync_enabled' => true,
    'sync_batch_size' => 100, // Number of domains to sync per batch
    'sync_delay_ms' => 250, // Delay between requests (milliseconds)
];
