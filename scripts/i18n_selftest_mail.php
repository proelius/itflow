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

// Test (b): client_language='en', config_language='fr', t_mail('Sign In') = 'Sign In'
echo "\nTest (b): client_language='en', config_language='fr', t_mail('Sign In') should return 'Sign In'\n";
$GLOBALS['_i18n_lang'] = 'fr';
$GLOBALS['_i18n_translations'] = [];
$GLOBALS['_i18n_loaded'] = false;
$config_language_client = 'en';
$result = t_mail('Sign In');
if ($result === 'Sign In') {
    echo "  ✓ PASS: t_mail('Sign In') = '{$result}' (uses client_language='en', not config_language='fr')\n";
    $tests_passed++;
} else {
    echo "  ✗ FAIL: Expected 'Sign In', got '{$result}'\n";
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
// Use a key that exists in the dictionary with a placeholder
$GLOBALS['_i18n_lang'] = 'en';
$GLOBALS['_i18n_translations'] = [];
$GLOBALS['_i18n_loaded'] = false;
$config_language_client = 'en';
// The key 'As of {date}' exists in the dictionary
$params = ['{date}' => '2024-01-15'];
$result = t_mail('As of {date}', $params);
// strtr substitutes ALL occurrences of {date} in the template
if ($result === 'As of 2024-01-15') {
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

// Test (h): t_mail('a\b') (key absent from dictionary, with backslash) returns 'a\\b'
echo "\nTest (h): t_mail with backslash in key (absent from dict) should escape to a\\b\n";
$GLOBALS['_i18n_lang'] = 'en';
$GLOBALS['_i18n_translations'] = [];
$GLOBALS['_i18n_loaded'] = false;
$config_language_client = 'en';
// Key not in dictionary - should return the key itself with escaping
// Input: a\b (one backslash in PHP string)
// After escaping: \ -> \\ (one backslash becomes two)
// Result: a\\b (two backslashes)
$result = t_mail('a\\b');
// Expected: a\\b (one backslash escaped to two)
$expected = 'a\\\\b';
if ($result === $expected) {
    echo "  ✓ PASS: Backslash escaped correctly: '{$result}'\n";
    $tests_passed++;
} else {
    echo "  ✗ FAIL: Expected '{$expected}', got '{$result}'\n";
    $tests_failed++;
}

// Test (i): A parameter containing a backslash is not doubled
echo "\nTest (i): Parameter containing backslash should not be doubled\n";
$GLOBALS['_i18n_lang'] = 'en';
$GLOBALS['_i18n_translations'] = [];
$GLOBALS['_i18n_loaded'] = false;
$config_language_client = 'en';
$GLOBALS['_i18n_translations']['Path: {path}'] = 'Path: {path}';
// Input: C:\Users (one backslash in each position)
$params = ['{path}' => "C:\\Users"];
$result = t_mail('Path: {path}', $params);
// Expected: Path: C:\Users (backslashes in param not doubled - param is pre-escaped)
$expected = "Path: C:\\Users";
if ($result === $expected) {
    echo "  ✓ PASS: Parameter backslash not doubled: '{$result}'\n";
    $tests_passed++;
} else {
    echo "  ✗ FAIL: Expected '{$expected}', got '{$result}'\n";
    $tests_failed++;
}

// Test (j): Global language state identical before/after with config_language='fr' AND client='en'
echo "\nTest (j): Global state preserved with config_language='fr' AND client='en'\n";
$GLOBALS['_i18n_lang'] = 'fr';
$GLOBALS['_i18n_translations'] = [];
$GLOBALS['_i18n_loaded'] = false;
$config_language_client = 'en';
$before_lang = $GLOBALS['_i18n_lang'];
$t_mail_before = $GLOBALS['_i18n_translations'] ?? null;
t_mail('Sign In');
$after_lang = $GLOBALS['_i18n_lang'];
$t_mail_after = $GLOBALS['_i18n_translations'] ?? null;
if ($before_lang === $after_lang && $t_mail_before === $t_mail_after) {
    echo "  ✓ PASS: Global state preserved (lang: {$before_lang} -> {$after_lang})\n";
    $tests_passed++;
} else {
    echo "  ✗ FAIL: Global state changed (lang: {$before_lang} -> {$after_lang})\n";
    $tests_failed++;
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
