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
 *   t('Failed login for %username%', ['%username%' => $username])  // Parameters via strtr()
 *   i18n_with('fr', fn() => t('Sign In'))  // Temporary French context
 *   i18n_set_language('fr')          // Permanent switch (for PDFs)
 * 
 * Files:
 *   lang/en.php - English texts (keys)
 *   lang/fr.php - French translations
 */

// Global translation state
$GLOBALS['_i18n_lang'] = 'en';
$GLOBALS['_i18n_translations'] = [];

/**
 * Normalize language code to lowercase 2-letter code
 * @param string|null $lang Language code (e.g. 'fr_CH', 'FR', 'fr-FR', null)
 * @return string Normalized code ('fr', 'en', etc.)
 */
function _i18n_normalize_lang($lang) {
    // If not a string, return default
    if (!is_string($lang)) {
        return 'en';
    }
    
    // Extract first 2 letters, lowercase
    $normalized = strtolower(substr($lang, 0, 2));
    
    // Validate: must be 2 characters
    if (strlen($normalized) !== 2) {
        return 'en';
    }
    
    // Check against available languages (no hardcoded values)
    $available_langs = _i18n_get_available_langs();
    if (in_array($normalized, $available_langs)) {
        return $normalized;
    }
    
    // Fallback to default
    return 'en';
}

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
    
    // Always include default
    if (!in_array('en', $langs)) {
        $langs[] = 'en';
    }
    
    $cache = $langs;
    return $langs;
}

/**
 * Load translations for a specific language with static cache
 * @param string $lang Language code
 * @return array Translations array
 */
function _i18n_dictionary($lang) {
    static $cache = [];
    
    // Return cached if available
    if (isset($cache[$lang])) {
        return $cache[$lang];
    }
    
    $translations = [];
    
    // Load English (base)
    $en_file = __DIR__ . '/../lang/en.php';
    if (file_exists($en_file)) {
        $en_translations = require $en_file;
        if (is_array($en_translations)) {
            $translations = $en_translations;
        }
    }
    
    // Load target language (overrides English)
    if ($lang !== 'en') {
        $lang_file = __DIR__ . '/../lang/' . $lang . '.php';
        if (file_exists($lang_file)) {
            $lang_translations = require $lang_file;
            if (is_array($lang_translations)) {
                // Merge: target language overrides English keys
                $translations = array_merge($translations, $lang_translations);
            }
        }
    }
    
    $cache[$lang] = $translations;
    return $translations;
}

/**
 * Apply language settings (set global state)
 * @param string $lang Normalized language code
 */
function _i18n_apply($lang) {
    $GLOBALS['_i18n_lang'] = $lang;
    $GLOBALS['_i18n_translations'] = _i18n_dictionary($lang);
}

/**
 * Load translations for current language from config (only once)
 */
function _i18n_load() {
    global $config_language;
    
    // If already loaded, return current state
    if (!empty($GLOBALS['_i18n_loaded'])) {
        return;
    }
    
    // Determine language from config
    $lang = isset($config_language) && is_string($config_language)
        ? _i18n_normalize_lang($config_language)
        : 'en';
    
    _i18n_apply($lang);
    $GLOBALS['_i18n_loaded'] = true;
}

/**
 * Translate text
 * 
 * @param string $english_text English text (serves as translation key)
 * @param array $params Named parameters for replacement (e.g. ['%username%' => 'John'])
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
 * Translate email body/subject text with SQL escaping for apostrophes
 * Always uses client_language() regardless of current global language
 * Escapes ' in the TRANSLATED template to \' BEFORE substituting parameters
 * Parameters are NEVER escaped (they are pre-escaped by the caller)
 * 
 * @param string $key Translation key (English text)
 * @param array $params Named parameters ['{name}' => $value, ...]
 * @return string Translated and escaped text ready for addToMailQueue()
 */
