#!/usr/bin/env php
<?php
/**
 * Fork prepaid hours report self-test - functions/fork_hours_report.php
 * The last civil quarter, and the numbers of a report from plain rows.
 *
 * CLI only - protected from web access
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

$itflow_dir = __DIR__ . '/..';
require_once $itflow_dir . '/functions/fork_hours_report.php';

$passed = 0;
$failed = 0;

function report_test($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "  ✓ $name\n";
        $passed++;
    } else {
        echo "  ✗ $name\n";
        $failed++;
    }
}

echo "The last civil quarter\n";
$p = forkHoursReportPeriod('2026-11-11');
report_test('November 2026: July-September', $p['start'] === '2026-07-01' && $p['end'] === '2026-09-30' && $p['quarter'] === 3 && $p['year'] === 2026);
$p = forkHoursReportPeriod('2026-01-05');
report_test('January 2026: October-December 2025', $p['start'] === '2025-10-01' && $p['end'] === '2025-12-31' && $p['quarter'] === 4 && $p['year'] === 2025);
$p = forkHoursReportPeriod('2026-04-01');
report_test('1 April: January-March', $p['start'] === '2026-01-01' && $p['end'] === '2026-03-31' && $p['quarter'] === 1);
$p = forkHoursReportPeriod('2026-06-30');
report_test('30 June: January-March (the quarter is not over)', $p['start'] === '2026-01-01' && $p['end'] === '2026-03-31');
$p = forkHoursReportPeriod('2026-07-01');
report_test('1 July: April-June', $p['start'] === '2026-04-01' && $p['end'] === '2026-06-30' && $p['quarter'] === 2);
$p = forkHoursReportPeriod('2024-05-20');
report_test('May 2024: January-March, leap year', $p['start'] === '2024-01-01' && $p['end'] === '2024-03-31');
$p = forkHoursReportPeriod('2024-03-01');
report_test('March 2024: October-December 2023', $p['start'] === '2023-10-01' && $p['end'] === '2023-12-31');

echo "\nThe numbers\n";
$start = '2026-07-01';
$end = '2026-09-30';
$blocks = [
    ['id' => 1, 'hours' => 10, 'bought' => '2026-05-10', 'expires' => '2027-05-09', 'month' => null, 'note' => '', 'contract' => 'A'],
    ['id' => 2, 'hours' => 5, 'bought' => '2026-09-01', 'expires' => null, 'month' => null, 'note' => '', 'contract' => 'A'],
    ['id' => 3, 'hours' => 2, 'bought' => '2026-08-01', 'expires' => '2026-08-31', 'month' => '2026-08', 'note' => '', 'contract' => 'A'],
    ['id' => 4, 'hours' => 8, 'bought' => '2025-01-01', 'expires' => '2025-12-31', 'month' => null, 'note' => '', 'contract' => 'A'],
    ['id' => 5, 'hours' => 4, 'bought' => '2026-10-15', 'expires' => null, 'month' => null, 'note' => '', 'contract' => 'A'],
];
$uses = [
    ['block_id' => 1, 'ticket_id' => 11, 'reference' => 'TCK-11', 'subject' => 'Before', 'done' => '2026-06-20', 'hours' => 3],
    ['block_id' => 1, 'ticket_id' => 12, 'reference' => 'TCK-12', 'subject' => 'July', 'done' => '2026-07-15', 'hours' => 2.5],
    ['block_id' => 1, 'ticket_id' => 13, 'reference' => 'TCK-13', 'subject' => 'Split', 'done' => '2026-08-20', 'hours' => 1],
    ['block_id' => 3, 'ticket_id' => 13, 'reference' => 'TCK-13', 'subject' => 'Split', 'done' => '2026-08-20', 'hours' => 0.5],
    ['block_id' => 1, 'ticket_id' => 14, 'reference' => 'TCK-14', 'subject' => 'After', 'done' => '2026-10-02', 'hours' => 1],
];
$m = forkHoursReportModel($blocks, $uses, $start, $end);
$ids = array_column($m['blocks'], 'id');
report_test('blocks in the quarter: 1, 2 and 3', $ids === [1, 2, 3]);
report_test('a block that ran out before it is left out', !in_array(4, $ids, true));
report_test('a block bought after it is left out', !in_array(5, $ids, true));
$b1 = $m['blocks'][0];
report_test('block 1: 3 before, 3.5 in the quarter', $b1['before'] === 3.0 && $b1['period'] === 3.5);
report_test('block 1: 3.5 left', $b1['left'] === 3.5);
report_test('block 2: nothing drawn, 5 left', $m['blocks'][1]['period'] === 0.0 && $m['blocks'][1]['left'] === 5.0);
report_test('block 3: 0.5 used, 1.5 lapsed', $m['blocks'][2]['period'] === 0.5 && $m['blocks'][2]['left'] === 1.5 && $m['blocks'][2]['lapsed'] === true);
report_test('used in the quarter: 4', $m['used'] === 4.0);
report_test('left: 8.5, not counting the lapsed', $m['left'] === 8.5);
report_test('lapsed: 1.5', $m['lapsed'] === 1.5);
report_test('two tickets: none before or after the quarter', array_column($m['lines'], 'ticket_id') === [12, 13]);
report_test('a ticket on two blocks is one line of 1.5', $m['lines'][1]['hours'] === 1.5);
report_test('lines by date', $m['lines'][0]['date'] <= $m['lines'][1]['date']);
report_test('a line starts with the ticket subject', $m['lines'][0]['label'] === 'July');

echo "\nEmpty\n";
$m = forkHoursReportModel([], [], $start, $end);
report_test('nothing: all zero', $m['blocks'] === [] && $m['lines'] === [] && $m['used'] === 0.0 && $m['left'] === 0.0);

echo "\nNothing in ITFlow's own tables\n";
$db = (string) file_get_contents($itflow_dir . '/db.sql');
report_test('fork_hours_reports is not in db.sql', strpos($db, '`fork_hours_reports`') === false);

echo "\n" . ($failed === 0 ? "All $passed tests passed" : "$failed failed, $passed passed") . "\n";
exit($failed === 0 ? 0 : 1);
