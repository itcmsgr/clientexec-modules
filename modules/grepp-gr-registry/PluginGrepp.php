<?php
// SPDX-License-Identifier: LicenseRef-ITCMS-Free-1.0
// ITCMS.GR Free License – All Rights Reserved
// Copyright (c) 2025 Antonios Voulvoulis
// Free to use (including commercial use), but redistribution,
// resale, modification, or cloning is strictly prohibited.
//
// Purpose: ClientExec Registrar module for .GR domains
//
// meta:name=clientexec-gr-registry
// meta:type=module
// meta:header=ClientExec .GR Registrar
// meta:version=1.1.0
// meta:owner="Antonios Voulvoulis <contact@itcms.gr>"
// meta:homepage=https://itcms.gr/
//
// meta:description=Handles .gr domain registration, renewal, contact updates, nameserver glue records, DACOR tokens, and syncs domain lifecycle data.
// meta:input=No arguments required; uses ClientExec registrar interface.
// meta:depends=curl, openssl, php8.0+
// meta:requires_env=GR_REGISTRY_API_KEY
// meta:created_date=2025-10-25
// meta:updated_date=2025-10-29

require_once dirname(__FILE__) . '/../../../modules/admin/models/RegistrarPlugin.php';
require_once __DIR__ . '/lib/GrEppClient.php';

use ITCMS\ClientExec\GR\GrEppClient;

/**
 * PluginGrepp - ClientExec Registrar Plugin for .GR domains
 *
 * This plugin provides full domain lifecycle management for Greek (.gr) domains
 * through the ICS FORTH EPP registry interface.
 *
 * Features:
 * - Domain registration, renewal, and transfer
 * - Contact management (Registrant, Admin, Tech, Billing)
 * - WHOIS updates
 * - Nameserver management
 * - Domain info synchronization
 * - Automated cron sync for expiration dates
 */
class PluginGrepp extends RegistrarPlugin
{
    public $features = [
        'nameSuggest' => false,
        'importDomains' => true,
        'importPrices' => false,
    ];

    /**
     * Get plugin variables for configuration
     */
    public function getVariables()
    {
        $variables = [
            lang('Plugin Name') => [
                'type' => 'hidden',
                'description' => lang('How CE sees this plugin (not to be confused with the Signup Name)'),
                'value' => 'grEPP for .GR TLD'
            ],
            lang('Enabled') => [
                'type' => 'yesno',
                'description' => lang('When enabled, this plugin will be available for registering domains.'),
                'value' => '0'
            ],
            lang('Registrar ID') => [
                'type' => 'text',
                'description' => lang('Your numeric registrar ID from ICS FORTH'),
                'value' => ''
            ],
            lang('EPP Username') => [
                'type' => 'text',
                'description' => lang('EPP API Username'),
                'value' => ''
            ],
            lang('EPP Password') => [
                'type' => 'password',
                'description' => lang('EPP API Password for Production'),
                'value' => ''
            ],
            lang('UAT Password') => [
                'type' => 'password',
                'description' => lang('EPP API Password for UAT/Sandbox (leave empty if same as production)'),
                'value' => ''
            ],
            lang('Use Sandbox') => [
                'type' => 'yesno',
                'description' => lang('Enable to use UAT/Sandbox environment for testing'),
                'value' => '0'
            ],
            lang('Default Contact Email') => [
                'type' => 'text',
                'description' => lang('Default email for admin/tech/billing contacts'),
                'value' => ''
            ],
            lang('Default Contact Name') => [
                'type' => 'text',
                'description' => lang('Default name for admin/tech/billing contacts'),
                'value' => ''
            ],
            lang('Supported TLDs') => [
                'type' => 'text',
                'description' => lang('Comma-separated list of supported TLDs'),
                'value' => 'gr,ελ'
            ],
            lang('Debug Mode') => [
                'type' => 'yesno',
                'description' => lang('Enable detailed logging for troubleshooting'),
                'value' => '0'
            ],
            lang('Actions') => [
                'type' => 'hidden',
                'description' => lang('Current actions available to admin'),
                'value' => 'Register,Renew,Transfer'
            ],
            lang('Registered Actions') => [
                'type' => 'hidden',
                'description' => lang('Current actions available for registered domains'),
                'value' => 'Renew'
            ],
            lang('Registered Actions For Customer') => [
                'type' => 'hidden',
                'description' => lang('Current actions available to clients for registered domains'),
                'value' => ''
            ],
        ];

        return $variables;
    }

