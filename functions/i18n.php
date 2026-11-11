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
 * Escapes \ and ' in the TRANSLATED template (order: backslash first, then apostrophe)
 * Parameters are NEVER escaped (they are pre-escaped by the caller)
 * 
 * @param string $english_text Translation key (English text)
 * @param array $params Named parameters ['{name}' => $value, ...]
 * @return string Translated and escaped text ready for addToMailQueue()
 */
function t_mail($english_text, $params = []) {
    $dictionary = _i18n_dictionary(client_language());
    $template = $dictionary[$english_text] ?? $english_text;
    $template = str_replace(['\\', "'"], ['\\\\', "\\'"], $template);   // antislash PUIS apostrophe
    return empty($params) ? $template : strtr($template, $params);
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

/**
 * Get ticket status label in current language
 * @param string $name English status name (New, Open, On Hold, Resolved, Closed)
 * @return string Translated status label or original if not found/custom
 */
function i18n_ticket_status_label(string $name): string {
    return match ($name) {
        'New' => t('New'),
        'Open' => t('Open'),
        'On Hold' => t('On Hold'),
        'Resolved' => t('Resolved'),
        'Closed' => t('Closed'),
        default => $name,
    };
}

/**
 * Get ticket status label in client's language (for emails)
 * @param string $name English status name (New, Open, On Hold, Resolved, Closed)
 * @return string Translated status label in client's language or original if not found/custom
 */
function i18n_ticket_status_label_client(string $name): string {
    return i18n_with(client_language(), fn() => i18n_ticket_status_label($name));
}

/**
 * Get ticket priority label in current language
 * @param string $name English priority name (Low, Medium, High, Critical)
 * @return string Translated priority label or original if not found/custom
 */
function i18n_ticket_priority_label(string $name): string {
    return match ($name) {
        'Low' => t('Low'),
        'Medium' => t('Medium'),
        'High' => t('High'),
        'Critical' => t('Critical'),
        'Urgent' => t('Urgent'),
        default => $name,
    };
}

/**
 * Format SLA due timestamp in current language
 * @param int $timestamp Unix timestamp
 * @return string Formatted date/time in current language
 */
function i18n_format_sla_due(int $timestamp): string {
    _i18n_load();
    $current_lang = $GLOBALS['_i18n_lang'];
    
    if ($current_lang === 'en') {
        // English: exact format 'D j M, g:i A'
        return date('D j M, g:i A', $timestamp);
    }
    
    // Try IntlDateFormatter if available
    if (class_exists('IntlDateFormatter')) {
        $locale = $current_lang === 'fr' ? 'fr_CH' : $current_lang;
        $formatter = new IntlDateFormatter(
            $locale,
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            date_default_timezone_get(),
            IntlDateFormatter::GREGORIAN,
            'EEE d MMM, HH:mm'
        );
        $formatted = $formatter->format($timestamp);
        if ($formatted !== false) {
            return $formatted;
        }
    }
    
    // Fallback to English format
    return date('D j M, g:i A', $timestamp);
}

/**
 * Format a date or time in the current language.
 *
 * English keeps PHP's date() exactly. Other languages translate the format itself
 * with t() (so 'M j, Y' can become 'j M Y'; only the formats listed below) and the month and day names, which
 * date() only knows in English. Any other format, such as 'Y-m-d', is left as it is.
 *
 * @param string $format English date() format
 * @param int|null $timestamp Unix timestamp, now when omitted
 * @return string
 */
function i18n_date(string $format, ?int $timestamp = null): string {
    $timestamp = $timestamp ?? time();
    _i18n_load();
    if ($GLOBALS['_i18n_lang'] === 'en') {
        return date($format, $timestamp);
    }
    // Literal t() calls, so each format is a visible key; any other format stays as it is
    $format = [
        'M j, Y' => t('M j, Y'),
        'M j' => t('M j'),
        'F j, Y' => t('F j, Y'),
        'l, F j' => t('l, F j'),
        'g:i A' => t('g:i A'),
        'M j, Y g:i A' => t('M j, Y g:i A'),
    ][$format] ?? $format;
    $names = [
        'M' => [t('Jan'), t('Feb'), t('Mar'), t('Apr'), t('May'), t('Jun'), t('Jul'), t('Aug'), t('Sep'), t('Oct'), t('Nov'), t('Dec')],
        'F' => [t('January'), t('February'), t('March'), t('April'), t('May'), t('June'), t('July'), t('August'), t('September'), t('October'), t('November'), t('December')],
        'D' => [t('Sun'), t('Mon'), t('Tue'), t('Wed'), t('Thu'), t('Fri'), t('Sat')],
        'l' => [t('Sunday'), t('Monday'), t('Tuesday'), t('Wednesday'), t('Thursday'), t('Friday'), t('Saturday')],
    ];
    $out = '';
    $length = strlen($format);
    for ($i = 0; $i < $length; $i++) {
        $char = $format[$i];
        if ($char === '\\' && $i + 1 < $length) {
            $out .= $format[++$i];
        } elseif ($char === 'M' || $char === 'F') {
            $out .= $names[$char][(int) date('n', $timestamp) - 1];
        } elseif ($char === 'D' || $char === 'l') {
            $out .= $names[$char][(int) date('w', $timestamp)];
        } else {
            $out .= date($char, $timestamp);
        }
    }
    return $out;
}

/**
 * Get invoice status label in current language
 * @param string $name English status name (Draft, Sent, Viewed, Partial, Paid, Cancelled, Non-Billable)
 * @return string Translated status label or original if not found/custom
 */
function i18n_invoice_status_label(string $name): string {
    return match ($name) {
        'Draft' => t('Draft'),
        'Sent' => t('Sent'),
        'Viewed' => t('Viewed'),
        'Partial' => t('Partial'),
        'Paid' => t('Paid'),
        'Cancelled' => t('Cancelled'),
        'Non-Billable' => t('Non-Billable'),
        default => $name,
    };
}

/**
 * Get quote status label in current language
 * @param string $name English status name (Draft, Sent, Viewed, Accepted, Declined, Invoiced)
 * @return string Translated status label or original if not found/custom
 */
function i18n_quote_status_label(string $name): string {
    return match ($name) {
        'Draft' => t('Draft'),
        'Sent' => t('Sent'),
        'Viewed' => t('Viewed'),
        'Accepted' => t('Accepted'),
        'Declined' => t('Declined'),
        'Invoiced' => t('Invoiced'),
        default => $name,
    };
}

/**
 * Get recurring invoice frequency label in current language
 * @param string $name English frequency name (monthly, yearly)
 * @return string Translated frequency label or original if not found/custom
 */
function i18n_recurring_frequency_label(string $name): string {
    $lower = strtolower($name);
    return match ($lower) {
        'monthly' => t('Monthly'),
        'yearly' => t('Yearly'),
        default => $name,
    };
}

/**
 * Get portal label text in current language (for PORTAL_TICKET_EMAILS, PORTAL_COMPANY_TICKET_EMAILS, and device labels)
 * @param string $text English label text
 * @return string Translated label or original if not found
 */
function i18n_portal_label(string $text): string {
    return match ($text) {
        'Every email' => t('Every email'),
        'When a ticket is raised for you, every reply and update, when it is fixed and when it is closed' => t('When a ticket is raised for you, every reply and update, when it is fixed and when it is closed'),
        'Replies and fixes only' => t('Replies and fixes only'),
        'Our replies and when it is fixed - not the emails saying a ticket was raised or closed' => t('Our replies and when it is fixed - not the emails saying a ticket was raised or closed'),
        'Only the tickets I am on' => t('Only the tickets I am on'),
        'Nothing about tickets other people raise' => t('Nothing about tickets other people raise'),
        'New tickets' => t('New tickets'),
        'An email when anyone raises one - in the portal, by email or by calling us' => t('An email when anyone raises one - in the portal, by email or by calling us'),
        'New tickets and every update' => t('New tickets and every update'),
        'As if you were copied in on all of them' => t('As if you were copied in on all of them'),
        'Unknown device' => t('Unknown device'),
        'With a password' => t('With a password'),
        'With their Microsoft 365 account' => t('With their Microsoft 365 account'),
        'No portal access' => t('No portal access'),
        'Portal access off' => t('Portal access off'),
        'Signs in with a password' => t('Signs in with a password'),
        'Signs in with Microsoft' => t('Signs in with Microsoft'),
        'Client Portal' => t('Client Portal'),
        'Your IT' => t('Your IT'),
        'Your account' => t('Your account'),
        'Activity' => t('Activity'),
        'Light mode' => t('Light mode'),
        'Dark mode' => t('Dark mode'),
        'Exit view' => t('Exit view'),
        'Sign out' => t('Sign out'),
        'Home' => t('Home'),
        'Tickets' => t('Tickets'),
        'New' => t('New'),
        'Billing' => t('Billing'),
        'Menu' => t('Menu'),
        'Invoices' => t('Invoices'),
        'Pay invoices' => t('Pay invoices'),
        'Quotes' => t('Quotes'),
        'Recurring invoices' => t('Recurring invoices'),
        'Contracts' => t('Contracts'),
        'Payments' => t('Payments'),
        'Statement' => t('Statement'),
        'Cards & autopay' => t('Cards & autopay'),
        'Devices' => t('Devices'),
        'Licenses' => t('Licenses'),
        'Domains & certificates' => t('Domains & certificates'),
        'Documents' => t('Documents'),
        'Contacts' => t('Contacts'),
        'Close' => t('Close'),
        'Company' => t('Company'),
        'Roles' => t('Roles'),
        'Email' => t('Email'),
        'Mobile' => t('Mobile'),
        'Department' => t('Department'),
        'Location' => t('Location'),
        'Primary contact' => t('Primary contact'),
        'Technical' => t('Technical'),
        'Waiting on you' => t('Waiting on you'),
        'On hold' => t('On hold'),
        'Received' => t('Received'),
        'In progress' => t('In progress'),
        'Open' => t('Open'),
        'Resolved' => t('Resolved'),
        'Closed' => t('Closed'),
        'All' => t('All'),
        'Paid' => t('Paid'),
        'Cancelled' => t('Cancelled'),
        'No charge' => t('No charge'),
        'Draft' => t('Draft'),
        'Accepted' => t('Accepted'),
        'Declined' => t('Declined'),
        'Expired' => t('Expired'),
        'Due today' => t('Due today'),
        'A password is at least 8 characters.' => t('A password is at least 8 characters.'),
        'To pay' => t('To pay'),
        'Waiting for you' => t('Waiting for you'),
        'Monthly' => t('Monthly'),
        'Quarterly' => t('Quarterly'),
        'Yearly' => t('Yearly'),
        'month' => t('month'),
        'quarter' => t('quarter'),
        'year' => t('year'),
        'Current' => t('Current'),
        '1-30 days' => t('1-30 days'),
        '31-60 days' => t('31-60 days'),
        '61-90 days' => t('61-90 days'),
        'Over 90 days' => t('Over 90 days'),
        default => $text,
    };
}