function t_mail($key, $params = []) {
    // Save current language state
    $saved_lang = $GLOBALS['_i18n_lang'] ?? null;
    $saved_translations = $GLOBALS['_i18n_translations'] ?? null;
    $saved_loaded = $GLOBALS['_i18n_loaded'] ?? false;
    
    // Always use client_language() for emails
    $email_lang = client_language();
    
    // Load translations for email language (bypass _i18n_load which uses config_language)
    if ($saved_loaded === false || $GLOBALS['_i18n_lang'] !== $email_lang) {
        $GLOBALS['_i18n_lang'] = $email_lang;
        $GLOBALS['_i18n_translations'] = _i18n_dictionary($email_lang);
        $GLOBALS['_i18n_loaded'] = true;
    }
    
    // Get translation
    $translated = $key;
    if (isset($GLOBALS['_i18n_translations'][$key])) {
        $translated = $GLOBALS['_i18n_translations'][$key];
    }
    
    // ESCAPE apostrophes in the translated template BEFORE substituting parameters
    // This ensures params (already pre-escaped) are not double-escaped
    $translated = str_replace("'", "\\'", $translated);
    
    // Substitute parameters (they are already pre-escaped by caller)
    if (!empty($params) && is_array($params)) {
        $translated = strtr($translated, $params);
    }
    
    // Restore original language state
    if ($saved_lang !== null) {
        $GLOBALS['_i18n_lang'] = $saved_lang;
        $GLOBALS['_i18n_translations'] = $saved_translations;
        $GLOBALS['_i18n_loaded'] = $saved_loaded;
    }
    
    return $translated;
}

/**
 * Get current language
 * @return string Current language code (en, fr, etc.)
 */
function _i18n_get_lang() {
    return $GLOBALS['_i18n_lang'];
}

/**
 * Get current language, ensuring translations are loaded first
 * @return string Language code (e.g. 'fr', 'en')
 */
function i18n_current_language() {
    _i18n_load();
    return _i18n_get_lang();
}

/**
 * Set language permanently (for PDFs that end with exit)
 * @param string $lang Language code (e.g. 'fr', 'en')
 */
function i18n_set_language($lang) {
    _i18n_load();
    _i18n_apply(_i18n_normalize_lang($lang));
}

/**
 * Execute code with temporary language context
 * @param string $lang Language code (e.g. 'fr', 'en')
 * @param callable $callback Function to execute
 * @return mixed Result of callback
 */
function i18n_with($lang, callable $callback) {
    // Load current state
    _i18n_load();
    
    // Save current language
    $old_lang = $GLOBALS['_i18n_lang'];
    
    // Set new language
    i18n_set_language($lang);
    
    // Guaranteed restoration even if callback throws exception
    try {
        return $callback();
    } finally {
        // Restore previous language
        _i18n_apply($old_lang);
    }
}

/**
 * Get client language from config (no DB column)
 * Normalizes 'fr_CH' -> 'fr', 'FR' -> 'fr', etc.
 * @param int|null $client_id Client ID (optional, for future use)
 * @return string Normalized language code ('en', 'fr', etc.)
 */
function client_language($client_id = null) {
    global $config_language_client;
    
    return _i18n_normalize_lang(isset($config_language_client) ? $config_language_client : 'en');
}

/**
 * Get country name in current language
 * @param string $country Country name (e.g. 'Switzerland', 'Germany')
 * @return string Translated country name or original if not found
 */
function i18n_country_name($country) {
    global $country_iso2_array;
    
    // Return unchanged if empty
    if (empty($country)) {
        return $country;
    }
    
    // Return unchanged in English (no translation needed)
    if (i18n_current_language() === 'en') {
        return $country;
    }
    
    // Return unchanged if no Locale class
    if (!class_exists('Locale', false)) {
        return $country;
    }
    
    // Return unchanged if country_iso2_array not defined or not an array
    if (!is_array($country_iso2_array)) {
        return $country;
    }
    
    // Direct lookup (no foreach loop)
    $iso2 = $country_iso2_array[$country] ?? null;
    
    // Return unchanged if country not found
    if ($iso2 === null) {
        return $country;
    }
    
    // Get translated country name
    $translated = Locale::getDisplayRegion('-' . strtoupper($iso2), i18n_current_language());
    
    // Return original if translation is empty or same as code
    if (empty($translated) || $translated === strtoupper($iso2)) {
        return $country;
    }
    
    return $translated;
}
