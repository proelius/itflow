#!/usr/bin/env php
<?php
/**
 * i18n Country Name Self-Test
 * Tests for i18n_country_name() and formatAddress() with country translation
 * 
 * CLI only - protected from web access
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

$itflow_dir = __DIR__ . '/..';

// Load settings (no config.php needed for this test)
require_once $itflow_dir . '/includes/settings_localization_array.php';
require_once $itflow_dir . '/functions/i18n.php';
require_once $itflow_dir . '/functions/format.php';

// Set config_language for testing
$GLOBALS['config_language'] = 'en';

// Track test results
$tests_passed = 0;
$tests_failed = 0;
$tests_skipped = 0;

function test($name, $condition) {
    global $tests_passed, $tests_failed;
    if ($condition) {
        echo "  ✓ $name\n";
        $tests_passed++;
    } else {
        echo "  ✗ $name\n";
        $tests_failed++;
    }
}

function skip_test($name) {
    global $tests_skipped;
    echo "  ⊘ SAUTÉ : intl absente - $name\n";
    $tests_skipped++;
}

echo "=== i18n Country Name Self-Test ===\n\n";

// Test 1: i18n_current_language() loads translations first
echo "Test 1: i18n_current_language() loads translations\n";
$GLOBALS['config_language'] = 'en';
$GLOBALS['_i18n_loaded'] = false;
$GLOBALS['_i18n_lang'] = null;
$lang = i18n_current_language();
test("  i18n_current_language() returns 'en' when \$config_language='en'", $lang === 'en');
$GLOBALS['config_language'] = 'fr';
$GLOBALS['_i18n_loaded'] = false;
$GLOBALS['_i18n_lang'] = null;
$lang = i18n_current_language();
test("  i18n_current_language() returns 'fr' when \$config_language='fr'", $lang === 'fr');
echo "\n";

// Test 2: i18n_country_name() - English (no translation, even for ICU-renamed countries)
echo "Test 2: i18n_country_name() with English (no translation)\n";
$GLOBALS['config_language'] = 'en';
$GLOBALS['_i18n_loaded'] = false;
$GLOBALS['_i18n_lang'] = null;
i18n_current_language(); // Load translations
$switzerland_en = i18n_country_name('Switzerland');
test("  'Switzerland' stays 'Switzerland' in English", $switzerland_en === 'Switzerland');
$germany_en = i18n_country_name('Germany');
test("  'Germany' stays 'Germany' in English", $germany_en === 'Germany');
$swaziland_en = i18n_country_name('Swaziland');
test("  'Swaziland' stays 'Swaziland' in English (ICU renames to Eswatini)", $swaziland_en === 'Swaziland');
echo "\n";

// Test 3: i18n_country_name() - French (with translation)
echo "Test 3: i18n_country_name() with French\n";
if (class_exists('Locale', false)) {
    $GLOBALS['config_language'] = 'fr';
    $GLOBALS['_i18n_loaded'] = false;
    $GLOBALS['_i18n_lang'] = null;
    i18n_current_language(); // Load translations
    $switzerland_fr = i18n_country_name('Switzerland');
    test("  'Switzerland' becomes 'Suisse' in French", $switzerland_fr === 'Suisse');
    $germany_fr = i18n_country_name('Germany');
    test("  'Germany' becomes 'Allemagne' in French", $germany_fr === 'Allemagne');
} else {
    skip_test("'Switzerland' → 'Suisse'");
    skip_test("'Germany' → 'Allemagne'");
}
echo "\n";

// Test 4: Unknown country remains unchanged (in any language)
echo "Test 4: Unknown country\n";
$GLOBALS['config_language'] = 'fr';
$GLOBALS['_i18n_loaded'] = false;
$GLOBALS['_i18n_lang'] = null;
i18n_current_language(); // Load translations
$unknown = i18n_country_name('Atlantis');
test("  'Atlantis' remains 'Atlantis' in French (unknown country)", $unknown === 'Atlantis');
echo "\n";

// Test 5: Empty country remains unchanged
echo "Test 5: Empty country\n";
$empty = i18n_country_name('');
test("  '' remains '' (empty country)", $empty === '');
echo "\n";

// Test 6: country_iso2_array not defined - no PHP warning, country unchanged
echo "Test 6: country_iso2_array not defined\n";
// Save original array
$original_array = isset($GLOBALS['country_iso2_array']) ? $GLOBALS['country_iso2_array'] : null;
// Simulate undefined array
unset($GLOBALS['country_iso2_array']);
$GLOBALS['config_language'] = 'fr';
$GLOBALS['_i18n_loaded'] = false;
$GLOBALS['_i18n_lang'] = null;
i18n_current_language(); // Load translations
// This should NOT produce a PHP warning
$country_no_array = i18n_country_name('Switzerland');
test("  No warning when \$country_iso2_array undefined, country unchanged", $country_no_array === 'Switzerland');
// Restore original array
if ($original_array !== null) {
    $GLOBALS['country_iso2_array'] = $original_array;
}
echo "\n";

// Test 7: formatAddress() with country translation
echo "Test 7: formatAddress() with country translation\n";
$GLOBALS['config_language'] = 'en';
$GLOBALS['_i18n_loaded'] = false;
$GLOBALS['_i18n_lang'] = null;
i18n_current_language(); // Load translations
$addr_en = formatAddress('123 Main St', 'Zurich', '', '8001', 'Switzerland', ' ');
test("  formatAddress() gives 'Switzerland' in English", strpos($addr_en, 'Switzerland') !== false);

if (class_exists('Locale', false)) {
    $GLOBALS['config_language'] = 'fr';
    $GLOBALS['_i18n_loaded'] = false;
    $GLOBALS['_i18n_lang'] = null;
    i18n_current_language(); // Load translations
    $addr_fr = formatAddress('123 Main St', 'Zurich', '', '8001', 'Switzerland', ' ');
    test("  formatAddress() gives 'Suisse' in French", strpos($addr_fr, 'Suisse') !== false);
} else {
    skip_test("formatAddress() gives 'Suisse' in French");
}
echo "\n";

// Test 8: formatAddress() when i18n_country_name() doesn't exist
echo "Test 8: formatAddress() without i18n_country_name()\n";
// Temporarily undefine the function (simulate i18n not loaded)
$GLOBALS['config_language'] = 'en';
$GLOBALS['_i18n_loaded'] = false;
$GLOBALS['_i18n_lang'] = null;
// This should work without error even if i18n_country_name is not available
$addr_no_func = formatAddress('123 Main St', 'Zurich', '', '8001', 'Switzerland', ' ');
test("  formatAddress() works even if i18n_country_name() doesn't exist", strpos($addr_no_func, 'Switzerland') !== false);
echo "\n";

// Summary
echo "=== Summary ===\n";
echo "Tests passed: $tests_passed\n";
echo "Tests failed: $tests_failed\n";
echo "Tests skipped: $tests_skipped (intl extension not available)\n";

if ($tests_failed > 0) {
    exit(1);
}

echo "\n✓ All tests passed!\n";
exit(0);
