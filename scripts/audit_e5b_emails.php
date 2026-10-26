<?php
/**
 * Audit script for E5b email translations
 * Checks for English text fragments not wrapped in t_mail()
 */

$files_to_check = [
    'agent/ajax.php',
    'api/v1/ticket_replies/create.php',
    'agent/post/recurring_ticket.php',
    'agent/post/task.php',
    'cron/nightly_tasks.php',
];

echo "=== E5b Email Translation Audit ===\n\n";

foreach ($files_to_check as $file) {
    echo "--- $file ---\n";
    
    if (!file_exists($file)) {
        echo "  FILE NOT FOUND\n\n";
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Extract email body/subject variables
    $patterns = [
        '/\$body\s*=\s*"([^"]*(?:\\.[^"]*)*)"/s',
        '/\$email_body\s*=\s*"([^"]*(?:\\.[^"]*)*)"/s',
        '/\$subject\s*=\s*"([^"]*(?:\\.[^"]*)*)"/s',
        '/\$email_subject\s*=\s*"([^"]*(?:\\.[^"]*)*)"/s',
    ];
    
    $found_issues = false;
    
    // Check for text outside t_mail() calls
    // Look for patterns like: "text" . t_mail() or t_mail() . "text"
    // We want to find "text" that is NOT inside t_mail()
    
    $lines = explode("\n", $content);
    foreach ($lines as $line_num => $line) {
        // Skip lines without email-related variables
        if (!preg_match('/\$body|(\$email_body)|\$subject|(\$email_subject)/', $line)) {
            continue;
        }
        
        // Skip if entire line is just t_mail() calls
        if (preg_match('/^.*t_mail\([^)]+\).*$/s', $line) && !preg_match('/["\']If your request|Hello,|Hello \{name\}|Your ticket|A ticket|Task name|Scope\/Type|To approve|If you require|Ticket:|Subject:|Status:|Portal:|View ticket|Support|re-open|to let us know|Ticket Created|Ticket update|Ticket Notification|Ticket resolved|Ticket created|\(scheduled\)|pending closure|closed|do not reply/', $line)) {
            continue;
        }
        
        // Check for plain text strings not in t_mail()
        // Look for "text" patterns that aren't inside t_mail()
        if (preg_match_all('/["\']([^"\']+)[\"\']/', $line, $matches)) {
            foreach ($matches[1] as $text) {
                // Skip if it's a t_mail() call or parameter
                if (preg_match('/t_mail\(|=>|\\\$/', $text)) {
                    continue;
                }
                // Skip HTML markers
                if ($text === '##- Please type your reply above this line -##') {
                    continue;
                }
                // Skip variable names and common patterns
                if (preg_match('/^\$|^\{|^<br>|^<i|^<a|^href|^style|^color/', $text)) {
                    continue;
                }
                // Skip empty or very short
                if (strlen(trim($text)) < 3) {
                    continue;
                }
                
                // This might be an issue - report it
                echo "  Line $line_num: Potential untranslated text: \"$text\"\n";
                $found_issues = true;
            }
        }
    }
    
    if (!$found_issues) {
        echo "  No issues found (only marker and separators)\n";
    }
    echo "\n";
}

echo "=== Audit Complete ===\n";
