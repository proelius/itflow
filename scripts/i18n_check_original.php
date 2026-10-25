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

// Check for non-t() modifications in git diff
echo "=== Checking for non-text modifications ===\n";
$diff_cmd = 'cd ' . escapeshellarg($itflow_dir) . ' && git diff master -- "*.php" 2>/dev/null';
$diff_output = shell_exec($diff_cmd);

$non_text_modifications = [];
$lines = explode("\n", $diff_output);
$current_file = null;
$file_exists_in_master = false;

foreach ($lines as $line) {
    // Track current file
    if (preg_match('/^diff --git a\/(.+) b\/(.+)$/', $line, $matches)) {
        $current_file = $matches[1];
        
        // Check if file exists in master
        $check_cmd = 'cd ' . escapeshellarg($itflow_dir) . ' && git cat-file -e ' . escapeshellarg($master_branch) . ':' . escapeshellarg($current_file) . ' 2>/dev/null; echo $?';
        $result = trim(shell_exec($check_cmd));
        // git cat-file -e returns 0 if object exists, 1 if not
        $file_exists_in_master = (substr($result, -1) === '0');
        continue;
    }
    
    // Skip if file doesn't exist in master (new file)
    if (!$file_exists_in_master) {
        continue;
    }
    
    // Skip if not an added/removed line
    if (!preg_match('/^[\+\-]/', $line) || preg_match('/^[\+\-]{3}/', $line)) {
        continue;
    }
    
    // Skip if line contains t(
    if (strpos($line, 't(') !== false) {
        continue;
    }
    
    // Skip require_once lines (needed for i18n system)
    if (preg_match('/^\+require_once.*i18n\.php/', $line)) {
        continue;
    }
    
    // Skip context lines (start with single + or - followed by space)
    if (preg_match('/^[\+\-] /', $line)) {
        continue;
    }
    
    // Check if this is a real modification (not just whitespace)
    $trimmed = trim(substr($line, 1));
    if (empty($trimmed)) {
        continue;
    }
    
    // This is a non-t() modification
    $non_text_modifications[] = [
        'file' => $current_file,
        'line' => $line
    ];
}

if (!empty($non_text_modifications)) {
    echo "FAILED: " . count($non_text_modifications) . " non-text modification(s) detected:\n\n";
    foreach ($non_text_modifications as $i => $mod) {
        echo ($i + 1) . ". File: " . $mod['file'] . "\n";
        echo "   Line: " . $mod['line'] . "\n\n";
    }
    exit(1);
} else {
    echo "SUCCESS: All non-t() modifications match master exactly.\n";
}

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
