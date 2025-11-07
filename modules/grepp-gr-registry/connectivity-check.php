<?php
// SPDX-License-Identifier: LicenseRef-ITCMS-Free-1.0
// ITCMS.GR Free License – All Rights Reserved
// Copyright (c) 2025 Antonios Voulvoulis
// Free to use (including commercial use), but redistribution,
// resale, modification, or cloning is strictly prohibited.
//
// Purpose: Diagnostic tool to verify API connectivity with .GR registry
//
// meta:name=grepp-connectivity-check
// meta:type=diagnostic
// meta:header=grEPP Connectivity Checker
// meta:version=1.0.0
// meta:owner="Antonios Voulvoulis <contact@itcms.gr>"
// meta:homepage=https://itcms.gr/
//
// meta:description=Verifies EPP API connectivity to the .GR registry
// meta:usage=php connectivity-check.php [--production|--sandbox]
// meta:created_date=2025-10-25
// meta:updated_date=2025-10-29

require_once __DIR__ . '/lib/GrEppClient.php';

use ITCMS\ClientExec\GR\GrEppClient;

// Parse command line arguments
$production = false;
if (isset($argv[1])) {
    if ($argv[1] === '--production') {
        $production = true;
    } elseif ($argv[1] === '--sandbox') {
        $production = false;
    } else {
        echo "Usage: php connectivity-check.php [--production|--sandbox]\n";
        exit(1);
    }
}

// Configuration - Update these values
$config = [
    'registrarId' => getenv('GR_REGISTRAR_ID') ?: 'YOUR_REGISTRAR_ID',
    'username' => getenv('GR_EPP_USERNAME') ?: 'YOUR_USERNAME',
    'password' => getenv('GR_EPP_PASSWORD') ?: 'YOUR_PASSWORD',
    'production' => $production,
    'logFile' => __DIR__ . '/logs/connectivity-check.log'
];

echo "========================================\n";
echo "  grEPP Connectivity Check Tool\n";
echo "========================================\n";
echo "Environment: " . ($production ? "PRODUCTION" : "SANDBOX/UAT") . "\n";
echo "Registrar ID: " . $config['registrarId'] . "\n";
echo "Username: " . $config['username'] . "\n";
echo "----------------------------------------\n\n";

// Initialize variables for test results
$tests = [];
$overallSuccess = true;

// Test 1: SSL Certificate
echo "[1/5] Checking SSL certificate... ";
$certPath = __DIR__ . '/lib/certificates/regepp_chain.pem';
if (file_exists($certPath) && is_readable($certPath)) {
    $tests['certificate'] = ['status' => 'PASS', 'message' => 'Certificate file found and readable'];
    echo "✓ PASS\n";
} else {
    $tests['certificate'] = ['status' => 'FAIL', 'message' => 'Certificate file not found or not readable'];
    echo "✗ FAIL\n";
    $overallSuccess = false;
}

// Test 2: Network connectivity to EPP endpoint
echo "[2/5] Testing network connectivity... ";
$eppUrl = $production
    ? 'https://regepp.ics.forth.gr:700/epp/proxy'
    : 'https://uat-regepp.ics.forth.gr:700/epp/proxy';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $eppUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_NOBODY => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0
]);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$connectTime = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
$error = curl_error($ch);
curl_close($ch);

if ($httpCode > 0) {
    $tests['network'] = [
        'status' => 'PASS',
        'message' => sprintf('Connected (HTTP %d, %.2fs)', $httpCode, $connectTime)
    ];
    echo "✓ PASS (" . round($connectTime * 1000) . "ms)\n";
} else {
    $tests['network'] = ['status' => 'FAIL', 'message' => 'Connection failed: ' . $error];
    echo "✗ FAIL - " . $error . "\n";
    $overallSuccess = false;
}

// Test 3: EPP Client initialization
echo "[3/5] Initializing EPP client... ";
try {
    $client = new GrEppClient($config);
    $tests['client_init'] = ['status' => 'PASS', 'message' => 'Client initialized successfully'];
    echo "✓ PASS\n";
} catch (Exception $e) {
    $tests['client_init'] = ['status' => 'FAIL', 'message' => 'Initialization failed: ' . $e->getMessage()];
    echo "✗ FAIL - " . $e->getMessage() . "\n";
    $overallSuccess = false;
    exit(1);
}

// Test 4: EPP Login
echo "[4/5] Testing EPP login... ";
$startTime = microtime(true);
$loginResult = $client->exec('login');
$loginTime = microtime(true) - $startTime;

if ($loginResult['success']) {
    $tests['login'] = [
        'status' => 'PASS',
        'message' => sprintf('Login successful (%.2fs)', $loginTime),
        'code' => $loginResult['code']
    ];
    echo "✓ PASS (" . round($loginTime * 1000) . "ms)\n";
} else {
    $tests['login'] = [
        'status' => 'FAIL',
        'message' => 'Login failed: ' . ($loginResult['error']['msg'] ?? 'Unknown error'),
        'code' => $loginResult['code'] ?? 0
    ];
    echo "✗ FAIL - " . ($loginResult['error']['msg'] ?? 'Unknown error') . "\n";
    echo "   Error code: " . ($loginResult['code'] ?? 'N/A') . "\n";
    $overallSuccess = false;
}

// Test 5: Test domain check command
echo "[5/5] Testing domain check command... ";
$testDomain = 'test-' . time() . '.gr';
$startTime = microtime(true);
$checkResult = $client->exec('domain-check', ['domains' => [$testDomain]]);
$checkTime = microtime(true) - $startTime;

if ($checkResult['success']) {
    $available = $checkResult['data']['domains'][0]['available'] ?? false;
    $tests['domain_check'] = [
        'status' => 'PASS',
        'message' => sprintf('Domain check successful (%.2fs, available: %s)', $checkTime, $available ? 'yes' : 'no'),
        'test_domain' => $testDomain
    ];
    echo "✓ PASS (" . round($checkTime * 1000) . "ms)\n";
} else {
    $tests['domain_check'] = [
        'status' => 'FAIL',
        'message' => 'Domain check failed: ' . ($checkResult['error']['msg'] ?? 'Unknown error')
    ];
    echo "✗ FAIL - " . ($checkResult['error']['msg'] ?? 'Unknown error') . "\n";
    $overallSuccess = false;
}

// Print summary
echo "\n========================================\n";
echo "  Test Summary\n";
echo "========================================\n";

foreach ($tests as $name => $result) {
    $status = str_pad($result['status'], 6);
    $statusIcon = $result['status'] === 'PASS' ? '✓' : '✗';
    echo sprintf("%-20s %s %s\n", ucwords(str_replace('_', ' ', $name)), $statusIcon, $status);
    if (isset($result['message'])) {
        echo "  " . $result['message'] . "\n";
    }
}

echo "========================================\n";
if ($overallSuccess) {
    echo "✓ All tests passed! Connection is healthy.\n";
    exit(0);
} else {
    echo "✗ Some tests failed. Please check the errors above.\n";
    exit(1);
}
