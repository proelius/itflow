#!/usr/bin/env php
<?php
/**
 * Fork backup checks self-test - functions/fork_backup.php
 * The Monday of a week, when a check is due, the colour of a line, the rows of a report, and the report as a real
 * Word file and PDF in both languages. The queries are not run here (no database): they are checked by use on the
 * test server.
 *
 * CLI only - protected from web access
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

date_default_timezone_set('Europe/Zurich');
$itflow_dir = __DIR__ . '/..';
require_once $itflow_dir . '/functions/i18n.php';
require_once $itflow_dir . '/functions/fork_hours_report.php';
require_once $itflow_dir . '/functions/fork_backup.php';

$passed = 0;
$failed = 0;

function backup_test($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "  ✓ $name\n";
        $passed++;
    } else {
        echo "  ✗ $name\n";
        $failed++;
    }
}

echo "Weeks\n";
backup_test('a Monday stays', forkBackupMonday('2026-11-09') === '2026-11-09');
backup_test('a Wednesday goes back to Monday', forkBackupMonday('2026-11-11') === '2026-11-09');
backup_test('a Sunday goes back to the Monday before', forkBackupMonday('2026-11-15') === '2026-11-09');
backup_test('across a month', forkBackupMonday('2026-12-01') === '2026-11-30');
backup_test('not a date', forkBackupMonday('') === null && forkBackupMonday('2026-02-30') === null && forkBackupMonday('09.11.2026') === null);
backup_test('the period is the last civil quarter', forkBackupPeriod('2026-11-13') === ['start' => '2026-07-01', 'end' => '2026-09-30'] && forkBackupPeriod('2026-01-05')['start'] === '2025-10-01');
backup_test('a quarter is a quarter', forkBackupIsQuarter('2026-04-01', '2026-06-30') && forkBackupIsQuarter('2026-10-01', '2026-12-31'));
backup_test('another period is not', !forkBackupIsQuarter('2026-04-01', '2026-05-31') && !forkBackupIsQuarter('2026-04-06', '2026-06-30') && !forkBackupIsQuarter('2026-02-01', '2026-04-30'));

echo "\nHow often\n";
backup_test('three frequencies', array_keys(forkBackupFrequencies()) === ['weekly', 'biweekly', 'monthly']);
backup_test('an unknown frequency is weekly', forkBackupFrequencyClean('daily') === 'weekly' && forkBackupFrequencyClean('monthly') === 'monthly');
backup_test('weekly: every week from the start', forkBackupDue('weekly', '2026-11-02', '2026-11-09', []) && forkBackupDue('weekly', '2026-11-02', '2026-11-02', []));
backup_test('not before the service starts', !forkBackupDue('weekly', '2026-11-09', '2026-11-02', []) && !forkBackupDue('monthly', '2026-11-09', '2026-11-02', []));
backup_test('the start date can be any day of the week', forkBackupDue('weekly', '2026-11-11', '2026-11-09', []));
backup_test('biweekly: due with no check yet', forkBackupDue('biweekly', '2026-11-02', '2026-11-02', []) && forkBackupDue('biweekly', '2026-11-02', '2026-11-09', []));
backup_test('biweekly: not the week after a check', !forkBackupDue('biweekly', '2026-11-02', '2026-11-09', ['2026-11-02']));
backup_test('biweekly: due two weeks after', forkBackupDue('biweekly', '2026-11-02', '2026-11-16', ['2026-11-02']));
backup_test('biweekly: a missed check stays due', forkBackupDue('biweekly', '2026-11-02', '2026-11-30', ['2026-11-02']));
backup_test('biweekly: counts from the last check, not the first', !forkBackupDue('biweekly', '2026-11-02', '2026-11-30', ['2026-11-02', '2026-11-23']));
backup_test('biweekly: across the clock change', forkBackupDue('biweekly', '2026-10-05', '2026-11-02', ['2026-10-19']) && !forkBackupDue('biweekly', '2026-10-05', '2026-10-26', ['2026-10-19']));
backup_test('monthly: due until one is entered in the month', forkBackupDue('monthly', '2026-11-02', '2026-11-09', []) && forkBackupDue('monthly', '2026-11-02', '2026-11-30', []));
backup_test('monthly: not again once done in the month', !forkBackupDue('monthly', '2026-11-02', '2026-11-16', ['2026-11-02']));
backup_test('monthly: due again next month', forkBackupDue('monthly', '2026-11-02', '2026-12-07', ['2026-11-02']));
backup_test('a week already done is always listed', forkBackupDue('monthly', '2026-11-02', '2026-11-02', ['2026-11-02']) && forkBackupDue('biweekly', '2026-11-02', '2026-11-09', ['2026-11-02', '2026-11-09']));

echo "\nStatuses and colours\n";
backup_test('three statuses, an unknown one is N/A', array_keys(forkBackupStatuses()) === ['ok', 'fail', 'na'] && forkBackupStatusClean('x') === 'na' && forkBackupStatusClean('fail') === 'fail');
backup_test('a colour or nothing', forkBackupStateClean('yellow') === 'yellow' && forkBackupStateClean('blue') === '' && forkBackupStateClean('') === '');
backup_test('all good: no second check', !forkBackupSecondNeeded(['ok', 'na', 'ok', 'ok']) && forkBackupSecondClean('ok', ['ok', 'ok', 'ok', 'ok']) === 'none' && forkBackupSecondClean('fail', ['ok', 'na', 'ok', 'ok']) === 'none');
backup_test('a failure needs a second check', forkBackupSecondNeeded(['ok', 'ok', 'ok', 'fail']) && forkBackupSecondNeeded(['fail', 'ok', 'ok', 'ok']));
backup_test('the second check is ok, fail or not done yet', forkBackupSecondClean('ok', ['fail']) === 'ok' && forkBackupSecondClean('fail', ['fail']) === 'fail' && forkBackupSecondClean('', ['fail']) === 'pending' && forkBackupSecondClean('none', ['fail']) === 'pending');
backup_test('green when nothing failed', forkBackupSuggestState(['ok', 'na', 'ok', 'ok'], '') === 'green');
backup_test('yellow when the second check put it right', forkBackupSuggestState(['ok', 'ok', 'ok', 'fail'], 'ok') === 'yellow');
backup_test('red when the second check failed or is not done', forkBackupSuggestState(['ok', 'ok', 'ok', 'fail'], 'fail') === 'red' && forkBackupSuggestState(['ok', 'ok', 'fail', 'ok'], '') === 'red');
backup_test('the worst colour wins', forkBackupWorst(['green', 'yellow', 'green']) === 'yellow' && forkBackupWorst(['yellow', 'red', 'green']) === 'red' && forkBackupWorst(['green']) === 'green' && forkBackupWorst([]) === 'green');

echo "\nThe report\n";
$checks = [
    ['week' => '2026-05-04', 'lines' => [['server' => 'SRV1', 'state' => 'green', 'comment' => 'internal note']]],
    ['week' => '2026-04-06', 'lines' => [['server' => 'SRV1', 'state' => 'green', 'comment' => '']]],
    ['week' => '2026-05-18', 'lines' => [['server' => 'SRV1', 'state' => 'yellow', 'comment' => 'Cloud backup restarted after a failure']]],
    ['week' => '2026-06-01', 'lines' => [['server' => 'SRV1', 'state' => 'green', 'comment' => ''], ['server' => 'SRV2', 'state' => 'red', 'comment' => 'Disk dead']]],
    ['week' => '2026-06-08', 'lines' => [['server' => 'SRV1', 'state' => 'yellow', 'comment' => 'Restarted'], ['server' => 'SRV2', 'state' => 'red', 'comment' => 'Disk still dead']]],
];
$model = forkBackupReportModel($checks);
backup_test('months in order, weeks in order', array_keys($model['months']) === ['2026-04', '2026-05', '2026-06'] && array_column($model['months']['2026-05'], 'week') === ['2026-05-04', '2026-05-18']);
backup_test('counts and total', $model['counts'] === ['green' => 2, 'yellow' => 1, 'red' => 2] && $model['total'] === 5);
backup_test('a green week has no detail, whatever its comment', $model['months']['2026-04'][0]['detail'] === [] && $model['months']['2026-05'][0]['detail'] === []);
backup_test('one server: the comment alone', $model['months']['2026-05'][1]['state'] === 'yellow' && $model['months']['2026-05'][1]['detail'] === [['server' => '', 'comment' => 'Cloud backup restarted after a failure']]);
backup_test('several servers: the worst colour, and the servers named', $model['months']['2026-06'][0]['state'] === 'red' && $model['months']['2026-06'][0]['detail'] === [['server' => 'SRV2', 'comment' => 'Disk dead']]);
backup_test('several servers: every problem is listed', count($model['months']['2026-06'][1]['detail']) === 2);
backup_test('no check, no month', forkBackupReportModel([])['months'] === [] && forkBackupReportModel([])['total'] === 0);

echo "\nThe report as the client reads it\n";
$report = ['br_start' => '2026-04-01', 'br_end' => '2026-06-30', 'br_comment' => "All backups ran.\nOne cloud failure in May.", 'client_name' => 'Société Éléphant & Fils', 'data' => $model];
i18n_set_language('en');
backup_test('English: the title and the states', forkBackupReportTitle($report) === 'Quarterly backup check report' && forkBackupStateLabel('green') === 'OK' && forkBackupStateLabel('yellow') === 'Issue followed up' && forkBackupStateLabel('red') === 'Failed');
backup_test('English: another period is not "quarterly"', forkBackupReportTitle(['br_start' => '2026-04-06', 'br_end' => '2026-06-30'] + $report) === 'Backup check report');
$rows = forkBackupReportRows($report);
backup_test('rows: a month, then its weeks, with the day', $rows[0] === ['type' => 'month', 'label' => 'April'] && $rows[1]['day'] === '6' && $rows[1]['detail'] === '/');
backup_test('rows: the detail of a week', $rows[4]['detail'] === 'Cloud backup restarted after a failure' && $rows[6]['detail'] === 'SRV2: Disk dead');
i18n_set_language('fr');
backup_test('French: the title and the states', forkBackupReportTitle($report) === 'Rapport trimestriel du contrôle des sauvegardes' && forkBackupStateLabel('yellow') === 'Incident traité' && forkBackupStateLabel('red') === 'Échec');
$rows = forkBackupReportRows($report);
backup_test('French: month names and server detail', $rows[0]['label'] === 'avril' && $rows[6]['detail'] === 'SRV2 : Disk dead');
backup_test('colours of the states', forkBackupStateColours('green') === ['00B050', 'FFFFFF'] && forkBackupStateColours('yellow') === ['FFFF00', '000000'] && forkBackupStateColours('red') === ['FF0000', 'FFFFFF']);

foreach (['en', 'fr'] as $lang) {
    i18n_set_language($lang);
    $word = forkBackupDocxRender($report);
    $path = tempnam(sys_get_temp_dir(), 'bkt');
    file_put_contents($path, $word['data']);
    $zip = new ZipArchive();
    $opened = $zip->open($path) === true;
    $xml = $opened ? $zip->getFromName('word/document.xml') : '';
    $dom = new DOMDocument();
    $valid = $xml !== '' && @$dom->loadXML($xml);
    backup_test("Word ($lang): a zip with a well-formed document", $opened && $valid && $zip->getFromName('[Content_Types].xml') !== false && $zip->getFromName('_rels/.rels') !== false);
    backup_test("Word ($lang): the client, the comment and the colours are in it", $valid && str_contains($xml, 'Société Éléphant &amp; Fils') && str_contains($xml, 'One cloud failure in May.') && str_contains($xml, 'w:fill="00B050"') && str_contains($xml, 'w:fill="FFFF00"') && str_contains($xml, 'w:fill="FF0000"'));
    backup_test("Word ($lang): a name that is safe", preg_match('/^[A-Za-z0-9_\-]+\.docx$/', $word['name']) === 1);
    unlink($path);
}

if (is_file($itflow_dir . '/libs/TCPDF/tcpdf.php')) {
    $GLOBALS['config_invoice_footer'] = '';
    $GLOBALS['config_quote_footer'] = '';
    $GLOBALS['config_invoice_show_tax_id'] = 0;
    require_once $itflow_dir . '/libs/TCPDF/tcpdf.php';
    require_once $itflow_dir . '/functions/billing_pdf.php';
    foreach (['en', 'fr'] as $lang) {
        i18n_set_language($lang);
        $pdf = forkBackupPdfRender($report, ['company_name' => 'Proxycore Sàrl', 'company_country' => 'Switzerland']);
        backup_test("PDF ($lang): a PDF file", str_starts_with($pdf['data'], '%PDF') && strlen($pdf['data']) > 3000 && preg_match('/^[A-Za-z0-9_\-]+\.pdf$/', $pdf['name']) === 1);
    }
} else {
    backup_test('PDF: TCPDF is there', false);
}
i18n_set_language('en');

echo "\nWiring\n";
$handler = file_get_contents($itflow_dir . '/agent/post/backup.php');
foreach (['backup_client_add', 'backup_client_save', 'backup_client_remove', 'backup_server_add', 'backup_server_remove', 'save_backup_check', 'generate_backup_report', 'save_backup_report', 'delete_backup_report'] as $action) {
    backup_test("the handler has $action", strpos($handler, "isset(\$_POST['$action'])") !== false);
}
backup_test('the handler opens the PDF and the Word file', strpos($handler, "isset(\$_GET['backup_report_pdf'])") !== false && strpos($handler, "isset(\$_GET['backup_report_docx'])") !== false);
backup_test('every action checks the CSRF token', substr_count($handler, 'validateCSRFToken()') >= 11);
backup_test('checks need Support, reports need Financial', strpos($handler, "enforceUserPermission('module_support', \$level)") !== false && substr_count($handler, "'module_financial'") >= 2);
backup_test('nothing is sent by email', stripos($handler . file_get_contents($itflow_dir . '/functions/fork_backup.php'), 'emailQueue') === false && stripos($handler, 'mail(') === false);
foreach (['backup_checks', 'backup_check', 'backup_client', 'backup_reports', 'backup_report'] as $page) {
    backup_test("the page $page exists", is_file($itflow_dir . "/agent/$page.php"));
}
backup_test('functions.php loads the module', strpos(file_get_contents($itflow_dir . '/functions.php'), 'functions/fork_backup.php') !== false);
backup_test('the menus link the pages', strpos(file_get_contents($itflow_dir . '/agent/includes/side_nav.php'), '/agent/backup_checks.php') !== false && strpos(file_get_contents($itflow_dir . '/agent/includes/client_side_nav.php'), '/agent/backup_client.php') !== false);
$client_nav = file_get_contents($itflow_dir . '/agent/includes/client_side_nav.php');
backup_test('the client menu entry is in Support, before Billing, behind the Support module only', ($at = strpos($client_nav, '/agent/backup_client.php')) !== false && $at < strpos($client_nav, '>BILLING<') && $at > strpos($client_nav, '>SUPPORT<') && substr_count($client_nav, '/agent/backup_client.php') === 1 && preg_match('/module_support"\) >= 1\) \{ \?>\s*<\?php if \(\$config_module_enable_ticketing != 1\)/', $client_nav) === 1);
backup_test('servers are told from other assets', forkBackupIsServerType('Server') && forkBackupIsServerType('Virtual Machine') && forkBackupIsServerType('NAS') && !forkBackupIsServerType('Desktop') && !forkBackupIsServerType('Switch') && !forkBackupIsServerType(''));
backup_test('the tables are not in ITFlow\'s own database updates', strpos(file_get_contents($itflow_dir . '/db.sql'), 'fork_backup') === false && !glob($itflow_dir . '/admin/database_updates/*') === false);

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
