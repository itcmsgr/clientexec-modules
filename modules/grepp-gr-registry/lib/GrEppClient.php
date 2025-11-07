<?php
// SPDX-License-Identifier: LicenseRef-ITCMS-Free-1.0
// ITCMS.GR Free License – All Rights Reserved
// Copyright (c) 2025 Antonios Voulvoulis
// Free to use (including commercial use), but redistribution,
// resale, modification, or cloning is strictly prohibited.
//
// Purpose: EPP Client for .GR Registry (ClientExec Module)
//
// meta:name=clientexec-gr-epp-client
// meta:type=library
// meta:header=ClientExec .GR EPP Client
// meta:version=1.1.0
// meta:owner="Antonios Voulvoulis <contact@itcms.gr>"
// meta:homepage=https://itcms.gr/
//
// meta:description=Low-level EPP client for .gr registry supporting full EPP 4.3 protocol including DACOR tokens, glue records, and all domain/contact/host operations.
// meta:input=Configuration array with credentials and environment settings.
// meta:depends=curl, openssl, simplexml
// meta:created_date=2025-10-25
// meta:updated_date=2025-10-29

namespace ITCMS\ClientExec\GR;

/**
 * GrEppClient - Standalone EPP Client for .GR Registry
 *
 * This class handles all EPP protocol communication with the Greek (.gr) Registry.
 * Designed specifically for ClientExec with no external dependencies.
 */
class GrEppClient
{
    private $registrarId;
    private $username;
    private $password;
    private $productionMode;
    private $eppUrl;
    private $cookieFile;
    private $logFile;
    private $certificates;
    private $epp43Mode = true; // EPP 4.3 protocol (post Aug 2024)

    const VERSION = '1.1.0';
    const USER_AGENT = 'ClientExec-grEPP/1.1';

    /**
     * Constructor
     *
     * @param array $config Configuration array
     */
    public function __construct($config)
    {
        $this->registrarId = $config['registrarId'] ?? '';
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
        $this->productionMode = $config['production'] ?? false;
        $this->logFile = $config['logFile'] ?? __DIR__ . '/../logs/grepp.log';

        // Set EPP endpoint
        $this->eppUrl = $this->productionMode
            ? 'https://regepp.ics.forth.gr:700/epp/proxy'
            : 'https://uat-regepp.ics.forth.gr:700/epp/proxy';

        // Set certificate path
        $this->certificates = __DIR__ . '/certificates/regepp_chain.pem';

        // Cookie file for session management
        $this->cookieFile = sys_get_temp_dir() . '/grepp_cookies_' . md5($this->username) . '.txt';

        $this->log('GrEppClient v' . self::VERSION . ' initialized (' . ($this->productionMode ? 'PRODUCTION' : 'UAT') . ')');
    }

    /**
     * Execute EPP command
     *
     * @param string $command EPP command name
     * @param array $params Command parameters
     * @param int $expectedCode Expected success code
     * @return array Result array with success status and data
     */
    public function exec($command, $params = [], $expectedCode = 1000)
    {
        $xml = $this->buildRequestXML($command, $params);
        $response = $this->sendRequest($xml);

        if (!$response['success']) {
            return $response;
        }

        return $this->parseResponse($response['xml'], $expectedCode);
    }

