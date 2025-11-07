<?php
// SPDX-License-Identifier: LicenseRef-ITCMS-Free-1.0
// ITCMS.GR Free License – All Rights Reserved
// Copyright (c) 2025 Antonios Voulvoulis
// Free to use (including commercial use), but redistribution,
// resale, modification, or cloning is strictly prohibited.
//
// Purpose: Simple language/translation system
//
// meta:name=language-system
// meta:type=library
// meta:version=1.0.0
// meta:owner="Antonios Voulvoulis <contact@itcms.gr>"
// meta:homepage=https://itcms.gr/

namespace DnsAlert;

/**
 * Language - Simple Translation System
 *
 * Supports:
 * - Multiple languages (English, Greek, etc.)
 * - Variable substitution in translations
 * - Automatic fallback to English
 */
class Language
{
    protected $currentLang = 'en';
    protected $translations = [];
    protected $langDir;

    public function __construct($lang = 'en', $langDir = null)
    {
        $this->langDir = $langDir ?? __DIR__ . '/../lang/';
        $this->setLanguage($lang);
    }

    /**
     * Set current language
     */
    public function setLanguage($lang)
    {
        // Validate language code
        $lang = strtolower(substr($lang, 0, 2));

        // Check if language file exists
        $langFile = $this->langDir . $lang . '.php';
        if (!file_exists($langFile)) {
            $lang = 'en'; // Fallback to English
        }

        $this->currentLang = $lang;
        $this->loadTranslations($lang);
    }

    /**
     * Load translations for a language
     */
    protected function loadTranslations($lang)
    {
        $langFile = $this->langDir . $lang . '.php';

        if (file_exists($langFile)) {
            $this->translations[$lang] = require $langFile;
        } else {
            $this->translations[$lang] = [];
        }

        // Also load English as fallback if not already loaded
        if ($lang !== 'en' && !isset($this->translations['en'])) {
            $enFile = $this->langDir . 'en.php';
            if (file_exists($enFile)) {
                $this->translations['en'] = require $enFile;
            }
        }
    }

    /**
     * Get translation for a key
     *
     * @param string $key Translation key
     * @param array $vars Variables to substitute (e.g., ['name' => 'John'])
     * @return string Translated text
     */
    public function get($key, $vars = [])
    {
        // Try current language
        if (isset($this->translations[$this->currentLang][$key])) {
            $text = $this->translations[$this->currentLang][$key];
        }
        // Fallback to English
        elseif (isset($this->translations['en'][$key])) {
            $text = $this->translations['en'][$key];
        }
        // Return key if not found
        else {
            return $key;
        }

        // Substitute variables
        if (!empty($vars)) {
            foreach ($vars as $varKey => $value) {
                $text = str_replace('{' . $varKey . '}', $value, $text);
            }
        }

        return $text;
    }

    /**
     * Translate template variables
     *
     * Replaces {{lang_key}} with translations in templates
     *
     * @param string $template Template content
     * @return string Translated template
     */
    public function translateTemplate($template)
    {
        // Find all {{lang_*}} placeholders
        return preg_replace_callback('/\{\{lang_([a-z_]+)\}\}/', function ($matches) {
            $key = $matches[1];
            return $this->get($key);
        }, $template);
    }

    /**
     * Get all translations for current language
     */
    public function getAll()
    {
        return $this->translations[$this->currentLang] ?? [];
    }

    /**
     * Get current language code
     */
    public function getCurrentLanguage()
    {
        return $this->currentLang;
    }

    /**
     * Check if a translation key exists
     */
    public function has($key)
    {
        return isset($this->translations[$this->currentLang][$key]) ||
               isset($this->translations['en'][$key]);
    }

    /**
     * Auto-detect language from user preference or browser
     */
    public static function detectLanguage()
    {
        // Try to detect from ClientExec user settings
        global $user;
        if (isset($user) && isset($user->language)) {
            return $user->language;
        }

        // Try to detect from browser
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
            if (in_array($browserLang, ['en', 'el'])) {
                return $browserLang;
            }
        }

        // Default to English
        return 'en';
    }

    /**
     * Get available languages
     */
    public function getAvailableLanguages()
    {
        $languages = [];
        $files = glob($this->langDir . '*.php');

        foreach ($files as $file) {
            $lang = basename($file, '.php');
            $languages[$lang] = $this->getLanguageName($lang);
        }

        return $languages;
    }

    /**
     * Get language name
     */
    protected function getLanguageName($code)
    {
        $names = [
            'en' => 'English',
            'el' => 'Ελληνικά (Greek)',
            'de' => 'Deutsch (German)',
            'fr' => 'Français (French)',
            'es' => 'Español (Spanish)',
            'it' => 'Italiano (Italian)',
        ];

        return $names[$code] ?? $code;
    }
}
