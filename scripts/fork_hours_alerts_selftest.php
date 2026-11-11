#!/usr/bin/env php
<?php
/**
 * Fork prepaid hours alerts self-test - functions/fork_hours_alerts.php
 * Which level is told for a block of hours, once, and when it is told again.
 *
 * CLI only - protected from web access
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

$itflow_dir = __DIR__ . '/..';
require_once $itflow_dir . '/functions/fork_hours_alerts.php';

$passed = 0;
$failed = 0;

function alert_test($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "  ✓ $name\n";
        $passed++;
    } else {
        echo "  ✗ $name\n";
        $failed++;
    }
}

echo "Levels\n";
alert_test('20, 10 and 0 percent', forkHoursAlertLevels() === [20, 10, 0]);

echo "\nA block of 20 hours\n";
$p = forkHoursAlertPlan(20, 10, []);
alert_test('10 hours left: nothing', $p['send'] === null && $p['mark'] === [] && $p['clear'] === []);
$p = forkHoursAlertPlan(20, 4.5, []);
alert_test('4.5 left: nothing yet', $p['send'] === null);
$p = forkHoursAlertPlan(20, 4, []);
alert_test('4 left (20 %): tells 20', $p['send'] === 20 && $p['mark'] === [20]);
$p = forkHoursAlertPlan(20, 3.5, [20]);
alert_test('3.5 left, 20 told: nothing', $p['send'] === null);
$p = forkHoursAlertPlan(20, 2, [20]);
alert_test('2 left (10 %), 20 told: tells 10', $p['send'] === 10 && $p['mark'] === [10]);
$p = forkHoursAlertPlan(20, 0, [20, 10]);
alert_test('0 left, 20 and 10 told: tells 0', $p['send'] === 0 && $p['mark'] === [0]);
$p = forkHoursAlertPlan(20, 0, [20, 10, 0]);
alert_test('everything told: nothing', $p['send'] === null && $p['mark'] === [] && $p['clear'] === []);

echo "\nSeveral levels at once\n";
$p = forkHoursAlertPlan(20, 0, []);
alert_test('0 left, none told: tells 0 only', $p['send'] === 0);
alert_test('and the levels above count as told', $p['mark'] === [20, 10, 0]);
$p = forkHoursAlertPlan(20, 1.5, []);
alert_test('1.5 left: tells 10, 20 counts as told', $p['send'] === 10 && $p['mark'] === [20, 10]);

echo "\nHours back\n";
$p = forkHoursAlertPlan(20, 10, [20, 10, 0]);
alert_test('10 left, all told: all cleared', $p['send'] === null && $p['clear'] === [20, 10, 0]);
$p = forkHoursAlertPlan(20, 3, [20, 10, 0]);
alert_test('3 left (under 20 %): 10 and 0 cleared, 20 kept', $p['clear'] === [10, 0] && $p['send'] === null);

echo "\nOther blocks\n";
$p = forkHoursAlertPlan(0, 0, []);
alert_test('a block of no hours: nothing', $p['send'] === null);
$p = forkHoursAlertPlan(5, 1, []);
alert_test('5 hours, 1 left (20 %): tells 20', $p['send'] === 20);

echo "\nNothing in ITFlow's own tables\n";
$db = (string) file_get_contents($itflow_dir . '/db.sql');
alert_test('fork_hours_alerts is not in db.sql', strpos($db, '`fork_hours_alerts`') === false);

echo "\n" . ($failed === 0 ? "All $passed tests passed" : "$failed failed, $passed passed") . "\n";
exit($failed === 0 ? 0 : 1);
