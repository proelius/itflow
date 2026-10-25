<?php
/**
 * ITFlow i18n System - Key-Based Translation
 * 
 * Approach: English text serves as the translation key.
 * t('Sign In') looks up 'Sign In' in fr.php and returns 'Se connecter'.
 * If translation is missing, returns the English text unchanged.
 * 
 * Usage:
 *   t('Sign In')                    // Returns 'Se connecter' (FR) or 'Sign In' (EN)
 *   t('Failed login', $username)    // Parameters replaced via strtr()
 * 
 * Files:
 *   lang/en.php - English texts (keys)
 *   lang/fr.php - French translations
 */

// Global translation cache
$GLOBALS['_i18n_loaded'] = false;
$GLOBALS['_i18n_lang'] = 'en';
$GLOBALS['_i18n_translations'] = [];

/**
 * Get list of available languages from lang/ directory
 * @return array Array of language codes (e.g. ['en', 'fr'])
 */
function _i18n_get_available_langs() {
    static $cache = null;
    
    if ($cache !== null) {
        return $cache;
    }
    
    $lang_dir = __DIR__ . '/../lang/';
    $langs = [];
    
    if (is_dir($lang_dir)) {
        $files = scandir($lang_dir);
        foreach ($files as $file) {
            if (preg_match('/^([a-z]{2})\.php$/', $file, $matches)) {
                $langs[] = $matches[1];
            }
        }
    }
    
    // Always include 'en' as fallback
    if (!in_array('en', $langs)) {
        $langs[] = 'en';
    }
    
    $cache = $langs;
    return $cache;
}

/**
 * Get client language from config (no DB column)
 * @param int|null $client_id Client ID (optional, for future use)
 * @return string Language code (e.g. 'en', 'fr', 'fr_FR')
 */
function client_language($client_id = null) {
    global $config_language_client;
    
    // Return from config (e.g. 'fr_FR', 'en_US', etc.)
    if (isset($config_language_client) && is_string($config_language_client)) {
        return $config_language_client;
    }
    
    // Default to English
    return 'en';
}

/**
 * Load translations for current language
 */
function _i18n_load() {
    global $config_language;
    
    if ($GLOBALS['_i18n_loaded']) {
        return;
    }
    
    // Determine language (default: en)
    $lang = 'en';
    if (isset($config_language) && is_string($config_language)) {
        $lang = strtolower(trim($config_language));
    }
    
    // Get available languages from lang/ directory (not hardcoded)
    $available_langs = _i18n_get_available_langs();
    
    // Validate against available languages
    if (!in_array($lang, $available_langs)) {
        $lang = 'en';
    }
    
    $GLOBALS['_i18n_lang'] = $lang;
    $GLOBALS['_i18n_translations'] = [];
    
    // Load English (base)
    $en_file = __DIR__ . '/../lang/en.php';
    if (file_exists($en_file)) {
        $translations = require $en_file;
        if (is_array($translations)) {
            $GLOBALS['_i18n_translations'] = $translations;
        }
    }
    
    // Load target language (overrides English)
    if ($lang !== 'en') {
        $lang_file = __DIR__ . '/../lang/' . $lang . '.php';
        if (file_exists($lang_file)) {
            $lang_translations = require $lang_file;
            if (is_array($lang_translations)) {
                // Merge: target language overrides English keys
                $GLOBALS['_i18n_translations'] = array_merge(
                    $GLOBALS['_i18n_translations'],
                    $lang_translations
                );
            }
        }
    }
    
    $GLOBALS['_i18n_loaded'] = true;
}

/**
 * Translate text
 * 
 * @param string $english_text English text (serves as translation key)
 * @param array $params Named parameters for replacement (e.g. ['username' => 'John'])
 * @return string Translated text or original English if not found
 */
function t($english_text, $params = []) {
    _i18n_load();
    
    // Use English text as key
    $key = $english_text;
    
    // Get translation (English if not found)
    $translated = isset($GLOBALS['_i18n_translations'][$key]) 
        ? $GLOBALS['_i18n_translations'][$key] 
        : $english_text;
    
    // Replace parameters (only parameter values, not the whole string)
    if (!empty($params) && is_array($params)) {
        $translated = strtr($translated, $params);
    }
    
    return $translated;
}

/**
 * Get current language
 * @return string Current language code (en, fr, etc.)
 */
function _i18n_get_lang() {
    _i18n_load();
    return $GLOBALS['_i18n_lang'];
}

/**
 * Execute code with temporary language context
 * @param string $lang Language code
 * @param callable $callback Function to execute
 * @return mixed Result of callback
 */
function i18n_with($lang, callable $callback) {
    _i18n_load();
    
    $old_lang = $GLOBALS['_i18n_lang'];
    $available_langs = _i18n_get_available_langs();
    
    // Validate language
    $effective_lang = in_array($lang, $available_langs) ? $lang : $old_lang;
    
    // Set new language
    $GLOBALS['_i18n_lang'] = $effective_lang;
    $GLOBALS['_i18n_loaded'] = false;
    _i18n_load();
    
    // try/finally for guaranteed restoration
    try {
        return $callback();
    } finally {
        $GLOBALS['_i18n_lang'] = $old_lang;
        $GLOBALS['_i18n_loaded'] = false;
        _i18n_load();
    }
}
