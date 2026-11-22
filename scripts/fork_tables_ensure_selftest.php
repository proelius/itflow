<?php
/*
 * The fork's own tables are made the first time a module is used (CREATE TABLE IF NOT EXISTS), so every
 * function that reads or writes one must make sure of them itself: on an install that has never opened the
 * module, the first query is the one that fails ("Table ... doesn't exist"). Run: php scripts/fork_tables_ensure_selftest.php
 */
$root = dirname(__DIR__);
$modules = [
    'functions/fork_backup.php'       => ['fork_backup_', 'forkBackupEnsure('],
    'functions/fork_billing_note.php' => ['fork_billing_notes', 'forkBillingNoteEnsure('],
    'functions/fork_hours_report.php' => ['fork_hours_reports', 'forkHoursReportEnsure('],
];
$pass = 0;
$fail = 0;
foreach ($modules as $file => [$table, $ensure]) {
    $src = file_get_contents("$root/$file");
    preg_match_all('/^function\s+(\w+)\s*\(/m', $src, $m, PREG_OFFSET_CAPTURE);
    $n = count($m[1]);
    $checked = 0;
    for ($i = 0; $i < $n; $i++) {
        $start = $m[0][$i][1];
        $end = $i + 1 < $n ? $m[0][$i + 1][1] : strlen($src);
        $body = substr($src, $start, $end - $start);
        $name = $m[1][$i][0];
        if (str_contains($name, 'Ensure') || !str_contains($body, $table) || !str_contains($body, 'mysqli_query')) {
            continue;
        }
        $checked++;
        if (str_contains($body, $ensure)) {
            $pass++;
        } else {
            $fail++;
            echo "  ✗ $file: $name() queries $table without calling $ensure)\n";
        }
    }
    echo "  $file: $checked functions with a query on its tables\n";
    if ($checked === 0) {
        $fail++;
        echo "  ✗ $file: no function found - the test looks at the wrong thing\n";
    }
}
echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
