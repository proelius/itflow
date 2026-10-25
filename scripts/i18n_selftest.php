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

// Test 3: $config_language='fr' context
echo "Test 3: \$config_language='fr' context\n";
$config_language = 'fr';
$GLOBALS['_i18n_lang'] = null;
$GLOBALS['_i18n_translations'] = [];
$GLOBALS['_i18n_loaded'] = false;

$result = t('Sign In');
if ($result !== 'Se connecter') {
    $errors[] = "t('Sign In') with \$config_language='fr' = '{$result}' (expected 'Se connecter')";
    echo "  ✗ t('Sign In') with \$config_language='fr' = '{$result}' (expected 'Se connecter')\n";
} else {
    echo "  ✓ t('Sign In') with \$config_language='fr' = '{$result}'\n";
}

// i18n_with('en', ...) should return English
$result = i18n_with('en', function() {
    return t('Sign In');
});

if ($result !== 'Sign In') {
    $errors[] = "i18n_with('en', fn() => t('Sign In')) with \$config_language='fr' = '{$result}' (expected 'Sign In')";
    echo "  ✗ i18n_with('en', fn() => t('Sign In')) with \$config_language='fr' = '{$result}' (expected 'Sign In')\n";
} else {
    echo "  ✓ i18n_with('en', fn() => t('Sign In')) with \$config_language='fr' = '{$result}'\n";
}

// After i18n_with, should return to French
$result = t('Sign In');
if ($result !== 'Se connecter') {
    $errors[] = "After i18n_with('en', ...), t('Sign In') = '{$result}' (expected 'Se connecter')";
    echo "  ✗ After i18n_with('en', ...), t('Sign In') = '{$result}' (expected 'Se connecter')\n";
} else {
    echo "  ✓ After i18n_with('en', ...), t('Sign In') = '{$result}'\n";
}
echo "\n";

// Test 4: $config_language='en' with i18n_with('fr', ...)
echo "Test 4: \$config_language='en' with i18n_with('fr', ...)\n";
$config_language = 'en';
$GLOBALS['_i18n_lang'] = null;
$GLOBALS['_i18n_translations'] = [];
$GLOBALS['_i18n_loaded'] = false;

$result = i18n_with('fr', function() {
    return t('Sign In');
});

if ($result !== 'Se connecter') {
    $errors[] = "i18n_with('fr', fn() => t('Sign In')) with \$config_language='en' = '{$result}' (expected 'Se connecter')";
    echo "  ✗ i18n_with('fr', fn() => t('Sign In')) with \$config_language='en' = '{$result}' (expected 'Se connecter')\n";
} else {
    echo "  ✓ i18n_with('fr', fn() => t('Sign In')) with \$config_language='en' = '{$result}'\n";
}

// Check that language was restored to English
$after_lang = _i18n_get_lang();
$result = t('Sign In');
if ($after_lang !== 'en' || $result !== 'Sign In') {
    $errors[] = "After i18n_with('fr', ...) with \$config_language='en', language = '{$after_lang}' and t('Sign In') = '{$result}' (expected 'en' and 'Sign In')";
    echo "  ✗ After i18n_with('fr', ...) with \$config_language='en', language = '{$after_lang}' and t('Sign In') = '{$result}' (expected 'en' and 'Sign In')\n";
} else {
    echo "  ✓ After i18n_with('fr', ...) with \$config_language='en', language restored to 'en' and t('Sign In') = '{$result}'\n";
}

// Test exception handling
echo "  Testing exception handling...\n";
$config_language = 'en';
$GLOBALS['_i18n_lang'] = null;
$GLOBALS['_i18n_translations'] = [];
$GLOBALS['_i18n_loaded'] = false;

try {
    i18n_with('fr', function() {
        throw new Exception("Test exception");
    });
} catch (Exception $e) {
    // Expected
}

$after_exception_lang = _i18n_get_lang();
$result = t('Sign In');
if ($after_exception_lang !== 'en' || $result !== 'Sign In') {
    $errors[] = "After exception in i18n_with('fr', ...), language = '{$after_exception_lang}' and t('Sign In') = '{$result}' (expected 'en' and 'Sign In')";
    echo "  ✗ After exception in i18n_with('fr', ...), language = '{$after_exception_lang}' and t('Sign In') = '{$result}' (expected 'en' and 'Sign In')\n";
} else {
    echo "  ✓ After exception in i18n_with('fr', ...), language restored to 'en' and t('Sign In') = '{$result}'\n";
}
echo "\n";

// Test 5: i18n_set_language('fr')
echo "Test 5: i18n_set_language('fr')\n";
$config_language = 'en';
$GLOBALS['_i18n_lang'] = null;
$GLOBALS['_i18n_translations'] = [];
$GLOBALS['_i18n_loaded'] = false;

i18n_set_language('fr');
$result = t('Sign In');

if ($result !== 'Se connecter') {
    $errors[] = "After i18n_set_language('fr'), t('Sign In') = '{$result}' (expected 'Se connecter')";
    echo "  ✗ After i18n_set_language('fr'), t('Sign In') = '{$result}' (expected 'Se connecter')\n";
} else {
    echo "  ✓ After i18n_set_language('fr'), t('Sign In') = '{$result}'\n";
}
echo "\n";

// Test 6: lang/fr.php apostrophe check
echo "Test 6: lang/fr.php apostrophe check (no right apostrophe ' in values)\n";
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

// Test 7: lang/en.php and lang/fr.php have same keys
echo "Test 7: lang/en.php and lang/fr.php have exactly the same keys\n";
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

// Test 8: No hardcoded 'fr' in functions/i18n.php (outside comments)
echo "Test 8: No hardcoded 'fr' in functions/i18n.php (outside comments)\n";
$i18n_file = $itflow_dir . '/functions/i18n.php';
if (file_exists($i18n_file)) {
    $content = file_get_contents($i18n_file);
    
    // Remove comments (single-line // and multi-line /* */)
    $content_no_comments = preg_replace('/\/\/.*$/m', '', $content);
    $content_no_comments = preg_replace('/\/\*.*?\*\//s', '', $content_no_comments);
    
    // Check for 'fr' in single quotes (not in strings like 'fr_CH' which is OK as input)
    // We're looking for hardcoded language values like: 'fr' => or = 'fr'
    if (preg_match("/['\"]fr['\"]/", $content_no_comments)) {
        $errors[] = "functions/i18n.php contains hardcoded 'fr' outside comments";
        echo "  ✗ functions/i18n.php contains hardcoded 'fr' outside comments\n";
    } else {
        echo "  ✓ No hardcoded 'fr' in functions/i18n.php (outside comments)\n";
    }
} else {
    $errors[] = "functions/i18n.php not found";
    echo "  ✗ functions/i18n.php not found\n";
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
