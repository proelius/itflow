<?php
/**
 * i18n Keys Checker
 * 
 * Validates that all t() calls use keys present in both lang/en.php and lang/fr.php.
 * Reports unused keys and missing keys.
 * 
 * Usage: php scripts/i18n_check_keys.php
 * 
 * Security: Must be run from CLI only (scripts/ is web-accessible).
 */

if (PHP_SAPI !== 'cli') {
    exit('This script must be run from command line only.');
}

// Get ITFlow root directory
$itflow_dir = dirname(__DIR__);
$lang_en_file = $itflow_dir . '/lang/en.php';
$lang_fr_file = $itflow_dir . '/lang/fr.php';
$exceptions_file = $itflow_dir . '/scripts/i18n_exceptions.php';

echo "=== i18n Keys Checker ===\n\n";

// Load language files
if (!file_exists($lang_en_file)) {
    echo "ERROR: lang/en.php not found\n";
    exit(1);
}
if (!file_exists($lang_fr_file)) {
    echo "ERROR: lang/fr.php not found\n";
    exit(1);
}

$en_keys = array_keys(require $lang_en_file);
$fr_keys = array_keys(require $lang_fr_file);

echo "Keys in en.php: " . count($en_keys) . "\n";
echo "Keys in fr.php: " . count($fr_keys) . "\n\n";

// Check that en.php and fr.php have the same keys
$en_set = array_flip($en_keys);
$fr_set = array_flip($fr_keys);

$missing_in_fr = array_diff($en_keys, $fr_keys);
$missing_in_en = array_diff($fr_keys, $en_keys);

if (!empty($missing_in_fr)) {
    echo "ERROR: Keys in en.php but missing in fr.php:\n";
    foreach ($missing_in_fr as $key) {
        echo "  - '{$key}'\n";
    }
    exit(1);
}

if (!empty($missing_in_en)) {
    echo "ERROR: Keys in fr.php but missing in en.php:\n";
    foreach ($missing_in_en as $key) {
        echo "  - '{$key}'\n";
    }
    exit(1);
}

echo "✓ en.php and fr.php have identical keys\n\n";

// Check php -l on language files
echo "Checking PHP syntax on language files...\n";
$en_lint = shell_exec('php -l ' . escapeshellarg($lang_en_file) . ' 2>&1');
$fr_lint = shell_exec('php -l ' . escapeshellarg($lang_fr_file) . ' 2>&1');

if (strpos($en_lint, 'No syntax errors') === false) {
    echo "ERROR: lang/en.php has syntax errors:\n{$en_lint}\n";
    exit(1);
}
if (strpos($fr_lint, 'No syntax errors') === false) {
    echo "ERROR: lang/fr.php has syntax errors:\n{$fr_lint}\n";
    exit(1);
}
echo "✓ PHP syntax valid on both language files\n\n";

// Load exceptions
$exceptions = [];
if (file_exists($exceptions_file)) {
    $exceptions = require $exceptions_file;
    if (!is_array($exceptions)) {
        $exceptions = [];
    }
}

// Scan PHP files for t() calls using token_get_all
echo "Scanning PHP files for t() calls...\n\n";

