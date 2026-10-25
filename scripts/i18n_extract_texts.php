<?php
/**
 * i18n_extract_texts.php
 * 
 * Extrait tous les textes visibles traduisibles (t('...')) du code.
 * Exclut automatiquement les textes contenant des variables.
 * 
 * Usage: php scripts/i18n_extract_texts.php
 */

// Stop if accessed via web (scripts/ is web-accessible)
if (PHP_SAPI !== 'cli') {
    exit;
}

$itflow_dir = __DIR__ . '/..';

// Find all PHP files (excluding vendor, libs, .git, uploads, node_modules)
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
            strpos($path, '/node_modules/') !== false) {
            continue;
        }
        $files[] = $path;
    }
}

echo "=== i18n Text Extractor ===\n";
echo "Extracting all t() calls from code...\n\n";

$all_texts = [];
$excluded = [];

foreach ($files as $file) {
    $relative_path = str_replace($itflow_dir . '/', '', $file);
    
    // Skip this script itself
    if (strpos($relative_path, 'scripts/i18n_extract_texts.php') !== false) {
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Extract all t('...') calls
    preg_match_all("/t\(['\"]([^'\"]+)['\"]\)/", $content, $matches);
    
    foreach ($matches[1] as $text) {
        // Skip if text contains variables (like agent/$config_start_page)
        if (strpos($text, '$') !== false) {
            $excluded[] = [
                'file' => $relative_path,
                'text' => $text,
                'reason' => 'Contains variable ($)'
            ];
            continue;
        }
        
        // Skip empty strings
        if (trim($text) === '') {
            continue;
        }
        
        $all_texts[] = [
            'file' => $relative_path,
            'text' => $text
        ];
    }
}

// Sort by file, then by text
usort($all_texts, function($a, $b) {
    $cmp = strcmp($a['file'], $b['file']);
    if ($cmp !== 0) return $cmp;
    return strcmp($a['text'], $b['text']);
});

echo "Visible texts found: " . count($all_texts) . "\n";
echo "Excluded (variables): " . count($excluded) . "\n\n";

if (!empty($excluded)) {
    echo "=== Excluded texts (contain variables) ===\n";
    foreach ($excluded as $ex) {
        echo "  {$ex['file']}: {$ex['text']} ({$ex['reason']})\n";
    }
    echo "\n";
}

echo "=== All visible t() calls ===\n";
$current_file = '';
foreach ($all_texts as $item) {
    if ($item['file'] !== $current_file) {
        $current_file = $item['file'];
        echo "\n{$current_file}:\n";
    }
    echo "  - {$item['text']}\n";
}

// Output as JSON for further processing
$json_output = [
    'visible_texts' => $all_texts,
    'excluded_texts' => $excluded,
    'summary' => [
        'total_visible' => count($all_texts),
        'total_excluded' => count($excluded)
    ]
];

file_put_contents(__DIR__ . '/../i18n_extracted_texts.json', json_encode($json_output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n\nJSON output saved to: i18n_extracted_texts.json\n";