    /**
     * Build EPP request XML
     */
    private function buildRequestXML($command, $params)
    {
        $pin = $this->generatePin();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">';
        $xml .= '<command>';

        switch ($command) {
            case 'login':
                $xml .= $this->buildLoginXML();
                break;
            case 'logout':
                $xml .= '<logout/>';
                break;
            case 'domain-check':
                $xml .= $this->buildDomainCheckXML($params);
                break;
            case 'domain-info':
                $xml .= $this->buildDomainInfoXML($params);
                break;
            case 'domain-create':
                $xml .= $this->buildDomainCreateXML($params);
                break;
            case 'domain-renew':
                $xml .= $this->buildDomainRenewXML($params);
                break;
            case 'domain-transfer':
                $xml .= $this->buildDomainTransferXML($params);
                break;
            case 'domain-update':
                $xml .= $this->buildDomainUpdateXML($params);
                break;
            case 'contact-check':
                $xml .= $this->buildContactCheckXML($params);
                break;
            case 'contact-info':
                $xml .= $this->buildContactInfoXML($params);
                break;
            case 'contact-create':
                $xml .= $this->buildContactCreateXML($params);
                break;
            case 'contact-update':
                $xml .= $this->buildContactUpdateXML($params);
                break;
            case 'host-check':
                $xml .= $this->buildHostCheckXML($params);
                break;
            case 'host-create':
                $xml .= $this->buildHostCreateXML($params);
                break;
            case 'host-info':
                $xml .= $this->buildHostInfoXML($params);
                break;
            case 'host-update':
                $xml .= $this->buildHostUpdateXML($params);
                break;
            case 'host-delete':
                $xml .= $this->buildHostDeleteXML($params);
                break;
            case 'domain-delete':
                $xml .= $this->buildDomainDeleteXML($params);
                break;
            case 'dacor-issue-token':
                $xml .= $this->buildDacorIssueTokenXML($params);
                break;
            case 'domain-recall-application':
                $xml .= $this->buildDomainRecallApplicationXML($params);
                break;
        }

        $xml .= '<clTRID>' . $pin . '</clTRID>';
        $xml .= '</command>';
        $xml .= '</epp>';

        return $xml;
    }

