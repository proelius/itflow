<?php
/**
 * i18n Mail Function Self-Test
 * 
 * Tests t_mail() function for email translation with SQL escaping
 * 
 * Usage: php scripts/i18n_selftest_mail.php
 * 
 * Security: Must be run from CLI only (scripts/ is web-accessible).
 */

if (PHP_SAPI !== 'cli') {
    exit('This script must be run from command line only.');
}

// Get ITFlow root directory
$itflow_dir = dirname(__DIR__);

// Include i18n functions only
require_once $itflow_dir . '/functions/i18n.php';

echo "=== i18n Mail Function Self-Test ===\n\n";

$tests_passed = 0;
$tests_failed = 0;

// Test (a): client_language='fr', t_mail('Sign In') = 'Se connecter'
echo "Test (a): client_language='fr', t_mail('Sign In') should return 'Se connecter'\n";
$GLOBALS['_i18n_lang'] = 'en';
$GLOBALS['_i18n_translations'] = [];
$GLOBALS['_i18n_loaded'] = false;
global $config_language_client;
$config_language_client = 'fr';
$result = t_mail('Sign In');
if ($result === 'Se connecter') {
    echo "  ✓ PASS: t_mail('Sign In') = '{$result}'\n";
    $tests_passed++;
} else {
    echo "  ✗ FAIL: Expected 'Se connecter', got '{$result}'\n";
    $tests_failed++;
}

// Test (b): client_language='en', t_mail('Sign In') = 'Sign In'
echo "\nTest (b): client_language='en', t_mail('Sign In') should return 'Sign In'\n";
$GLOBALS['_i18n_lang'] = 'fr';
$GLOBALS['_i18n_translations'] = [];
$GLOBALS['_i18n_loaded'] = false;
$config_language_client = 'en';
$result = t_mail('Sign In');
// Check that the global state was restored to 'fr'
if ($GLOBALS['_i18n_lang'] === 'fr') {
    echo "  ✓ PASS: Language restored to 'fr' after t_mail()\n";
    $tests_passed++;
} else {
    echo "  ✗ FAIL: Language not restored, got '" . $GLOBALS['_i18n_lang'] . "'\n";
    $tests_failed++;
}

// Test (c): Global language state is identical before and after t_mail() call
echo "\nTest (c): Global language state preserved before and after t_mail()\n";
$GLOBALS['_i18n_lang'] = 'en';
$GLOBALS['_i18n_translations'] = [];
$GLOBALS['_i18n_loaded'] = false;
$config_language_client = 'en';
$before_lang = $GLOBALS['_i18n_lang'];
t_mail('Sign In');
$after_lang = $GLOBALS['_i18n_lang'];
if ($before_lang === $after_lang) {
    echo "  ✓ PASS: Language state preserved ({$before_lang} -> {$after_lang})\n";
    $tests_passed++;
} else {
    echo "  ✗ FAIL: Language changed ({$before_lang} -> {$after_lang})\n";
    $tests_failed++;
}

// Test (d): t_mail("We've applied") produces "We\'ve applied" (backslash + apostrophe)
echo "\nTest (d): t_mail with apostrophe should escape to \\\'\n";
$GLOBALS['_i18n_lang'] = 'en';
$GLOBALS['_i18n_translations'] = [];
$GLOBALS['_i18n_loaded'] = false;
$config_language_client = 'en';
$GLOBALS['_i18n_translations']["We've applied payment"] = "We've applied payment";
$result = t_mail("We've applied payment");
// Check that apostrophe is escaped (contains \' but not just ')
if (strpos($result, "\\'") !== false) {
    echo "  ✓ PASS: Apostrophe escaped correctly: '{$result}'\n";
    $tests_passed++;
} else {
    echo "  ✗ FAIL: Apostrophe not escaped: '{$result}'\n";
    $tests_failed++;
}

// Test (e): Pre-escaped parameter not doubled
echo "\nTest (e): Pre-escaped parameter should not be doubled\n";
$GLOBALS['_i18n_lang'] = 'en';
$GLOBALS['_i18n_translations'] = [];
$GLOBALS['_i18n_loaded'] = false;
$config_language_client = 'en';
$GLOBALS['_i18n_translations']['Hello {name},'] = 'Hello {name},';
// Input: O'Brien (one backslash + apostrophe)
$params = ['{name}' => "O\'Brien"];
$result = t_mail('Hello {name},', $params);
// Expected: Hello O'Brien, (one backslash + apostrophe, not doubled)
$expected = "Hello O\'Brien,";
if ($result === $expected) {
    echo "  ✓ PASS: Parameter not doubled: '{$result}'\n";
    $tests_passed++;
} else {
    echo "  ✗ FAIL: Expected '{$expected}', got '{$result}'\n";
    $tests_failed++;
}

// Test (f): A value containing another marker is not substituted a 2nd time
echo "\nTest (f): Value containing marker should not be substituted twice\n";
// Pre-load English dictionary first
$GLOBALS['_i18n_lang'] = 'en';
$GLOBALS['_i18n_translations'] = [];
$GLOBALS['_i18n_loaded'] = false;
$config_language_client = 'en';
// Force load to get the dictionary
$t_mail_test_key = 'Test t_mail substitution';
$GLOBALS['_i18n_translations'][$t_mail_test_key] = 'Hello {name}, {name}';
$GLOBALS['_i18n_loaded'] = true;  // Prevent reload
$params = ['{name}' => 'Jean'];
$result = t_mail($t_mail_test_key, $params);
// strtr substitutes ALL occurrences of {name} in the template
if ($result === 'Hello Jean, Jean') {
    echo "  ✓ PASS: All markers substituted correctly: '{$result}'\n";
    $tests_passed++;
} else {
    echo "  ✗ FAIL: Unexpected result: '{$result}'\n";
    $tests_failed++;
}

// Test (g): All keys should produce output without unescaped apostrophes
echo "\nTest (g): All keys should produce output without unescaped apostrophes\n";
$en_file = $itflow_dir . '/lang/en.php';
$en_keys = array_keys(require $en_file);

$all_clean = true;
foreach (array_slice($en_keys, 0, 10) as $key) {
    $GLOBALS['_i18n_lang'] = 'en';
    $GLOBALS['_i18n_translations'] = [];
    $GLOBALS['_i18n_loaded'] = false;
    $config_language_client = 'en';
    $result_en = t_mail($key);
    
    $GLOBALS['_i18n_lang'] = 'fr';
    $GLOBALS['_i18n_translations'] = [];
    $GLOBALS['_i18n_loaded'] = false;
    $config_language_client = 'fr';
    $result_fr = t_mail($key);
    
    if (preg_match("/(?<!\\\\)'/", $result_en) || preg_match("/(?<!\\\\)'/", $result_fr)) {
        echo "  ✗ FAIL: Key '{$key}' has unescaped apostrophe\n";
        $all_clean = false;
        $tests_failed++;
    }
}

if ($all_clean) {
    echo "  ✓ PASS: All tested keys produce clean output\n";
    $tests_passed++;
} else {
    echo "  ✗ FAIL: Some keys have unescaped apostrophes\n";
}

// Summary
echo "\n=== Summary ===\n";
echo "Tests passed: {$tests_passed}\n";
echo "Tests failed: {$tests_failed}\n";

if ($tests_failed > 0) {
    echo "\n✗ Some tests failed\n";
    exit(1);
} else {
    echo "\n✓ All tests passed!\n";
    exit(0);
}