$used_keys = [];
$files_scanned = 0;
$files_with_t = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($itflow_dir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    
    $relative_path = str_replace($itflow_dir . '/', '', $file->getPathname());
    
    // Skip libs/ and vendor/
    if (strpos($relative_path, 'libs/') === 0 || strpos($relative_path, 'vendor/') === 0) {
        continue;
    }
    
    // Skip test files
    if (strpos($relative_path, 'i18n_selftest_mail.php') !== false) {
        continue;
    }
    
    $files_scanned++;
    
    $content = file_get_contents($file->getPathname());
    if ($content === false) {
        continue;
    }
    
    $tokens = token_get_all($content);
    $count = count($tokens);
    
    for ($i = 0; $i < $count; $i++) {
        // Look for function call 't' or 't_mail'
        if (is_array($tokens[$i]) && $tokens[$i][0] === T_STRING && ($tokens[$i][1] === 't' || $tokens[$i][1] === 't_mail')) {
            // Check if followed by '('
            $j = $i + 1;
            while ($j < $count && in_array($tokens[$j], [' ', "\t", "\n", "\r"])) {
                $j++;
            }
            
            if ($j < $count && $tokens[$j] === '(') {
                // Found t( or t_mail( call - extract first argument
                $j++;
                // Skip whitespace
                while ($j < $count && in_array($tokens[$j], [' ', "\t", "\n", "\r"])) {
                    $j++;
                }
                
                $first_arg = null;
                
                // Check for string literal (T_CONSTANT_ENCAPSED_STRING)
                if ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_CONSTANT_ENCAPSED_STRING) {
                    $first_arg = $tokens[$j][1];
                }
                
                if ($first_arg !== null) {
                    // Remove quotes from the string
                    $key = substr($first_arg, 1, -1);
                    
                    // Handle escaped quotes (both single and double)
                    $key = str_replace("\\'", "'", $key);
                    $key = str_replace('\\"', '"', $key);
                    $key = str_replace("\\\\'", "'", $key);
                    
                    $used_keys[$key] = $relative_path;
                }
            }
        }
    }
}

echo "Files scanned: {$files_scanned}\n";
echo "Keys used in t() calls: " . count($used_keys) . "\n\n";

// Guard: must find at least 40 t() calls
if (count($used_keys) < 40) {
    echo "ERROR: Found only " . count($used_keys) . " t() calls (minimum 40 required)\n";
    exit(1);
}

echo "✓ Found " . count($used_keys) . " t() calls (minimum 40 required)\n\n";

// Check for unknown keys (used but not in language files)
$unknown_keys = [];
foreach ($used_keys as $key => $file) {
    if (!in_array($key, $en_keys)) {
        // Check if it's an exception
        if (!in_array($key, $exceptions)) {
            $unknown_keys[] = "{$file}: '{$key}'";
        }
    }
}

if (!empty($unknown_keys)) {
    echo "ERROR: Unknown keys (used but not in lang files):\n";
    foreach ($unknown_keys as $item) {
        echo "  ✗ {$item}\n";
    }
    exit(1);
}

echo "✓ All t() keys found in lang files\n\n";

// Check for unused keys
$unused_keys = [];
foreach ($en_keys as $key) {
    if (!isset($used_keys[$key])) {
        $unused_keys[] = $key;
    }
}

if (!empty($unused_keys)) {
    echo "ERROR: Keys defined but not used in t() calls:\n";
    foreach ($unused_keys as $key) {
        echo "  • '{$key}'\n";
    }
    exit(1);
} else {
    echo "✓ All keys are used\n\n";
}

// Check fr.php values for forbidden characters: ', ", or backslash
echo "Checking fr.php for forbidden characters in values...\n";
$fr_raw = file_get_contents($lang_fr_file);

// Look for forbidden patterns in values (after =>)
$lines = explode("\n", $fr_raw);
$forbidden_errors = [];
foreach ($lines as $line_num => $line) {
    // Skip empty lines and comments
    if (trim($line) === '' || strpos(trim($line), '//') === 0 || strpos(trim($line), '/*') === 0) {
        continue;
    }
    
    // Find lines with => (translation lines)
    if (strpos($line, "=>") !== false) {
        // Extract the value part (after =>)
        $parts = explode("=>", $line);
        if (count($parts) >= 2) {
            $value_part = trim($parts[1]);
            // Value should start with ' and end with ', possibly followed by ,
            if (preg_match("/^'(.*)',?$/", $value_part, $value_matches)) {
                $value_content = $value_matches[1];
                // Check for forbidden characters
                // Straight apostrophe ' (not escaped as \')
                if (preg_match("/(?<!\\\\\\\\)'/", $value_content)) {
                    $key_part = $parts[0];
                    preg_match("/'([^']*)'/", $key_part, $key_matches);
                    $key = $key_matches[1] ?? 'unknown';
                    $forbidden_errors[] = "Line {$line_num}: Key '{$key}' contains unescaped apostrophe '";
                }
                // Double quote "
                if (strpos($value_content, '"') !== false) {
                    $key_part = $parts[0];
                    preg_match("/'([^']*)'/", $key_part, $key_matches);
                    $key = $key_matches[1] ?? 'unknown';
                    $forbidden_errors[] = "Line {$line_num}: Key '{$key}' contains double quote \"";
                }
                // Backslash \ (except as escape for apostrophe)
                if (preg_match("/\\\\(?![\\\\'])/", $value_content)) {
                    $key_part = $parts[0];
                    preg_match("/'([^']*)'/", $key_part, $key_matches);
                    $key = $key_matches[1] ?? 'unknown';
                    $forbidden_errors[] = "Line {$line_num}: Key '{$key}' contains backslash";
                }
            }
        }
    }
}