    /**
     * Get EPP client instance
     */
    private function getClient()
    {
        $useSandbox = $this->settings->get('Use Sandbox') == '1';
        $password = $useSandbox && $this->settings->get('UAT Password')
            ? $this->settings->get('UAT Password')
            : $this->settings->get('EPP Password');

        $config = [
            'registrarId' => $this->settings->get('Registrar ID'),
            'username' => $this->settings->get('EPP Username'),
            'password' => $password,
            'production' => !$useSandbox,
            'logFile' => __DIR__ . '/logs/grepp_' . date('Y-m-d') . '.log'
        ];

        return new GrEppClient($config);
    }

    /**
     * Check domain availability
     */
    public function checkDomain($params)
    {
        $domain = $params['sld'] . '.' . $params['tld'];
        $domain = $this->sanitizeDomain($domain);

        try {
            $client = $this->getClient();
            $result = $client->exec('domain-check', ['domains' => [$domain]]);

            if ($result['success'] && isset($result['data']['domains'][0])) {
                // ClientExec expects: array(0) for available, array(1) or array(1, 'message') for unavailable
                if ($result['data']['domains'][0]['available']) {
                    return array(0);
                } else {
                    return array(1, 'Domain is not available');
                }
            }

            return array(1, $result['error']['msg'] ?? 'Unknown error occurred');
        } catch (Exception $e) {
            return array(1, $e->getMessage());
        }
    }

    /**
     * Register a domain - abstract method implementation
     * Required by ClientExec RegistrarPlugin abstract class
     */
    public function registerDomain($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $this->setup($userPackage);

        $result = $this->doRegister($params);

        if (isset($result['error'])) {
            CE_Lib::log(4, 'Domain registration failed for ' . $params['sld'] . '.' . $params['tld'] . ': ' . $result['error']);
            return $result['error'];
        }

        return 'Domain registered successfully';
    }

