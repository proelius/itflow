#!/usr/bin/env php
<?php
/**
 * Fork prepaid hours self-test - functions/fork_hours.php
 * How time is counted (to the minute, slices of 5 or 15), which steps exist, and that the
 * fork's hours tables are not in ITFlow's own database updates or db.sql.
 *
 * CLI only - protected from web access
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

$itflow_dir = __DIR__ . '/..';
require_once $itflow_dir . '/functions/billing.php';
require_once $itflow_dir . '/functions/fork_hours.php';

$passed = 0;
$failed = 0;

function hours_test($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "  ✓ $name\n";
        $passed++;
    } else {
        echo "  ✗ $name\n";
        $failed++;
    }
}

echo "Steps\n";
hours_test('three ways to count', array_keys(forkHoursSteps()) === [0, 5, 15]);
hours_test('an unknown step is 0', forkHoursStepClean(7) === 0 && forkHoursStepClean('x') === 0);
hours_test('15 is kept', forkHoursStepClean('15') === 15);

echo "\nTo the minute\n";
hours_test('nothing worked: 0', forkHoursStepHours(0, 0) === 0.0);
hours_test('1 hour', forkHoursStepHours(3600, 0) === 1.0);
hours_test('90 minutes: 1.5', forkHoursStepHours(5400, 0) === 1.5);
hours_test('20 minutes: 0.33', forkHoursStepHours(1200, 0) === 0.33);
hours_test('29 seconds: no minute', forkHoursStepHours(29, 0) === 0.0);
hours_test('31 seconds: one minute', forkHoursStepHours(31, 0) === 0.02);

echo "\nSlices of 5 minutes, to the nearest\n";
hours_test('2 minutes: no slice', forkHoursStepHours(120, 5) === 0.0);
hours_test('2.5 minutes: one slice', forkHoursStepHours(150, 5) === 0.08);
hours_test('5 minutes: one slice', forkHoursStepHours(300, 5) === 0.08);
hours_test('7 minutes: one slice', forkHoursStepHours(420, 5) === 0.08);
hours_test('8 minutes: two slices', forkHoursStepHours(480, 5) === 0.17);
hours_test('60 minutes: 1.0', forkHoursStepHours(3600, 5) === 1.0);

echo "\nSlices of 15 minutes, to the nearest\n";
hours_test('7 minutes: no slice', forkHoursStepHours(420, 15) === 0.0);
hours_test('8 minutes: 0.25', forkHoursStepHours(480, 15) === 0.25);
hours_test('15 minutes: 0.25', forkHoursStepHours(900, 15) === 0.25);
hours_test('16 minutes: 0.25', forkHoursStepHours(960, 15) === 0.25);
hours_test('22 minutes: 0.25', forkHoursStepHours(1320, 15) === 0.25);
hours_test('22 minutes 30 seconds: 0.5', forkHoursStepHours(1350, 15) === 0.5);
hours_test('50 minutes: 0.75', forkHoursStepHours(3000, 15) === 0.75);
hours_test('nothing worked: 0', forkHoursStepHours(0, 15) === 0.0);

echo "\nNo step: the general billing time rules\n";
$rules = ['increment' => 15, 'rounding' => 'nearest', 'minimum' => 0];
hours_test('20 minutes at 15, nearest: 0.25', forkHoursStepHours(1200, null, $rules) === 0.25);
hours_test('same as billingTimeHours()', forkHoursStepHours(2000, null, $rules) === billingTimeHours(2000, $rules));

echo "\nNothing in ITFlow's own tables\n";
$db = (string) file_get_contents($itflow_dir . '/db.sql');
foreach (['fork_hours_blocks', 'fork_hours_tickets', 'fork_client_hours'] as $table) {
    hours_test("$table is not in db.sql", strpos($db, "`$table`") === false);
}
$latest = '0';
foreach (glob($itflow_dir . '/admin/database_updates/*.php') as $file) {
    if (version_compare(basename($file, '.php'), $latest, '>')) {
        $latest = basename($file, '.php');
    }
}
hours_test("latest database update is ITFlow's ($latest)", $latest === '3.6.2');

echo "\n" . ($failed === 0 ? "All $passed tests passed" : "$failed failed, $passed passed") . "\n";
exit($failed === 0 ? 0 : 1);
