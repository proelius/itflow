#!/usr/bin/env php
<?php
/**
 * Fork prepaid hours report - the period the agent can change (functions/fork_hours_report.php).
 * Pure functions and the wiring of the pages, the handler and the language files.
 *
 * CLI only - protected from web access
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

$itflow_dir = __DIR__ . '/..';
require_once $itflow_dir . '/functions/i18n.php';
require_once $itflow_dir . '/functions/fork_hours.php';
require_once $itflow_dir . '/functions/fork_hours_report.php';
i18n_set_language('en');

$passed = 0;
$failed = 0;

function period_test($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "  ✓ $name\n";
        $passed++;
    } else {
        echo "  ✗ $name\n";
        $failed++;
    }
}

$read = fn($f) => file_get_contents(__DIR__ . '/../' . $f);

echo "Is it a civil quarter\n";
period_test('January-March is one', forkHoursReportIsQuarter('2026-01-01', '2026-03-31'));
period_test('October-December is one', forkHoursReportIsQuarter('2025-10-01', '2025-12-31'));
period_test('a leap year February is not a quarter end', !forkHoursReportIsQuarter('2024-01-01', '2024-02-29'));
period_test('April to mid-May is not one', !forkHoursReportIsQuarter('2026-04-01', '2026-05-15'));
period_test('February to April is not one', !forkHoursReportIsQuarter('2026-02-01', '2026-04-30'));
period_test('a quarter that ends a day early is not one', !forkHoursReportIsQuarter('2026-07-01', '2026-09-29'));

echo "\nThe dates of the form\n";
[$s, $e, $err] = forkHoursReportPeriodFrom('', '', '2026-11-11');
period_test('empty dates give the last civil quarter', $s === '2026-07-01' && $e === '2026-09-30' && $err === '');
[$s, $e, $err] = forkHoursReportPeriodFrom('2026-04-01', '2026-05-15');
period_test('two dates are kept', $s === '2026-04-01' && $e === '2026-05-15' && $err === '');
[$s, $e, $err] = forkHoursReportPeriodFrom('2026-05-15', '2026-04-01');
period_test('an end before the start is refused', $err !== '' && $s === '' && $e === '');
[$s, $e, $err] = forkHoursReportPeriodFrom('2026-02-30', '2026-03-31');
period_test('a date that does not exist is refused', $err !== '');
[$s, $e, $err] = forkHoursReportPeriodFrom('2026-04-01', '');
period_test('one date only is refused', $err !== '');
[$s, $e, $err] = forkHoursReportPeriodFrom('2026-04-01', '2026-04-01');
period_test('one single day is a period', $err === '' && $s === $e);

echo "\nThe wording\n";
$w = forkHoursReportWords('2026-07-01', '2026-09-30');
period_test('a quarter keeps the words it always had', $w['used'] === 'Used this quarter' && $w['no_hours'] === 'No prepaid hours this quarter.' && $w['total'] === 'Total used this quarter' && $w['no_tickets'] === 'No ticket drew hours this quarter.');
$w = forkHoursReportWords('2026-04-01', '2026-05-15');
period_test('another period says "the period"', $w['used'] === 'Used in the period' && $w['total'] === 'Total used in the period');

i18n_set_language('fr');
period_test('in French, the title of another period', forkHoursReportTitle('2026-04-01', '2026-05-15') !== 'Period {from} to {to}' && str_starts_with(forkHoursReportTitle('2026-04-01', '2026-05-15'), 'Période du'));
period_test('in French, the title of a quarter has not changed', forkHoursReportTitle('2026-07-01', '2026-09-30') === 'Trimestre 3 2026' && forkHoursReportTitle('2026-07-01') === 'Trimestre 3 2026');
i18n_set_language('en');
period_test('in English, a quarter is still "Quarter 3 2026"', forkHoursReportTitle('2026-07-01', '2026-09-30') === 'Quarter 3 2026');

echo "\nThe signature\n";
period_test('the legal form is left out of the name', forkHoursReportCompanyShort('Proxycore Sàrl') === 'Proxycore' && forkHoursReportCompanyShort('Proxycore SA') === 'Proxycore' && forkHoursReportCompanyShort('Acme GmbH') === 'Acme');
period_test('a name without a legal form is kept', forkHoursReportCompanyShort('Proxycore') === 'Proxycore' && forkHoursReportCompanyShort('Sarlat Info') === 'Sarlat Info');
period_test('a name that is only a legal form is kept', forkHoursReportCompanyShort('SA') === 'SA');
i18n_set_language('fr');
period_test('in French, "L’Équipe" with capitals', t('The {company} team', ['{company}' => 'Proxycore']) === 'L’Équipe Proxycore');
i18n_set_language('en');

echo "\nThe wiring\n";
$fn = $read('functions/fork_hours_report.php');
$pages = $read('agent/hours_reports.php');
$post = $read('agent/post/hours_report.php');
period_test('the unique key is the client, the start and the end', str_contains($fn, "UNIQUE KEY `report_client_period` (`report_client_id`, `report_start`, `report_end`)"));
period_test('an old table has its key replaced, in the fork table only', str_contains($fn, "DROP INDEX `report_client_period`") && str_contains($fn, 'SHOW INDEX FROM `fork_hours_reports`'));
period_test('the page has the two dates', str_contains($pages, 'name="start"') && str_contains($pages, 'name="end"'));
period_test('the handler reads them and looks for the report of that period', str_contains($post, 'forkHoursReportPeriodFrom($_POST') && str_contains($post, "forkHoursReportFor(\$client_id, \$period['start'], \$period['end'])"));
period_test('no title is made from the start alone any more', !preg_match('/forkHoursReportTitle\(\$[a-z_\[\]\']+\[\'(start|report_start)\'\]\)/', $post . $pages . $read('agent/hours_report.php') . $read('client/contracts.php') . $fn));
$en = $read('lang/en.php');
$fr = $read('lang/fr.php');
foreach (['Period {from} to {to}', 'Used in the period', 'Total used in the period', 'No prepaid hours in this period.', 'No ticket drew hours in this period.', 'Please find attached the report of your prepaid hours for the period {from} to {to}.'] as $k) {
    period_test("language files have '$k'", str_contains($en, "'$k' =>") && str_contains($fr, "'$k' =>"));
}

echo "\nTime in the report: real time, not decimals\n";
period_test('0.33 h is 20 min', forkHoursDuration(0.33) === '20 min');
period_test('0.08 h is 5 min', forkHoursDuration(0.08) === '5 min');
period_test('0.92 h is 55 min', forkHoursDuration(0.92) === '55 min');
period_test('3.67 h is 3 h 40 min', forkHoursDuration(3.67) === '3 h 40 min');
period_test('1.08 h is 1 h 05 min', forkHoursDuration(1.08) === '1 h 05 min');
period_test('5 h is 5 h', forkHoursDuration(5) === '5 h');
period_test('0 is 0 min', forkHoursDuration(0) === '0 min');
$fnsrc = $read('functions/fork_hours_report.php');
period_test('the contract pages (agent and portal) show real time too', !preg_match('/contractHoursText\\(/', $read('agent/contract.php') . $read('agent/contracts.php') . $read('client/contract.php') . $read('client/contracts.php')));
period_test('the report does not show hours in decimals any more', !str_contains($fnsrc . $read('agent/hours_report.php'), 'contractHoursText('));

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
