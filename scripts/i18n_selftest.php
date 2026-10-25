<?php
/**
 * i18n_selftest.php
 * 
 * Auto-test de la fonctionnalité i18n.
 * Échoue avec la liste des erreurs s'il y a un écart.
 * 
 * Usage: php scripts/i18n_selftest.php
 */

// Stop if accessed via web (scripts/ is web-accessible)
if (PHP_SAPI !== 'cli') {
    exit;
}

$itflow_dir = __DIR__ . '/..';
$errors = [];

// Load i18n functions
require_once $itflow_dir . '/functions/i18n.php';

echo "=== i18n Self-Test ===\n\n";

// Test 1: i18n_normalize_lang
echo "Test 1: i18n_normalize_lang()\n";
$normalize_tests = [
    ['fr_CH', 'fr'],
    ['FR', 'fr'],
    ['fr-FR', 'fr'],
    ['fr_FR', 'fr'],
    ['xx', 'en'],
    ['', 'en'],
    [null, 'en'],
    ['en_US', 'en'],
    ['EN', 'en'],
];

foreach ($normalize_tests as $test) {
    list($input, $expected) = $test;
    $result = _i18n_normalize_lang($input);
    if ($result !== $expected) {
        $errors[] = "i18n_normalize_lang(" . var_export($input, true) . ") = '{$result}' (expected '{$expected}')";
        echo "  ✗ i18n_normalize_lang(" . var_export($input, true) . ") = '{$result}' (expected '{$expected}')\n";
    } else {
        echo "  ✓ i18n_normalize_lang(" . var_export($input, true) . ") = '{$result}'\n";
    }
}
echo "\n";

// Test 2: client_language()
echo "Test 2: client_language()\n";
$config_language_client = 'fr_CH';
$result = client_language();
if ($result !== 'fr') {
    $errors[] = "client_language() with \$config_language_client='fr_CH' = '{$result}' (expected 'fr')";
    echo "  ✗ client_language() with \$config_language_client='fr_CH' = '{$result}' (expected 'fr')\n";
} else {
    echo "  ✓ client_language() with \$config_language_client='fr_CH' = '{$result}'\n";
}
echo "\n";

// Test 3: t() with $config_language='en'
echo "Test 3: t() with \$config_language='en'\n";
$config_language = 'en';
$GLOBALS['_i18n_loaded'] = false;
$GLOBALS['_i18n_lang'] = 'en';
$GLOBALS['_i18n_translations'] = [];
$result = t('Sign In');
if ($result !== 'Sign In') {
    $errors[] = "t('Sign In') with \$config_language='en' = '{$result}' (expected 'Sign In')";
    echo "  ✗ t('Sign In') with \$config_language='en' = '{$result}' (expected 'Sign In')\n";
} else {
    echo "  ✓ t('Sign In') with \$config_language='en' = '{$result}'\n";
}
echo "\n";

// Test 4: i18n_with('fr', ...)
echo "Test 4: i18n_with('fr', fn() => t('Sign In'))\n";
$config_language = 'en';
$GLOBALS['_i18n_loaded'] = false;
$GLOBALS['_i18n_lang'] = 'en';
$GLOBALS['_i18n_translations'] = [];

$result = i18n_with('fr', function() {
    return t('Sign In');
});

if ($result !== 'Se connecter') {
    $errors[] = "i18n_with('fr', fn() => t('Sign In')) = '{$result}' (expected 'Se connecter')";
    echo "  ✗ i18n_with('fr', fn() => t('Sign In')) = '{$result}' (expected 'Se connecter')\n";
} else {
    echo "  ✓ i18n_with('fr', fn() => t('Sign In')) = '{$result}'\n";
}

// Check that language was restored
$after_lang = _i18n_get_lang();
if ($after_lang !== 'en') {
    $errors[] = "After i18n_with('fr', ...), t('Sign In') = '{$result}' (expected 'Sign In' after restoration)";
    echo "  ✗ After i18n_with('fr', ...), language = '{$after_lang}' (expected 'en')\n";
} else {
    echo "  ✓ After i18n_with('fr', ...), language restored to 'en'\n";
}

// Test exception handling
echo "  Testing exception handling...\n";
try {
    i18n_with('fr', function() {
        throw new Exception("Test exception");
    });
} catch (Exception $e) {
    // Expected
}

$after_exception_lang = _i18n_get_lang();
if ($after_exception_lang !== 'en') {
    $errors[] = "After exception in i18n_with('fr', ...), language = '{$after_exception_lang}' (expected 'en')";
    echo "  ✗ After exception in i18n_with('fr', ...), language = '{$after_exception_lang}' (expected 'en')\n";
} else {
    echo "  ✓ After exception in i18n_with('fr', ...), language restored to 'en'\n";
}
echo "\n";

// Test 5: lang/fr.php apostrophe check
echo "Test 5: lang/fr.php apostrophe check (no right apostrophe ') in values)\n";
$fr_file = $itflow_dir . '/lang/fr.php';
if (file_exists($fr_file)) {
    $fr_translations = require $fr_file;
    $apostrophe_errors = [];
    foreach ($fr_translations as $key => $value) {
        // Check for right apostrophe ' in values (should use ' only)
        if (strpos($value, "'") !== false) {
            $apostrophe_errors[] = "Key '{$key}': value contains right apostrophe '";
        }
    }
    
    if (empty($apostrophe_errors)) {
        echo "  ✓ No right apostrophe ' in lang/fr.php values\n";
    } else {
        foreach ($apostrophe_errors as $error) {
            $errors[] = "lang/fr.php: {$error}";
            echo "  ✗ {$error}\n";
        }
    }
} else {
    $errors[] = "lang/fr.php not found";
    echo "  ✗ lang/fr.php not found\n";
}
echo "\n";

// Test 6: lang/en.php and lang/fr.php have same keys
echo "Test 6: lang/en.php and lang/fr.php have exactly the same keys\n";
$en_file = $itflow_dir . '/lang/en.php';
$fr_file = $itflow_dir . '/lang/fr.php';

if (file_exists($en_file) && file_exists($fr_file)) {
    $en_translations = require $en_file;
    $fr_translations = require $fr_file;
    
    $en_keys = array_keys($en_translations);
    $fr_keys = array_keys($fr_translations);
    
    sort($en_keys);
    sort($fr_keys);
    
    if ($en_keys === $fr_keys) {
        echo "  ✓ Both files have exactly " . count($en_keys) . " keys (identical)\n";
    } else {
        $missing_in_fr = array_diff($en_keys, $fr_keys);
        $extra_in_fr = array_diff($fr_keys, $en_keys);
        
        if (!empty($missing_in_fr)) {
            $errors[] = "Keys in en.php but not in fr.php: " . implode(', ', $missing_in_fr);
            echo "  ✗ Keys in en.php but not in fr.php: " . implode(', ', $missing_in_fr) . "\n";
        }
        if (!empty($extra_in_fr)) {
            $errors[] = "Keys in fr.php but not in en.php: " . implode(', ', $extra_in_fr);
            echo "  ✗ Keys in fr.php but not in en.php: " . implode(', ', $extra_in_fr) . "\n";
        }
    }
} else {
    $errors[] = "lang/en.php or lang/fr.php not found";
    echo "  ✗ lang/en.php or lang/fr.php not found\n";
}
echo "\n";

// Final summary
echo "=== Summary ===\n";
if (empty($errors)) {
    echo "✓ All tests passed!\n";
    exit(0);
} else {
    echo "✗ " . count($errors) . " error(s) found:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
    exit(1);
}
