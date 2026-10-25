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
    
    $files_scanned++;
    
    $content = file_get_contents($file->getPathname());
    if ($content === false) {
        continue;
    }
    
    $tokens = token_get_all($content);
    $count = count($tokens);
    
    for ($i = 0; $i < $count; $i++) {
        // Look for function call 't'
        if ($tokens[$i] === T_STRING && $tokens[$i][1] === 't') {
            // Check if followed by '('
            $j = $i + 1;
            while ($j < $count && in_array($tokens[$j], [' ', "\t", "\n", "\r"])) {
                $j++;
            }
            
            if ($j < $count && $tokens[$j] === '(') {
                // Found t( call - extract first argument
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
                    
                    // Handle escaped quotes
                    $key = str_replace("\\'", "'", $key);
                    
                    $used_keys[$key] = $relative_path;
                }
            }
        }
    }
}

echo "Files scanned: {$files_scanned}\n";
echo "Keys used in t() calls: " . count($used_keys) . "\n\n";

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
    echo "WARNING: Keys defined but not used in t() calls:\n";
    foreach ($unused_keys as $key) {
        echo "  • '{$key}'\n";
    }
    echo "\n";
} else {
    echo "✓ All keys are used\n\n";
}

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

echo "=== All checks passed ===\n";
exit(0);