    /**
     * Register a domain
     * Required by ClientExec - method name must be doRegister
     */
    public function doRegister($params)
    {
        $domain = $params['sld'] . '.' . $params['tld'];
        $domain = $this->sanitizeDomain($domain);

        try {
            $client = $this->getClient();

            // Create registrant contact
            $registrantId = $this->createContact($client, $params, 'registrant');
            if (isset($registrantId['error'])) {
                return $registrantId;
            }

            // Create admin/tech/billing contacts (can be same as registrant)
            $useDefaultContacts = $this->settings->get('Default Contact Email') != '';
            $contacts = [];

            if ($useDefaultContacts) {
                $defaultContactId = $this->createDefaultContact($client);
                if (isset($defaultContactId['error'])) {
                    return $defaultContactId;
                }

                $contacts = [
                    ['type' => 'admin', 'id' => $defaultContactId],
                    ['type' => 'tech', 'id' => $defaultContactId],
                    ['type' => 'billing', 'id' => $defaultContactId]
                ];
            } else {
                $contacts = [
                    ['type' => 'admin', 'id' => $registrantId],
                    ['type' => 'tech', 'id' => $registrantId],
                    ['type' => 'billing', 'id' => $registrantId]
                ];
            }

            // Prepare nameservers
            $nameservers = [];
            for ($i = 1; $i <= 5; $i++) {
                if (!empty($params["ns{$i}"])) {
                    $nameservers[] = $params["ns{$i}"];
                }
            }

            // Register domain
            $domainParams = [
                'domain' => $domain,
                'years' => $params['NumYears'] ?? 2,
                'registrant' => $registrantId,
                'contacts' => $contacts,
                'nameservers' => $nameservers,
                'password' => GrEppClient::generatePassword()
            ];

            $result = $client->exec('domain-create', $domainParams, 1001);

            if (!$result['success']) {
                return ['error' => $result['error']['msg']];
            }

            return ['success' => true];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Renew a domain
     * Required by ClientExec - method name must be doRenew
     */
    public function doRenew($params)
    {
        $domain = $params['sld'] . '.' . $params['tld'];
        $domain = $this->sanitizeDomain($domain);

        try {
            $client = $this->getClient();

            // Get current expiration date
            $info = $client->exec('domain-info', ['domain' => $domain]);
            if (!$info['success']) {
                return ['error' => $info['error']['msg']];
            }

            $currentExpDate = substr($info['data']['exDate'], 0, 10);

            // Renew domain
            $renewParams = [
                'domain' => $domain,
                'expDate' => $currentExpDate,
                'years' => $params['NumYears'] ?? 2
            ];

            $result = $client->exec('domain-renew', $renewParams);

            if (!$result['success']) {
                return ['error' => $result['error']['msg']];
            }

            return ['success' => true];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Transfer a domain
     * Required by ClientExec - method name must be doTransfer
     */
    public function doTransfer($params)
    {
        $domain = $params['sld'] . '.' . $params['tld'];
        $domain = $this->sanitizeDomain($domain);

        try {
            $client = $this->getClient();

            $transferParams = [
                'domain' => $domain,
                'years' => 2,
                'authCode' => $params['EPPCode'] ?? $params['eppCode'] ?? ''
            ];

            $result = $client->exec('domain-transfer', $transferParams);

            if (!$result['success']) {
                return ['error' => $result['error']['msg']];
            }

            return ['success' => true];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get nameservers for a domain
     */
    public function getNameServers($params)
    {
        $domain = $params['sld'] . '.' . $params['tld'];
        $domain = $this->sanitizeDomain($domain);

        try {
            $client = $this->getClient();
            $result = $client->exec('domain-info', ['domain' => $domain]);

            if (!$result['success']) {
                return ['error' => $result['error']['msg']];
            }

            $nameservers = [
                'usesDefault' => false,
                'hasDefault' => false
            ];

            if (isset($result['data']['nameservers'])) {
                foreach ($result['data']['nameservers'] as $index => $ns) {
                    $nameservers['ns' . ($index + 1)] = $ns;
                }
            }

            return $nameservers;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Set nameservers for a domain
     */
    public function setNameServers($params)
    {
        $domain = $params['sld'] . '.' . $params['tld'];
        $domain = $this->sanitizeDomain($domain);

        try {
            $client = $this->getClient();

            // Get current nameservers
            $info = $client->exec('domain-info', ['domain' => $domain]);
            if (!$info['success']) {
                return ['error' => $info['error']['msg']];
            }

            $currentNS = $info['data']['nameservers'] ?? [];

            // Remove old nameservers
            if (!empty($currentNS)) {
                $removeParams = [
                    'domain' => $domain,
                    'rem' => ['nameservers' => $currentNS]
                ];
                $client->exec('domain-update', $removeParams);
            }

            // Add new nameservers
            $newNS = [];
            for ($i = 1; $i <= 5; $i++) {
                if (!empty($params["ns{$i}"])) {
                    $newNS[] = $params["ns{$i}"];
                }
            }

            if (!empty($newNS)) {
                $addParams = [
                    'domain' => $domain,
                    'add' => ['nameservers' => $newNS]
                ];
                $result = $client->exec('domain-update', $addParams);

                if (!$result['success']) {
                    return ['error' => $result['error']['msg']];
                }
            }

            return ['success' => true];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get domain information
     */
    public function getDomainInformation($params)
    {
        $domain = $params['sld'] . '.' . $params['tld'];
        $domain = $this->sanitizeDomain($domain);

        try {
            $client = $this->getClient();
            $result = $client->exec('domain-info', ['domain' => $domain]);

            if (!$result['success']) {
                return ['error' => $result['error']['msg']];
            }

            $data = $result['data'];

            return [
                'domain' => $data['domain'],
                'registrant' => $data['registrant'] ?? '',
                'created' => $data['crDate'] ?? '',
                'expires' => $data['exDate'] ?? '',
                'updated' => $data['upDate'] ?? '',
                'status' => implode(', ', $data['status'] ?? []),
                'nameservers' => $data['nameservers'] ?? [],
                'contacts' => $data['contacts'] ?? []
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get contact details for domain
     */
    public function getContactInformation($params)
    {
        $domain = $params['sld'] . '.' . $params['tld'];
        $domain = $this->sanitizeDomain($domain);

        try {
            $client = $this->getClient();

            // Get domain info
            $domainInfo = $client->exec('domain-info', ['domain' => $domain]);
            if (!$domainInfo['success']) {
                return ['error' => $domainInfo['error']['msg']];
            }

            $contacts = [];

            // Get registrant contact
            if (isset($domainInfo['data']['registrant'])) {
                $contactResult = $client->exec('contact-info', [
                    'contactId' => $domainInfo['data']['registrant']
                ]);

                if ($contactResult['success']) {
                    $contacts['Registrant'] = $this->formatContactData($contactResult['data']);
                }
            }

            // Get admin/tech/billing contacts
            foreach (['admin', 'tech', 'billing'] as $type) {
                if (isset($domainInfo['data']['contacts'][$type])) {
                    $contactResult = $client->exec('contact-info', [
                        'contactId' => $domainInfo['data']['contacts'][$type]
                    ]);

                    if ($contactResult['success']) {
                        $contacts[ucfirst($type)] = $this->formatContactData($contactResult['data']);
                    }
                }
            }

            return $contacts;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Update contact information
     */
    public function setContactInformation($params)
    {
        $domain = $params['sld'] . '.' . $params['tld'];
        $domain = $this->sanitizeDomain($domain);

        try {
            $client = $this->getClient();

            // Get domain info
            $domainInfo = $client->exec('domain-info', ['domain' => $domain]);
            if (!$domainInfo['success']) {
                return ['error' => $domainInfo['error']['msg']];
            }

            // Update registrant if provided
            if (isset($params['Registrant'])) {
                $contactId = $domainInfo['data']['registrant'];
                $updateParams = [
                    'contactId' => $contactId,
                    'chg' => [
                        'name' => $params['Registrant']['Name'] ?? '',
                        'org' => $params['Registrant']['Organization'] ?? '',
                        'email' => $params['Registrant']['EmailAddress'] ?? '',
                        'voice' => $params['Registrant']['Phone'] ?? '',
                        'addr' => [
                            'street' => array_filter([
                                $params['Registrant']['Address1'] ?? '',
                                $params['Registrant']['Address2'] ?? '',
                                $params['Registrant']['Address3'] ?? ''
                            ]),
                            'city' => $params['Registrant']['City'] ?? '',
                            'sp' => $params['Registrant']['StateProvince'] ?? '',
                            'pc' => $params['Registrant']['PostalCode'] ?? '',
                            'cc' => strtolower($params['Registrant']['Country'] ?? 'gr')
                        ]
                    ]
                ];

                $result = $client->exec('contact-update', $updateParams);
                if (!$result['success']) {
                    return ['error' => $result['error']['msg']];
                }
            }

            return ['success' => true];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get EPP/Auth code for transfer
     * Uses DACOR token system (EPP 4.3+)
     */
    public function getEPPCode($params)
    {
        $domain = $params['sld'] . '.' . $params['tld'];
        $domain = $this->sanitizeDomain($domain);

        try {
            $client = $this->getClient();

            // Use DACOR issue-token command (EPP 4.3+)
            $result = $client->exec('dacor-issue-token', ['domain' => $domain]);

            if (!$result['success']) {
                return ['error' => $result['error']['msg']];
            }

            // Extract DACOR token from response
            if (isset($result['data']['dacor_token']) && !empty($result['data']['dacor_token'])) {
                return ['eppcode' => $result['data']['dacor_token']];
            }

            // Fallback error if no token was returned
            return ['error' => 'Failed to retrieve transfer token from registry'];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Register nameserver (glue record)
     */
    public function registerNameserver($params)
    {
        $nameserver = $params['nameserver'];

        try {
            $client = $this->getClient();

            // Check if nameserver already exists
            $checkResult = $client->exec('host-check', ['hosts' => [$nameserver]]);
            if ($checkResult['success'] && isset($checkResult['data']['hosts'][0])) {
                if (!$checkResult['data']['hosts'][0]['available']) {
                    return ['error' => 'Nameserver already exists'];
                }
            }

            // Create nameserver with IP addresses
            $hostParams = [
                'host' => $nameserver,
                'ipv4' => [],
                'ipv6' => []
            ];

            // Add IPv4 addresses
            if (!empty($params['ipaddress'])) {
                $hostParams['ipv4'][] = $params['ipaddress'];
            }

            // Add IPv6 addresses if provided
            if (!empty($params['ipv6'])) {
                $hostParams['ipv6'][] = $params['ipv6'];
            }

            // At least one IP address is required
            if (empty($hostParams['ipv4']) && empty($hostParams['ipv6'])) {
                return ['error' => 'At least one IP address (IPv4 or IPv6) is required'];
            }

            $result = $client->exec('host-create', $hostParams, 1000);

            if (!$result['success']) {
                return ['error' => $result['error']['msg']];
            }

            return ['success' => true];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Modify nameserver IP addresses
     */
    public function modifyNameserver($params)
    {
        $nameserver = $params['nameserver'];

        try {
            $client = $this->getClient();

            // Get current nameserver info
            $info = $client->exec('host-info', ['host' => $nameserver]);
            if (!$info['success']) {
                return ['error' => $info['error']['msg']];
            }

            $currentIPs = $info['data']['addresses'] ?? [];

            // Prepare update parameters
            $updateParams = [
                'host' => $nameserver,
                'rem' => ['ipv4' => [], 'ipv6' => []],
                'add' => ['ipv4' => [], 'ipv6' => []]
            ];

            // Remove old IPs
            foreach ($currentIPs as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $updateParams['rem']['ipv4'][] = $ip;
                } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $updateParams['rem']['ipv6'][] = $ip;
                }
            }

            // Add new IPs
            if (!empty($params['ipaddress'])) {
                $updateParams['add']['ipv4'][] = $params['ipaddress'];
            }

            if (!empty($params['ipv6'])) {
                $updateParams['add']['ipv6'][] = $params['ipv6'];
            }

            // At least one IP address is required
            if (empty($updateParams['add']['ipv4']) && empty($updateParams['add']['ipv6'])) {
                return ['error' => 'At least one IP address (IPv4 or IPv6) is required'];
            }

            $result = $client->exec('host-update', $updateParams);

            if (!$result['success']) {
                return ['error' => $result['error']['msg']];
            }

            return ['success' => true];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Delete nameserver
     */
    public function deleteNameserver($params)
    {
        $nameserver = $params['nameserver'];

        try {
            $client = $this->getClient();

            $result = $client->exec('host-delete', ['host' => $nameserver]);

            if (!$result['success']) {
                return ['error' => $result['error']['msg']];
            }

            return ['success' => true];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Request domain deletion
     */
    public function requestDelete($params)
    {
        $domain = $params['sld'] . '.' . $params['tld'];
        $domain = $this->sanitizeDomain($domain);

        try {
            $client = $this->getClient();

            $result = $client->exec('domain-delete', ['domain' => $domain]);

            if (!$result['success']) {
                return ['error' => $result['error']['msg']];
            }

            return ['success' => true];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Recall a domain application (.GR specific)
     * Can only be used within 5 days of domain registration
     *
     * @param array $params Domain parameters
     * @return array Result array with success or error
     */
    public function recallApplication($params)
    {
        $domain = $params['sld'] . '.' . $params['tld'];
        $domain = $this->sanitizeDomain($domain);

        try {
            $client = $this->getClient();

            // Get domain info to retrieve protocol ID
            $domainInfo = $client->exec('domain-info', ['domain' => $domain]);
            if (!$domainInfo['success']) {
                return ['error' => $domainInfo['error']['msg']];
            }

            // Check if protocol is available
            if (empty($domainInfo['data']['protocol'])) {
                return ['error' => 'Domain protocol ID not found. This operation may only be available within 5 days of registration.'];
            }

            // Execute recall application
            $recallParams = [
                'domain' => $domain,
                'protocol' => $domainInfo['data']['protocol']
            ];

            $result = $client->exec('domain-recall-application', $recallParams);

            if (!$result['success']) {
                return ['error' => $result['error']['msg']];
            }

            return ['success' => true];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Sync domain status
     */
    public function syncDomain($params)
    {
        $domain = $params['sld'] . '.' . $params['tld'];
        $domain = $this->sanitizeDomain($domain);

        try {
            $client = $this->getClient();
            $result = $client->exec('domain-info', ['domain' => $domain]);

            if (!$result['success']) {
                if ($result['error']['code'] == 2303) {
                    // Domain not found - expired
                    return ['expired' => true];
                } elseif ($result['error']['code'] == 2201) {
                    // Transferred away
                    return ['transferredAway' => true];
                }
                return ['error' => $result['error']['msg']];
            }

            $data = $result['data'];
            $expiryDate = substr($data['exDate'], 0, 10);

            // Check if domain is in pending delete
            if (isset($data['status']) && in_array('pendingDelete', $data['status'])) {
                return [
                    'expirydate' => $expiryDate,
                    'expired' => true
                ];
            }

            return [
                'expirydate' => $expiryDate,
                'active' => true
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Test API connectivity
     */
    public function testConnection($params = [])
    {
        try {
            $client = $this->getClient();

            // Try to login
            $result = $client->exec('login');

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'Successfully connected to .GR registry'
                ];
            }

            return [
                'success' => false,
                'message' => 'Connection failed: ' . ($result['error']['msg'] ?? 'Unknown error')
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }

    // ========== Helper Methods ==========

    /**
     * Create contact from params
     */
    private function createContact($client, $params, $type = 'registrant')
    {
        $registrarId = $this->settings->get('Registrar ID');
        $contactId = $registrarId . '_' . substr(md5(uniqid()), 0, 8);

        // Check if contact ID is available
        $checkResult = $client->exec('contact-check', ['contacts' => [$contactId]]);
        if (!$checkResult['success'] || !$checkResult['data']['contacts'][0]['available']) {
            return ['error' => 'Contact ID not available'];
        }

        $contactParams = [
            'contactId' => $contactId,
            'name' => $params['RegistrantFirstName'] . ' ' . $params['RegistrantLastName'],
            'org' => $params['RegistrantOrganizationName'] ?? '',
            'email' => $params['RegistrantEmailAddress'],
            'voice' => $params['RegistrantPhone'] ?? '+30.2101234567',
            'street' => array_filter([
                $params['RegistrantAddress1'] ?? '',
                $params['RegistrantAddress2'] ?? ''
            ]),
            'city' => $params['RegistrantCity'] ?? 'Athens',
            'sp' => $params['RegistrantStateProvince'] ?? '',
            'pc' => $params['RegistrantPostalCode'] ?? '10000',
            'cc' => strtolower($params['RegistrantCountry'] ?? 'gr'),
            'password' => GrEppClient::generatePassword()
        ];

        $result = $client->exec('contact-create', $contactParams);

        if (!$result['success']) {
            return ['error' => $result['error']['msg']];
        }

        return $contactId;
    }

    /**
     * Create default contact for admin/tech/billing
     */
    private function createDefaultContact($client)
    {
        $registrarId = $this->settings->get('Registrar ID');
        $contactId = $registrarId . '_' . substr(md5(uniqid()), 0, 8);

        $contactParams = [
            'contactId' => $contactId,
            'name' => $this->settings->get('Default Contact Name'),
            'org' => $this->settings->get('Default Contact Name'),
            'email' => $this->settings->get('Default Contact Email'),
            'voice' => '+30.2101234567',
            'street' => ['Default Address'],
            'city' => 'Athens',
            'sp' => '',
            'pc' => '10000',
            'cc' => 'gr',
            'password' => GrEppClient::generatePassword()
        ];

        $result = $client->exec('contact-create', $contactParams);

        if (!$result['success']) {
            return ['error' => $result['error']['msg']];
        }

        return $contactId;
    }

    /**
     * Format contact data for ClientExec
     */
    private function formatContactData($data)
    {
        return [
            'Name' => $data['name'] ?? '',
            'Organization' => $data['org'] ?? '',
            'EmailAddress' => $data['email'] ?? '',
            'Phone' => $data['voice'] ?? '',
            'Address1' => $data['street'][0] ?? '',
            'Address2' => $data['street'][1] ?? '',
            'City' => $data['city'] ?? '',
            'StateProvince' => $data['sp'] ?? '',
            'PostalCode' => $data['pc'] ?? '',
            'Country' => strtoupper($data['cc'] ?? 'GR')
        ];
    }

    /**
     * Sanitize domain name for Greek TLD
     */
    private function sanitizeDomain($domain)
    {
        // Remove accents from Greek characters
        $domain = str_replace(
            ['ά', 'έ', 'ή', 'ί', 'ϊ', 'ΐ', 'ό', 'ύ', 'ϋ', 'ΰ', 'ώ'],
            ['α', 'ε', 'η', 'ι', 'ι', 'ι', 'ό', 'υ', 'υ', 'υ', 'ω'],
            $domain
        );

        // Replace final sigma
        $domain = preg_replace(['/σ-/', '/σ\./'], ['ς-', 'ς.'], $domain);

        return strtolower($domain);
    }

    // ========== Required Abstract Methods ==========

    /**
     * Get general domain information
     * Required by RegistrarPlugin abstract class
     */
    public function getGeneralInfo($params)
    {
        $domain = $params['sld'] . '.' . $params['tld'];
        $domain = $this->sanitizeDomain($domain);

        try {
            $client = $this->getClient();
            $result = $client->exec('domain-info', ['domain' => $domain]);

            if (!$result['success']) {
                return ['error' => $result['error']['msg']];
            }

            $data = $result['data'];

            return [
                'domain' => $data['domain'] ?? $domain,
                'expiration' => $data['exDate'] ?? '',
                'registrationstatus' => isset($data['status']) ? implode(', ', $data['status']) : '',
                'purchasestatus' => 1,
                'is_registered' => 1
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Set domain auto-renewal status
     * Required by RegistrarPlugin abstract class
     */
    public function setAutorenew($params)
    {
        // .GR registry does not support auto-renewal flag via EPP
        // Auto-renewal is handled by ClientExec billing system
        return [
            'success' => true,
            'message' => 'Auto-renewal is managed by ClientExec billing system'
        ];
    }

    /**
     * Get domain registrar lock status
     * Required by RegistrarPlugin abstract class
     */
    public function getRegistrarLock($params)
    {
        $domain = $params['sld'] . '.' . $params['tld'];
        $domain = $this->sanitizeDomain($domain);

        try {
            $client = $this->getClient();
            $result = $client->exec('domain-info', ['domain' => $domain]);

            if (!$result['success']) {
                return '0';
            }

            $data = $result['data'];
            $locked = false;

            // Check for clientTransferProhibited status
            if (isset($data['status']) && is_array($data['status'])) {
                $locked = in_array('clientTransferProhibited', $data['status']);
            }

            // ClientExec expects string '1' or '0', not array
            return $locked ? '1' : '0';
        } catch (Exception $e) {
            return '0';
        }
    }

    /**
     * Set domain registrar lock (alias for doSetRegistrarLock)
     * Required by RegistrarPlugin abstract class
     */
    public function setRegistrarLock($params)
    {
        return $this->doSetRegistrarLock($params);
    }

    /**
     * Set domain registrar lock
     * Required by RegistrarPlugin abstract class
     */
    public function doSetRegistrarLock($params)
    {
        $domain = $params['sld'] . '.' . $params['tld'];
        $domain = $this->sanitizeDomain($domain);
        $lock = !empty($params['lockoptions']);

        try {
            $client = $this->getClient();

            $updateParams = [
                'domain' => $domain
            ];

            if ($lock) {
                // Add transfer lock
                $updateParams['add'] = [
                    'status' => ['clientTransferProhibited']
                ];
            } else {
                // Remove transfer lock
                $updateParams['rem'] = [
                    'status' => ['clientTransferProhibited']
                ];
            }

            $result = $client->exec('domain-update', $updateParams);

            if (!$result['success']) {
                return ['error' => $result['error']['msg']];
            }

            return ['success' => true];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get child nameservers (glue records)
     * Required by RegistrarPlugin abstract class
     */
    public function getChildNameServers($params)
    {
        $domain = $params['sld'] . '.' . $params['tld'];
        $domain = $this->sanitizeDomain($domain);

        try {
            $client = $this->getClient();
            $result = $client->exec('domain-info', ['domain' => $domain]);

            if (!$result['success']) {
                return ['error' => $result['error']['msg']];
            }

            $nameservers = [];
            if (isset($result['data']['nameservers'])) {
                foreach ($result['data']['nameservers'] as $ns) {
                    // Check if this is a child nameserver (under this domain)
                    if (strpos($ns, '.' . $domain) !== false) {
                        // Get nameserver details
                        $hostInfo = $client->exec('host-info', ['host' => $ns]);
                        if ($hostInfo['success'] && isset($hostInfo['data']['addresses'])) {
                            $nameservers[] = [
                                'hostname' => $ns,
                                'ipaddress' => implode(', ', $hostInfo['data']['addresses'])
                            ];
                        }
                    }
                }
            }

            return $nameservers;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Send transfer key (EPP/Auth code) to domain owner
     * Required by RegistrarPlugin abstract class
     */
    public function sendTransferKey($params)
    {
        // The .GR registry uses DACOR tokens which are time-limited
        // Instead of "sending" the key, we retrieve it via getEPPCode
        // which generates a DACOR token that the domain owner can use

        $domain = $params['sld'] . '.' . $params['tld'];
        $domain = $this->sanitizeDomain($domain);

        try {
            // Get the EPP code (DACOR token)
            $eppResult = $this->getEPPCode($params);

            if (isset($eppResult['error'])) {
                return ['error' => $eppResult['error']];
            }

            // In a real implementation, you might want to email this to the domain owner
            // For now, we'll return success indicating the token has been generated
            return [
                'success' => true,
                'message' => 'DACOR transfer token generated. Use getEPPCode to retrieve it.',
                'eppcode' => $eppResult['eppcode'] ?? ''
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get TLDs and their pricing
     * Required by ClientExec for TLD import feature
     */
    public function getTLDsAndPrices($params)
    {
        // .GR registry pricing structure
        // Pricing should be configured manually in ClientExec
        // as it varies per registrar agreement

        return [
            'tlds' => [
                [
                    'tld' => 'gr',
                    'term' => '1',
                    'register' => 0.00,  // Set your pricing
                    'renew' => 0.00,
                    'transfer' => 0.00
                ],
                [
                    'tld' => 'ελ',  // Greek IDN
                    'term' => '1',
                    'register' => 0.00,  // Set your pricing
                    'renew' => 0.00,
                    'transfer' => 0.00
                ]
            ]
        ];
    }

    /**
     * Fetch/import existing domains from registry
     * Required by ClientExec for domain import feature
     */
    public function fetchDomains($params)
    {
        // This would require listing all domains under the registrar account
        // .GR EPP doesn't provide a "list all domains" command
        // Domains must be tracked in ClientExec database

        return [
            'success' => false,
            'error' => 'Domain listing not supported by .GR EPP. Please add domains manually.'
        ];
    }
}