if (!empty($forbidden_errors)) {
    echo "ERROR: fr.php contains forbidden characters in values:\n";
    foreach ($forbidden_errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}

echo "✓ No forbidden characters in fr.php values\n\n";

// Negative test: verify detection logic works on in-memory data
echo "Running negative test (in-memory check)...\n";
$test_key = 'NEGATIVE_TEST_KEY_DO_NOT_USE';
$test_value = "This has a bad ' apostrophe";
$test_line = "    '{$test_key}' => '{$test_value}',";

// Test the detection logic on in-memory data (no file writes)
$lines_test = [$test_line];
$negative_test_passed = false;

foreach ($lines_test as $line_num => $line) {
    if (strpos($line, "=>") !== false && strpos($line, $test_key) !== false) {
        $parts = explode("=>", $line);
        if (count($parts) >= 2) {
            $value_part = trim($parts[1]);
            if (preg_match("/^'(.*)',?$/", $value_part, $value_matches)) {
                $value_content = $value_matches[1];
                if (preg_match("/(?<!\\\\\\\\)'/", $value_content)) {
                    $negative_test_passed = true;
                    echo "✓ Negative test passed: script correctly detects bad apostrophe\n";
                    break;
                }
            }
        }
    }
}

if (!$negative_test_passed) {
    echo "ERROR: Negative test failed - detection logic did not find inserted bad value\n";
    exit(1);
}

echo "\n";

// Check fr.php values for right apostrophe ' in raw file (should use \' only)
echo "Checking fr.php for unescaped right apostrophe ' in values...\n";
$fr_raw = file_get_contents($lang_fr_file);

// Look for pattern: => 'text with unescaped ' in middle'
// We need to find apostrophes that are NOT preceded by backslash AND are inside the string
$apostrophe_errors = [];
$lines = explode("\n", $fr_raw);
foreach ($lines as $line_num => $line) {
    // Skip empty lines and comments
    if (trim($line) === '' || strpos(trim($line), '//') === 0 || strpos(trim($line), '/*') === 0) {
        continue;
    }
    
    // Find lines with => (translation lines)
    if (strpos($line, "=>") !== false) {
        // Extract the value part (after =>)
        $parts = explode("=>", $line);
        if (count($parts) >= 2) {
            $value_part = trim($parts[1]);
            // Value should start with ' and end with ', possibly followed by ,
            // Check for apostrophe inside the value (not at start/end)
            if (preg_match("/^'(.*)',?$/", $value_part, $value_matches)) {
                $value_content = $value_matches[1];
                // Check for unescaped apostrophe inside the value
                // Pattern: apostrophe not preceded by backslash
                if (preg_match("/(?<!\\\\)'/", $value_content)) {
                    // Extract key for error message
                    $key_part = $parts[0];
                    preg_match("/'([^']*)'/", $key_part, $key_matches);
                    $key = $key_matches[1] ?? 'unknown';
                    $apostrophe_errors[] = "Line {$line_num}: Key '{$key}' has unescaped apostrophe in value";
                }
            }
        }
    }
}

if (!empty($apostrophe_errors)) {
    echo "ERROR: fr.php contains unescaped right apostrophe ' in values (should use \\' only):\n";
    foreach ($apostrophe_errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}

echo "✓ No unescaped right apostrophe ' in fr.php values\n\n";

// Test (a): Check that en.php values match keys (English = key)
echo "Checking en.php values match keys (English text should equal key)...\n";
$en_raw = file_get_contents($lang_en_file);
$en_lines = explode("\n", $en_raw);
$en_value_errors = [];

foreach ($en_lines as $line_num => $line) {
    // Skip empty lines and comments
    if (trim($line) === '' || strpos(trim($line), '//') === 0 || strpos(trim($line), '/*') === 0) {
        continue;
    }
    
    // Find lines with => (translation lines)
    if (strpos($line, "=>") !== false) {
        // Extract key and value
        if (preg_match("/'([^']*)'\s*=>\s*'([^']*)',?$/", $line, $matches)) {
            $key = $matches[1];
            $value = $matches[2];
            // For English, value should equal key
            if ($key !== $value) {
                $en_value_errors[] = "Line {$line_num}: Key '{$key}' has value '{$value}' (should be '{$key}')";
            }
        }
    }
}

if (!empty($en_value_errors)) {
    echo "ERROR: en.php values must match keys (English text = key):\n";
    foreach ($en_value_errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}

echo "✓ All en.php values match keys\n\n";

// Test (b): Check for duplicate keys in en.php and fr.php
echo "Checking for duplicate keys in lang files...\n";
$en_key_counts = [];
$fr_key_counts = [];

// Count keys in en.php
foreach ($en_lines as $line) {
    if (preg_match("/'([^']*)'\s*=>/", $line, $matches)) {
        $key = $matches[1];
        $en_key_counts[$key] = ($en_key_counts[$key] ?? 0) + 1;
    }
}

// Count keys in fr.php
$fr_lines = explode("\n", $fr_raw);
foreach ($fr_lines as $line) {
    if (preg_match("/'([^']*)'\s*=>/", $line, $matches)) {
        $key = $matches[1];
        $fr_key_counts[$key] = ($fr_key_counts[$key] ?? 0) + 1;
    }
}

$duplicate_errors = [];
foreach ($en_key_counts as $key => $count) {
    if ($count > 1) {
        $duplicate_errors[] = "en.php: Key '{$key}' defined {$count} times";
    }
}
foreach ($fr_key_counts as $key => $count) {
    if ($count > 1) {
        $duplicate_errors[] = "fr.php: Key '{$key}' defined {$count} times";
    }
}

if (!empty($duplicate_errors)) {
    echo "ERROR: Duplicate keys found:\n";
    foreach ($duplicate_errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}

echo "✓ No duplicate keys in lang files\n\n";

// Negative test (a): verify en.php value check detection
echo "Running negative test (a) - en.php value mismatch detection...\n";
$test_line_a = "    'TEST_KEY_MISMATCH' => 'Wrong Value',";
$test_lines_a = [$test_line_a];
$negative_test_a_passed = false;

foreach ($test_lines_a as $line) {
    if (preg_match("/'([^']*)'\s*=>\s*'([^']*)',?$/", $line, $matches)) {
        $key = $matches[1];
        $value = $matches[2];
        if ($key !== $value) {
            $negative_test_a_passed = true;
            echo "✓ Negative test (a) passed: script correctly detects value mismatch\n";
            break;
        }
    }
}

if (!$negative_test_a_passed) {
    echo "ERROR: Negative test (a) failed - did not detect value mismatch\n";
    exit(1);
}

// Negative test (b): verify duplicate key detection
echo "Running negative test (b) - duplicate key detection...\n";
$test_lines_b = [
    "    'DUPLICATE_KEY' => 'DUPLICATE_KEY',",
    "    'DUPLICATE_KEY' => 'DUPLICATE_KEY',",
];
$test_counts = [];
$negative_test_b_passed = false;

foreach ($test_lines_b as $line) {
    if (preg_match("/'([^']*)'\s*=>/", $line, $matches)) {
        $key = $matches[1];
        $test_counts[$key] = ($test_counts[$key] ?? 0) + 1;
    }
}

foreach ($test_counts as $key => $count) {
    if ($count > 1) {
        $negative_test_b_passed = true;
        echo "✓ Negative test (b) passed: script correctly detects duplicate keys\n";
        break;
    }
}

if (!$negative_test_b_passed) {
    echo "ERROR: Negative test (b) failed - did not detect duplicate keys\n";
    exit(1);
}

echo "\n";

echo "=== All checks passed ===\n";
exit(0);