    /**
     * Send EPP request via HTTP POST
     */
    private function sendRequest($xml)
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->eppUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CAINFO => $this->certificates,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=UTF-8',
                'Content-Length: ' . strlen($xml)
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            $this->log('cURL Error: ' . $error, 'ERROR');
            return [
                'success' => false,
                'error' => ['code' => 0, 'msg' => 'Connection error: ' . $error]
            ];
        }

        if ($httpCode !== 200) {
            $this->log('HTTP Error: ' . $httpCode, 'ERROR');
            return [
                'success' => false,
                'error' => ['code' => $httpCode, 'msg' => 'HTTP error: ' . $httpCode]
            ];
        }

        $this->log('Request: ' . $xml, 'DEBUG');
        $this->log('Response: ' . $response, 'DEBUG');

        return [
            'success' => true,
            'xml' => simplexml_load_string($response)
        ];
    }

    /**
     * Parse EPP response XML
     */
    private function parseResponse($xml, $expectedCode)
    {
        $result = [];
        $result['code'] = (int) $xml->response->result->attributes()->code;
        $result['msg'] = (string) $xml->response->result->msg;
        $result['success'] = ($result['code'] === $expectedCode);

        if (!$result['success']) {
            $result['error'] = [
                'code' => $result['code'],
                'msg' => $result['msg']
            ];
        }

        // Parse response data based on code
        if (isset($xml->response->resData)) {
            $result['data'] = $this->parseResData($xml->response->resData);
        }

        // Parse extension data (for DACOR tokens, etc.)
        if (isset($xml->response->extension)) {
            $extData = $this->parseExtensionData($xml->response->extension);
            if ($extData) {
                $result['data'] = array_merge($result['data'] ?? [], $extData);
            }
        }

        return $result;
    }

    /**
     * Parse response data section
     */
    private function parseResData($resData)
    {
        $data = [];

        // Domain info
        if (isset($resData->children('urn:ietf:params:xml:ns:domain-1.0')->infData)) {
            $infData = $resData->children('urn:ietf:params:xml:ns:domain-1.0')->infData;
            $data['domain'] = (string) $infData->name;
            $data['registrant'] = (string) $infData->registrant;
            $data['crDate'] = (string) $infData->crDate;
            $data['exDate'] = (string) $infData->exDate;
            $data['upDate'] = (string) $infData->upDate ?? null;

            // Parse contacts
            if (isset($infData->contact)) {
                foreach ($infData->contact as $contact) {
                    $type = (string) $contact->attributes()->type;
                    $data['contacts'][$type] = (string) $contact;
                }
            }

            // Parse nameservers
            if (isset($infData->ns->hostObj)) {
                foreach ($infData->ns->hostObj as $ns) {
                    $data['nameservers'][] = (string) $ns;
                }
            }

            // Parse status
            if (isset($infData->status)) {
                foreach ($infData->status as $status) {
                    $data['status'][] = (string) $status->attributes()->s;
                }
            }

            // Parse auth info (password) - for domain deletion
            if (isset($infData->authInfo->pw)) {
                $data['password'] = (string) $infData->authInfo->pw;
            }

            // Parse ROID (Registry Object ID)
            if (isset($infData->roid)) {
                $data['roid'] = (string) $infData->roid;
            }
        }

        // Contact info
        if (isset($resData->children('urn:ietf:params:xml:ns:contact-1.0')->infData)) {
            $infData = $resData->children('urn:ietf:params:xml:ns:contact-1.0')->infData;
            $data['id'] = (string) $infData->id;
            $data['email'] = (string) $infData->email;

            // Postal info
            if (isset($infData->postalInfo)) {
                $postal = $infData->postalInfo;
                $data['name'] = (string) $postal->name;
                $data['org'] = (string) $postal->org ?? '';
                $data['street'] = [];
                if (isset($postal->addr->street)) {
                    foreach ($postal->addr->street as $street) {
                        $data['street'][] = (string) $street;
                    }
                }
                $data['city'] = (string) $postal->addr->city;
                $data['sp'] = (string) $postal->addr->sp ?? '';
                $data['pc'] = (string) $postal->addr->pc;
                $data['cc'] = (string) $postal->addr->cc;
            }

            if (isset($infData->voice)) {
                $data['voice'] = (string) $infData->voice;
            }
        }

        // Check results
        if (isset($resData->children('urn:ietf:params:xml:ns:domain-1.0')->chkData)) {
            $chkData = $resData->children('urn:ietf:params:xml:ns:domain-1.0')->chkData;
            foreach ($chkData->cd as $cd) {
                $domain = (string) $cd->name;
                $avail = ((string) $cd->name->attributes()->avail === '1');
                $data['domains'][] = ['name' => $domain, 'available' => $avail];
            }
        }

        if (isset($resData->children('urn:ietf:params:xml:ns:contact-1.0')->chkData)) {
            $chkData = $resData->children('urn:ietf:params:xml:ns:contact-1.0')->chkData;
            foreach ($chkData->cd as $cd) {
                $contactId = (string) $cd->id;
                $avail = ((string) $cd->id->attributes()->avail === '1');
                $data['contacts'][] = ['id' => $contactId, 'available' => $avail];
            }
        }

        // Host info
        if (isset($resData->children('urn:ietf:params:xml:ns:host-1.0')->infData)) {
            $infData = $resData->children('urn:ietf:params:xml:ns:host-1.0')->infData;
            $data['name'] = (string) $infData->name;
            $data['addresses'] = [];

            if (isset($infData->addr)) {
                foreach ($infData->addr as $addr) {
                    $data['addresses'][] = (string) $addr;
                }
            }

            $data['crDate'] = (string) $infData->crDate ?? null;
            $data['upDate'] = (string) $infData->upDate ?? null;
        }

        // Host check results
        if (isset($resData->children('urn:ietf:params:xml:ns:host-1.0')->chkData)) {
            $chkData = $resData->children('urn:ietf:params:xml:ns:host-1.0')->chkData;
            foreach ($chkData->cd as $cd) {
                $hostName = (string) $cd->name;
                $avail = ((string) $cd->name->attributes()->avail === '1');
                $data['hosts'][] = ['name' => $hostName, 'available' => $avail];
            }
        }

        return $data;
    }

    /**
     * Parse extension data section (DACOR tokens, etc.)
     */
    private function parseExtensionData($extension)
    {
        $data = [];

        // Parse DACOR token (EPP 4.3+)
        if (isset($extension->children('http://www.ics.forth.gr/gr-domain-ext-1.0')->resData)) {
            $extDomain = $extension->children('http://www.ics.forth.gr/gr-domain-ext-1.0')->resData;

            // DACOR transfer token
            if (isset($extDomain->comment)) {
                $data['dacor_token'] = (string) $extDomain->comment;
            }
        }

        // Parse .GR domain extension info data
        if (isset($extension->children('http://www.ics.forth.gr/gr-domain-ext-1.0')->infData)) {
            $extDomain = $extension->children('http://www.ics.forth.gr/gr-domain-ext-1.0')->infData;

            // Protocol ID (used for recall application)
            if (isset($extDomain->protocol)) {
                $data['protocol'] = (string) $extDomain->protocol;
            }
        }

        // Parse host extension data
        if (isset($extension->children('http://www.ics.forth.gr/gr-host-ext-1.0')->resData)) {
            $extHost = $extension->children('http://www.ics.forth.gr/gr-host-ext-1.0')->resData;
            // Add any host-specific extension parsing here if needed
        }

        return $data;
    }

    /**
     * Build login XML
     */
    private function buildLoginXML()
    {
        $xml = '<login>';
        $xml .= '<clID>' . htmlspecialchars($this->username) . '</clID>';
        $xml .= '<pw>' . htmlspecialchars($this->password) . '</pw>';
        $xml .= '<options>';
        $xml .= '<version>1.0</version>';
        $xml .= '<lang>el</lang>';
        $xml .= '</options>';
        $xml .= '<svcs>';
        $xml .= '<objURI>urn:ietf:params:xml:ns:domain-1.0</objURI>';
        $xml .= '<objURI>urn:ietf:params:xml:ns:contact-1.0</objURI>';
        $xml .= '<objURI>urn:ietf:params:xml:ns:host-1.0</objURI>';
        $xml .= '</svcs>';
        $xml .= '</login>';
        return $xml;
    }

    /**
     * Build domain check XML
     */
    private function buildDomainCheckXML($params)
    {
        $xml = '<check>';
        $xml .= '<domain:check xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">';
        foreach ($params['domains'] as $domain) {
            $xml .= '<domain:name>' . htmlspecialchars($domain) . '</domain:name>';
        }
        $xml .= '</domain:check>';
        $xml .= '</check>';
        return $xml;
    }

    /**
     * Build domain info XML
     */
    private function buildDomainInfoXML($params)
    {
        $xml = '<info>';
        $xml .= '<domain:info xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">';
        $xml .= '<domain:name>' . htmlspecialchars($params['domain']) . '</domain:name>';
        $xml .= '</domain:info>';
        $xml .= '</info>';
        return $xml;
    }

    /**
     * Build domain create XML
     */
    private function buildDomainCreateXML($params)
    {
        $xml = '<create>';
        $xml .= '<domain:create xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">';
        $xml .= '<domain:name>' . htmlspecialchars($params['domain']) . '</domain:name>';
        $xml .= '<domain:period unit="y">' . (int)$params['years'] . '</domain:period>';

        // Nameservers
        if (!empty($params['nameservers'])) {
            $xml .= '<domain:ns>';
            foreach ($params['nameservers'] as $ns) {
                $xml .= '<domain:hostObj>' . htmlspecialchars($ns) . '</domain:hostObj>';
            }
            $xml .= '</domain:ns>';
        }

        // Registrant
        $xml .= '<domain:registrant>' . htmlspecialchars($params['registrant']) . '</domain:registrant>';

        // Contacts
        if (!empty($params['contacts'])) {
            foreach ($params['contacts'] as $contact) {
                $xml .= '<domain:contact type="' . htmlspecialchars($contact['type']) . '">';
                $xml .= htmlspecialchars($contact['id']);
                $xml .= '</domain:contact>';
            }
        }

        // Auth info
        $xml .= '<domain:authInfo>';
        $xml .= '<domain:pw>' . htmlspecialchars($params['password']) . '</domain:pw>';
        $xml .= '</domain:authInfo>';

        $xml .= '</domain:create>';
        $xml .= '</create>';
        return $xml;
    }

    /**
     * Build domain renew XML
     */
    private function buildDomainRenewXML($params)
    {
        $xml = '<renew>';
        $xml .= '<domain:renew xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">';
        $xml .= '<domain:name>' . htmlspecialchars($params['domain']) . '</domain:name>';
        $xml .= '<domain:curExpDate>' . $params['expDate'] . '</domain:curExpDate>';
        $xml .= '<domain:period unit="y">' . (int)$params['years'] . '</domain:period>';
        $xml .= '</domain:renew>';
        $xml .= '</renew>';
        return $xml;
    }

    /**
     * Build domain transfer XML
     */
    private function buildDomainTransferXML($params)
    {
        $xml = '<transfer op="request">';
        $xml .= '<domain:transfer xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">';
        $xml .= '<domain:name>' . htmlspecialchars($params['domain']) . '</domain:name>';
        $xml .= '<domain:period unit="y">' . (int)$params['years'] . '</domain:period>';
        $xml .= '<domain:authInfo>';
        $xml .= '<domain:pw>' . htmlspecialchars($params['authCode']) . '</domain:pw>';
        $xml .= '</domain:authInfo>';
        $xml .= '</domain:transfer>';
        $xml .= '</transfer>';
        return $xml;
    }

    /**
     * Build domain update XML
     */
    private function buildDomainUpdateXML($params)
    {
        $xml = '<update>';
        $xml .= '<domain:update xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">';
        $xml .= '<domain:name>' . htmlspecialchars($params['domain']) . '</domain:name>';

        // Add/Remove operations
        if (isset($params['add'])) {
            $xml .= '<domain:add>';
            if (isset($params['add']['nameservers'])) {
                $xml .= '<domain:ns>';
                foreach ($params['add']['nameservers'] as $ns) {
                    $xml .= '<domain:hostObj>' . htmlspecialchars($ns) . '</domain:hostObj>';
                }
                $xml .= '</domain:ns>';
            }
            if (isset($params['add']['contacts'])) {
                foreach ($params['add']['contacts'] as $contact) {
                    $xml .= '<domain:contact type="' . htmlspecialchars($contact['type']) . '">';
                    $xml .= htmlspecialchars($contact['id']);
                    $xml .= '</domain:contact>';
                }
            }
            $xml .= '</domain:add>';
        }

        if (isset($params['rem'])) {
            $xml .= '<domain:rem>';
            if (isset($params['rem']['nameservers'])) {
                $xml .= '<domain:ns>';
                foreach ($params['rem']['nameservers'] as $ns) {
                    $xml .= '<domain:hostObj>' . htmlspecialchars($ns) . '</domain:hostObj>';
                }
                $xml .= '</domain:ns>';
            }
            if (isset($params['rem']['contacts'])) {
                foreach ($params['rem']['contacts'] as $contact) {
                    $xml .= '<domain:contact type="' . htmlspecialchars($contact['type']) . '">';
                    $xml .= htmlspecialchars($contact['id']);
                    $xml .= '</domain:contact>';
                }
            }
            $xml .= '</domain:rem>';
        }

        $xml .= '</domain:update>';
        $xml .= '</update>';
        return $xml;
    }

    /**
     * Build contact check XML
     */
    private function buildContactCheckXML($params)
    {
        $xml = '<check>';
        $xml .= '<contact:check xmlns:contact="urn:ietf:params:xml:ns:contact-1.0">';
        foreach ($params['contacts'] as $contactId) {
            $xml .= '<contact:id>' . htmlspecialchars($contactId) . '</contact:id>';
        }
        $xml .= '</contact:check>';
        $xml .= '</check>';
        return $xml;
    }

    /**
     * Build contact info XML
     */
    private function buildContactInfoXML($params)
    {
        $xml = '<info>';
        $xml .= '<contact:info xmlns:contact="urn:ietf:params:xml:ns:contact-1.0">';
        $xml .= '<contact:id>' . htmlspecialchars($params['contactId']) . '</contact:id>';
        $xml .= '</contact:info>';
        $xml .= '</info>';
        return $xml;
    }

    /**
     * Build contact create XML
     */
    private function buildContactCreateXML($params)
    {
        $xml = '<create>';
        $xml .= '<contact:create xmlns:contact="urn:ietf:params:xml:ns:contact-1.0">';
        $xml .= '<contact:id>' . htmlspecialchars($params['contactId']) . '</contact:id>';
        $xml .= '<contact:postalInfo type="loc">';
        $xml .= '<contact:name>' . htmlspecialchars($params['name']) . '</contact:name>';
        if (!empty($params['org'])) {
            $xml .= '<contact:org>' . htmlspecialchars($params['org']) . '</contact:org>';
        }
        $xml .= '<contact:addr>';
        foreach ($params['street'] as $street) {
            $xml .= '<contact:street>' . htmlspecialchars($street) . '</contact:street>';
        }
        $xml .= '<contact:city>' . htmlspecialchars($params['city']) . '</contact:city>';
        if (!empty($params['sp'])) {
            $xml .= '<contact:sp>' . htmlspecialchars($params['sp']) . '</contact:sp>';
        }
        $xml .= '<contact:pc>' . htmlspecialchars($params['pc']) . '</contact:pc>';
        $xml .= '<contact:cc>' . htmlspecialchars($params['cc']) . '</contact:cc>';
        $xml .= '</contact:addr>';
        $xml .= '</contact:postalInfo>';
        if (!empty($params['voice'])) {
            $xml .= '<contact:voice>' . htmlspecialchars($params['voice']) . '</contact:voice>';
        }
        $xml .= '<contact:email>' . htmlspecialchars($params['email']) . '</contact:email>';
        $xml .= '<contact:authInfo>';
        $xml .= '<contact:pw>' . htmlspecialchars($params['password']) . '</contact:pw>';
        $xml .= '</contact:authInfo>';
        $xml .= '</contact:create>';
        $xml .= '</create>';
        return $xml;
    }

    /**
     * Build contact update XML
     */
    private function buildContactUpdateXML($params)
    {
        $xml = '<update>';
        $xml .= '<contact:update xmlns:contact="urn:ietf:params:xml:ns:contact-1.0">';
        $xml .= '<contact:id>' . htmlspecialchars($params['contactId']) . '</contact:id>';

        if (isset($params['chg'])) {
            $xml .= '<contact:chg>';
            $xml .= '<contact:postalInfo type="loc">';
            if (isset($params['chg']['name'])) {
                $xml .= '<contact:name>' . htmlspecialchars($params['chg']['name']) . '</contact:name>';
            }
            if (isset($params['chg']['org'])) {
                $xml .= '<contact:org>' . htmlspecialchars($params['chg']['org']) . '</contact:org>';
            }
            if (isset($params['chg']['addr'])) {
                $xml .= '<contact:addr>';
                foreach ($params['chg']['addr']['street'] as $street) {
                    $xml .= '<contact:street>' . htmlspecialchars($street) . '</contact:street>';
                }
                $xml .= '<contact:city>' . htmlspecialchars($params['chg']['addr']['city']) . '</contact:city>';
                if (!empty($params['chg']['addr']['sp'])) {
                    $xml .= '<contact:sp>' . htmlspecialchars($params['chg']['addr']['sp']) . '</contact:sp>';
                }
                $xml .= '<contact:pc>' . htmlspecialchars($params['chg']['addr']['pc']) . '</contact:pc>';
                $xml .= '<contact:cc>' . htmlspecialchars($params['chg']['addr']['cc']) . '</contact:cc>';
                $xml .= '</contact:addr>';
            }
            $xml .= '</contact:postalInfo>';
            if (isset($params['chg']['voice'])) {
                $xml .= '<contact:voice>' . htmlspecialchars($params['chg']['voice']) . '</contact:voice>';
            }
            if (isset($params['chg']['email'])) {
                $xml .= '<contact:email>' . htmlspecialchars($params['chg']['email']) . '</contact:email>';
            }
            $xml .= '</contact:chg>';
        }

        $xml .= '</contact:update>';
        $xml .= '</update>';
        return $xml;
    }

    /**
     * Build host check XML
     */
    private function buildHostCheckXML($params)
    {
        $xml = '<check>';
        $xml .= '<host:check xmlns:host="urn:ietf:params:xml:ns:host-1.0">';
        foreach ($params['hosts'] as $host) {
            $xml .= '<host:name>' . htmlspecialchars($host) . '</host:name>';
        }
        $xml .= '</host:check>';
        $xml .= '</check>';
        return $xml;
    }

    /**
     * Build host create XML
     */
    private function buildHostCreateXML($params)
    {
        $xml = '<create>';
        $xml .= '<host:create xmlns:host="urn:ietf:params:xml:ns:host-1.0">';
        $xml .= '<host:name>' . htmlspecialchars($params['host']) . '</host:name>';
        if (!empty($params['ipv4'])) {
            foreach ((array)$params['ipv4'] as $ip) {
                $xml .= '<host:addr ip="v4">' . htmlspecialchars($ip) . '</host:addr>';
            }
        }
        if (!empty($params['ipv6'])) {
            foreach ((array)$params['ipv6'] as $ip) {
                $xml .= '<host:addr ip="v6">' . htmlspecialchars($ip) . '</host:addr>';
            }
        }
        $xml .= '</host:create>';
        $xml .= '</create>';
        return $xml;
    }

    /**
     * Build host info XML
     */
    private function buildHostInfoXML($params)
    {
        $xml = '<info>';
        $xml .= '<host:info xmlns:host="urn:ietf:params:xml:ns:host-1.0">';
        $xml .= '<host:name>' . htmlspecialchars($params['host']) . '</host:name>';
        $xml .= '</host:info>';
        $xml .= '</info>';
        return $xml;
    }

    /**
     * Build host update XML
     */
    private function buildHostUpdateXML($params)
    {
        $xml = '<update>';
        $xml .= '<host:update xmlns:host="urn:ietf:params:xml:ns:host-1.0">';
        $xml .= '<host:name>' . htmlspecialchars($params['host']) . '</host:name>';

        // Add/Remove IP addresses
        if (isset($params['add'])) {
            $xml .= '<host:add>';
            if (isset($params['add']['ipv4'])) {
                foreach ((array)$params['add']['ipv4'] as $ip) {
                    $xml .= '<host:addr ip="v4">' . htmlspecialchars($ip) . '</host:addr>';
                }
            }
            if (isset($params['add']['ipv6'])) {
                foreach ((array)$params['add']['ipv6'] as $ip) {
                    $xml .= '<host:addr ip="v6">' . htmlspecialchars($ip) . '</host:addr>';
                }
            }
            $xml .= '</host:add>';
        }

        if (isset($params['rem'])) {
            $xml .= '<host:rem>';
            if (isset($params['rem']['ipv4'])) {
                foreach ((array)$params['rem']['ipv4'] as $ip) {
                    $xml .= '<host:addr ip="v4">' . htmlspecialchars($ip) . '</host:addr>';
                }
            }
            if (isset($params['rem']['ipv6'])) {
                foreach ((array)$params['rem']['ipv6'] as $ip) {
                    $xml .= '<host:addr ip="v6">' . htmlspecialchars($ip) . '</host:addr>';
                }
            }
            $xml .= '</host:rem>';
        }

        $xml .= '</host:update>';
        $xml .= '</update>';
        return $xml;
    }

    /**
     * Build host delete XML
     */
    private function buildHostDeleteXML($params)
    {
        $xml = '<delete>';
        $xml .= '<host:delete xmlns:host="urn:ietf:params:xml:ns:host-1.0">';
        $xml .= '<host:name>' . htmlspecialchars($params['host']) . '</host:name>';
        $xml .= '</host:delete>';
        $xml .= '</delete>';
        return $xml;
    }

    /**
     * Build domain delete XML
     */
    private function buildDomainDeleteXML($params)
    {
        $xml = '<delete>';
        $xml .= '<domain:delete xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">';
        $xml .= '<domain:name>' . htmlspecialchars($params['domain']) . '</domain:name>';
        $xml .= '</domain:delete>';
        $xml .= '</delete>';
        return $xml;
    }

    /**
     * Build DACOR issue token XML (EPP 4.3+)
     * Used for obtaining transfer authorization codes
     */
    private function buildDacorIssueTokenXML($params)
    {
        $xml = '<info>';
        $xml .= '<domain:info xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">';
        $xml .= '<domain:name>' . htmlspecialchars($params['domain']) . '</domain:name>';
        $xml .= '</domain:info>';
        $xml .= '</info>';
        $xml .= '<extension>';
        $xml .= '<extdomain:info xmlns:extdomain="http://www.ics.forth.gr/gr-domain-ext-1.0">';
        $xml .= '<extdomain:issueToken/>';
        $xml .= '</extdomain:info>';
        $xml .= '</extension>';
        return $xml;
    }

    /**
     * Build domain recall application XML (.GR specific)
     * Used to recall a domain registration within 5 days
     */
    private function buildDomainRecallApplicationXML($params)
    {
        $xml = '<delete>';
        $xml .= '<domain:delete xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">';
        $xml .= '<domain:name>' . htmlspecialchars($params['domain']) . '</domain:name>';
        $xml .= '</domain:delete>';
        $xml .= '</delete>';
        $xml .= '<extension>';
        $xml .= '<extdomain:delete xmlns:extdomain="http://www.ics.forth.gr/gr-domain-ext-1.0">';
        $xml .= '<extdomain:op>recallApplication</extdomain:op>';
        $xml .= '<extdomain:datatype>protocol</extdomain:datatype>';
        $xml .= '<extdomain:details>' . htmlspecialchars($params['protocol']) . '</extdomain:details>';
        $xml .= '</extdomain:delete>';
        $xml .= '</extension>';
        return $xml;
    }

    /**
     * Generate random PIN for transaction ID
     */
    private function generatePin($length = 10)
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $pin = '';
        for ($i = 0; $i < $length; $i++) {
            $pin .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $pin;
    }

    /**
     * Generate secure password
     */
    public static function generatePassword($length = 12)
    {
        $lowers = 'abcdefghijklmnopqrstuvwxyz';
        $uppers = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $digits = '0123456789';
        $symbols = '!@#$%^&*()';

        // Ensure at least one of each type
        $password = $lowers[rand(0, strlen($lowers) - 1)];
        $password .= $uppers[rand(0, strlen($uppers) - 1)];
        $password .= $digits[rand(0, strlen($digits) - 1)];
        $password .= $symbols[rand(0, strlen($symbols) - 1)];

        // Fill the rest
        $all = $lowers . $uppers . $digits . $symbols;
        for ($i = 4; $i < $length; $i++) {
            $password .= $all[rand(0, strlen($all) - 1)];
        }

        return str_shuffle($password);
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
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);
    }

    /**
     * Destructor - cleanup
     */
    public function __destruct()
    {
        // Logout if needed
        if (file_exists($this->cookieFile)) {
            $this->exec('logout');
            @unlink($this->cookieFile);
        }
    }
}
