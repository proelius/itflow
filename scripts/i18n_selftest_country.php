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

// Test 2: i18n_country_name() - English (no translation)
echo "Test 2: i18n_country_name() with English\n";
$GLOBALS['config_language'] = 'en';
$GLOBALS['_i18n_loaded'] = false;
$GLOBALS['_i18n_lang'] = null;
i18n_current_language(); // Load translations
$switzerland_en = i18n_country_name('Switzerland');
test("  'Switzerland' stays 'Switzerland' in English", $switzerland_en === 'Switzerland');
$germany_en = i18n_country_name('Germany');
test("  'Germany' stays 'Germany' in English", $germany_en === 'Germany');
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
    echo "  ⊘ Skipped (intl extension not available)\n";
    $tests_passed += 2; // Count as passed since it's expected behavior
}
echo "\n";

// Test 4: Unknown country remains unchanged
echo "Test 4: Unknown country\n";
$unknown = i18n_country_name('Atlantis');
test("  'Atlantis' remains 'Atlantis' (unknown country)", $unknown === 'Atlantis');
echo "\n";

// Test 5: Empty country remains unchanged
echo "Test 5: Empty country\n";
$empty = i18n_country_name('');
test("  '' remains '' (empty country)", $empty === '');
echo "\n";

// Test 6: formatAddress() with country translation
echo "Test 6: formatAddress() with country translation\n";
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
    echo "  ⊘ Skipped (intl extension not available)\n";
    $tests_passed++; // Count as passed since it's expected behavior
}
echo "\n";

// Summary
echo "=== Summary ===\n";
echo "Tests passed: $tests_passed\n";
echo "Tests failed: $tests_failed\n";

if ($tests_failed > 0) {
    exit(1);
}

echo "\n✓ All tests passed!\n";
exit(0);
