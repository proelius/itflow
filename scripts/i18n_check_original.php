<?php
/**
 * i18n_check_original.php
 * 
 * Vérifie que chaque texte passé à t() dans le code est IDENTIQUE
 * au texte d'origine de master (au caractère près).
 * 
 * Usage: php scripts/i18n_check_original.php
 */

// Stop if accessed via web (scripts/ is web-accessible)
if (PHP_SAPI !== 'cli') {
    exit;
}

$itflow_dir = __DIR__ . '/..';

echo "=== i18n Original Text Checker ===\n";
echo "Checking that t() calls match original master texts...\n\n";

// Get original texts from master
$master_texts = [];
exec("cd {$itflow_dir} && git show master:functions/i18n.php 2>/dev/null | grep -E \"t\\(['\\\"]\" | sed -E \"s/.*t\\(['\\\"]([^'\\\"]+)['\\\"].*/\\1/\" 2>/dev/null", $master_texts, $exit_code);

// If master doesn't have functions/i18n.php, use lang/en.php as reference
if (empty($master_texts)) {
    $en_file = $itflow_dir . '/lang/en.php';
    if (file_exists($en_file)) {
        $translations = require $en_file;
        $master_texts = array_keys($translations);
    }
}

// Find all PHP files
$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($itflow_dir, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();
        if (strpos($path, '/vendor/') !== false ||
            strpos($path, '/libs/') !== false ||
            strpos($path, '/.git/') !== false ||
            strpos($path, '/uploads/') !== false ||
            strpos($path, '/node_modules/') !== false ||
            strpos($path, '/scripts/i18n_') !== false) {
            continue;
        }
        $files[] = $path;
    }
}

$all_texts = [];
$non_text_modifications = [];

foreach ($files as $file) {
    $relative_path = str_replace($itflow_dir . '/', '', $file);
    $content = file_get_contents($file);
    $lines = explode("\n", $content);
    
    // Extract all t('...') calls with line numbers
    foreach ($lines as $line_num => $line) {
        preg_match_all("/t\(['\"]([^'\"]+)['\"]\)/", $line, $matches);
        foreach ($matches[1] as $text) {
            $all_texts[] = [
                'file' => $relative_path,
                'line' => $line_num + 1,
                'text' => $text
            ];
        }
    }
    
    // Check for non-t() modifications (lines that were added/removed)
    exec("cd {$itflow_dir} && git diff master -- {$relative_path} 2>/dev/null | grep -E '^[+-]' | grep -v '^[+-]{3}' | grep -v \"t\\(['\\\"]\" 2>/dev/null", $diff_lines, $exit_code);
    
    if (!empty($diff_lines)) {
        $non_text_modifications[] = [
            'file' => $relative_path,
            'lines' => $diff_lines
        ];
    }
    
    // Clear diff_lines for next file
    $diff_lines = [];
}

// Check for unknown texts (not in master)
$unknown_texts = [];
foreach ($all_texts as $item) {
    if (!in_array($item['text'], $master_texts)) {
        $unknown_texts[] = $item;
    }
}

echo "=== Summary ===\n";
echo "Total t() calls found: " . count($all_texts) . "\n";
echo "Unknown texts (not in master): " . count($unknown_texts) . "\n";
echo "Files with non-t() modifications: " . count($non_text_modifications) . "\n\n";

$errors = [];

if (!empty($unknown_texts)) {
    echo "=== Unknown texts (not in master lang/en.php) ===\n";
    foreach ($unknown_texts as $item) {
        $errors[] = "Unknown: {$item['file']}:{$item['line']} - '{$item['text']}'";
        echo "  {$item['file']}:{$item['line']} - '{$item['text']}'\n";
    }
    echo "\n";
}

if (!empty($non_text_modifications)) {
    echo "=== Non-t() modifications detected ===\n";
    foreach ($non_text_modifications as $mod) {
        $errors[] = "Non-t() modification: {$mod['file']}";
        echo "  {$mod['file']}:\n";
        foreach ($mod['lines'] as $line) {
            echo "    {$line}\n";
        }
    }
    echo "\n";
}

if (empty($errors)) {
    echo "✓ All t() calls match original master texts.\n";
    echo "✓ No non-t() modifications detected.\n";
    exit(0);
} else {
    echo "=== ERRORS ===\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}
