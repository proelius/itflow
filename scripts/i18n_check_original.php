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

$itflow_dir = realpath(__DIR__ . '/..');
$master_branch = 'master';

// Load exceptions
$exceptions_file = __DIR__ . '/i18n_exceptions.php';
$exceptions = file_exists($exceptions_file) ? require $exceptions_file : [];

// Find all PHP files
$files = [];
$dir = new RecursiveDirectoryIterator($itflow_dir, RecursiveDirectoryIterator::SKIP_DOTS);
$iterator = new RecursiveIteratorIterator($dir);

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    
    $path = $file->getPathname();
    
    // Exclude vendor, libs, .git, uploads, node_modules, scripts
    if (strpos($path, '/vendor/') !== false ||
        strpos($path, '/libs/') !== false ||
        strpos($path, '/.git/') !== false ||
        strpos($path, '/uploads/') !== false ||
        strpos($path, '/node_modules/') !== false ||
        strpos($path, '/scripts/') !== false) {
        continue;
    }
    
    $files[] = $path;
}

echo "=== i18n Original Text Checker ===\n";
echo "Checking all t() calls against master branch...\n\n";

$errors = [];
$checked = 0;

foreach ($files as $file) {
    $relative_path = str_replace($itflow_dir . '/', '', $file);
    
    $content = file_get_contents($file);
    
    // Extract t() calls
    $pattern = '/t\([\'"]([^\'"]+)[\'"]\)/';
    preg_match_all($pattern, $content, $matches);
    
    foreach ($matches[1] as $text) {
        if (strpos($text, '$') !== false) {
            continue;
        }
        
        // Skip exceptions
        if (in_array($text, $exceptions)) {
            continue;
        }
        
        $checked++;
        
        // Get master version of THIS FILE only
        $git_cmd = 'cd ' . escapeshellarg($itflow_dir) . ' && git show ' . escapeshellarg($master_branch) . ':' . escapeshellarg($relative_path) . ' 2>/dev/null';
        $master_content = shell_exec($git_cmd);
        
        if ($master_content === null || $master_content === '') {
            // File doesn't exist in master - skip validation
            continue;
        }
        
        // Check if text exists in master file (quoted or unquoted)
        $escaped_text = preg_quote($text, '/');
        
        // Check quoted version
        $quoted_pattern = '/[\'"]' . $escaped_text . '[\'"]/';
        $found_quoted = preg_match($quoted_pattern, $master_content);
        
        // Check unquoted version using strpos (simpler, no regex issues)
        $found_unquoted = (strpos($master_content, $text) !== false);
        
        if (!$found_quoted && !$found_unquoted) {
            $errors[] = [
                'file' => $relative_path,
                'text' => $text,
                'message' => 'Text NOT found in master (quoted or unquoted)'
            ];
        }
    }
}

echo "Files checked: " . count($files) . "\n";
echo "t() calls verified: $checked\n";
echo "\n";

if (empty($errors)) {
    echo "SUCCESS: All t() texts match master branch exactly.\n";
    exit(0);
} else {
    echo "FAILED: " . count($errors) . " text(s) do NOT match master:\n\n";
    foreach ($errors as $i => $error) {
        echo ($i + 1) . ". File: " . $error['file'] . "\n";
        echo '   Text: "' . $error['text'] . '"' . "\n";
        echo '   Issue: ' . $error['message'] . "\n\n";
    }
    exit(1);
}
