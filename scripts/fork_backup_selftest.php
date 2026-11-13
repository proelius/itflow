#!/usr/bin/env php
<?php
/**
 * Fork backup checks self-test - functions/fork_backup.php
 * The Monday of a week, when a check is due, the colour of a line, the rows of a report, and the report as a real
 * PDF in both languages. The queries are not run here (no database): they are checked by use on the
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
backup_test('four frequencies', array_keys(forkBackupFrequencies()) === ['weekly', 'biweekly', 'monthly', 'bimonthly'] && forkBackupFrequencies()['bimonthly'] === 'Every 2 months' && forkBackupFrequencyClean('bimonthly') === 'bimonthly');
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
backup_test('every 2 months: due with no check yet, from the start', forkBackupDue('bimonthly', '2026-11-02', '2026-11-02', []) && forkBackupDue('bimonthly', '2026-11-02', '2026-11-30', []) && !forkBackupDue('bimonthly', '2026-11-09', '2026-11-02', []));
backup_test('every 2 months: not in the month of the last check nor the next', !forkBackupDue('bimonthly', '2026-09-07', '2026-09-21', ['2026-09-07']) && !forkBackupDue('bimonthly', '2026-09-07', '2026-10-05', ['2026-09-07']) && !forkBackupDue('bimonthly', '2026-09-07', '2026-10-26', ['2026-09-07']));
backup_test('every 2 months: due from the second month after', forkBackupDue('bimonthly', '2026-09-07', '2026-11-02', ['2026-09-07']) && forkBackupDue('bimonthly', '2026-09-07', '2026-11-30', ['2026-09-07']));
backup_test('every 2 months: a missed one stays due, counted from the last done', forkBackupDue('bimonthly', '2026-01-05', '2026-06-01', ['2026-01-05']) && !forkBackupDue('bimonthly', '2026-01-05', '2026-06-08', ['2026-01-05', '2026-05-04']));
backup_test('every 2 months: across the year', forkBackupDue('bimonthly', '2026-11-02', '2027-01-04', ['2026-11-02']) && !forkBackupDue('bimonthly', '2026-11-02', '2026-12-07', ['2026-11-02']));
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
backup_test('everything OK is green by itself, whatever the form sent', forkBackupLineState(['ok', 'ok', 'ok', 'na'], 'none', '') === 'green' && forkBackupLineState(['ok', 'ok', 'ok', 'ok'], 'none', 'red') === 'green' && forkBackupLineState(['ok', 'ok', 'ok', 'ok'], 'none', 'yellow') === 'green');
backup_test('a failure is never green; the chosen colour is kept', forkBackupLineState(['ok', 'fail', 'ok', 'ok'], 'pending', 'green') === 'red' && forkBackupLineState(['ok', 'fail', 'ok', 'ok'], 'ok', '') === 'yellow' && forkBackupLineState(['ok', 'fail', 'ok', 'ok'], 'ok', 'red') === 'red' && forkBackupLineState(['ok', 'fail', 'ok', 'ok'], 'pending', 'yellow') === 'yellow');
backup_test('the colour choice is hidden when everything is OK', strpos(file_get_contents($itflow_dir . '/agent/includes/backup_check_fields.php'), 'bk-state-box') !== false && strpos(file_get_contents($itflow_dir . '/agent/includes/backup_check_fields.php'), "stateBox.style.display = failed") !== false);
backup_test('green is taken out of the list when something failed, and put back when everything is OK', strpos(file_get_contents($itflow_dir . '/agent/includes/backup_check_fields.php'), 'state.removeChild(green)') !== false && strpos(file_get_contents($itflow_dir . '/agent/includes/backup_check_fields.php'), 'state.insertBefore(greenOption') !== false);
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

echo "\nChanging the report without changing the checks\n";
$auto = fn(array $detail) => $detail ? implode("\n", array_map(fn($d) => ($d['server'] !== '' ? $d['server'] . ': ' : '') . $d['comment'], $detail)) : '/';
$before = json_encode($model);
$edited = forkBackupReportApplyEdits($model, [
    '2026-05-18' => ['state' => 'green', 'text' => 'Cloud backup restarted'],
    '2026-06-01' => ['state' => 'red', 'text' => 'SRV2: Disk dead'],
    '2026-04-06' => ['state' => 'yellow', 'text' => ''],
    '2026-06-08' => ['state' => 'blue'],
], $auto);
backup_test('the original is not touched', json_encode($model) === $before);
backup_test('a new colour is shown, the entered one is kept', $edited['months']['2026-05'][1]['state'] === 'green' && $edited['months']['2026-05'][1]['orig_state'] === 'yellow');
backup_test('a new wording is shown, the entered detail is kept', $edited['months']['2026-05'][1]['text'] === 'Cloud backup restarted' && $edited['months']['2026-05'][1]['detail'] === $model['months']['2026-05'][1]['detail']);
backup_test('a wording equal to the automatic one stays automatic', $edited['months']['2026-06'][0]['text'] === null);
backup_test('an empty wording where there is nothing to say stays automatic', $edited['months']['2026-04'][0]['text'] === null && $edited['months']['2026-04'][0]['state'] === 'yellow');
backup_test('a wrong colour is ignored', $edited['months']['2026-06'][1]['state'] === 'red');
backup_test('a week that is not posted is left as it is', $edited['months']['2026-05'][0] === $model['months']['2026-05'][0]);
backup_test('the counts follow the colours shown', $edited['counts'] === ['green' => 2, 'yellow' => 1, 'red' => 2] && $edited['total'] === 5);
$cleared = forkBackupReportApplyEdits($model, ['2026-05-18' => ['state' => 'yellow', 'text' => '']], $auto);
backup_test('an emptied wording shows "/" in the report', $cleared['months']['2026-05'][1]['text'] === '');
$edited_report = ['data' => $edited] + ['br_start' => '2026-04-01', 'br_end' => '2026-06-30', 'br_comment' => 'x', 'client_name' => 'X'];
i18n_set_language('en');
$rows_edited = forkBackupReportRows($edited_report);
backup_test('the report rows show the changed colour and wording', $rows_edited[4]['state'] === 'green' && $rows_edited[4]['detail'] === 'Cloud backup restarted');
$rows_cleared = forkBackupReportRows(['data' => $cleared] + $edited_report);
backup_test('the report rows show "/" for an emptied wording', $rows_cleared[4]['detail'] === '/');
backup_test('the report rows show the automatic wording where nothing changed', $rows_edited[6]['detail'] === 'SRV2: Disk dead' && $rows_edited[1]['detail'] === '/');

echo "\nThe report as the client reads it\n";
$report = ['br_start' => '2026-04-01', 'br_end' => '2026-06-30', 'br_comment' => "All backups ran.\nOne cloud failure in May.", 'client_name' => 'Société Éléphant & Fils', 'data' => $model];
i18n_set_language('en');
backup_test('English: the title', forkBackupReportTitle($report) === 'Quarterly backup check report');
backup_test('English: another period is not "quarterly"', forkBackupReportTitle(['br_start' => '2026-04-06', 'br_end' => '2026-06-30'] + $report) === 'Backup check report');
$rows = forkBackupReportRows($report);
backup_test('rows: a month, then its weeks, with the day', $rows[0] === ['type' => 'month', 'label' => 'April'] && $rows[1]['day'] === '6' && $rows[1]['detail'] === '/');
backup_test('rows: the date of a week is the Monday as 06.04.2026', $rows[1]['date'] === '06.04.2026' && $rows[3]['date'] === '04.05.2026' && $rows[4]['date'] === '18.05.2026' && preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $rows[6]['date']) === 1);
backup_test('rows: the detail of a week', $rows[4]['detail'] === 'Cloud backup restarted after a failure' && $rows[6]['detail'] === 'SRV2: Disk dead');
i18n_set_language('fr');
backup_test('French: the title', forkBackupReportTitle($report) === 'Rapport trimestriel du contrôle des sauvegardes');
$rows = forkBackupReportRows($report);
backup_test('French: month names and server detail', $rows[0]['label'] === 'avril' && $rows[6]['detail'] === 'SRV2 : Disk dead');
backup_test('colours of the states', forkBackupStateColours('green') === ['00B050'] && forkBackupStateColours('yellow') === ['FFFF00'] && forkBackupStateColours('red') === ['FF0000']);

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
        $tmp = tempnam(sys_get_temp_dir(), 'bkp');
        file_put_contents($tmp, $pdf['data']);
        $text = trim((string) @shell_exec('pdftotext -layout ' . escapeshellarg($tmp) . ' - 2>/dev/null'));
        unlink($tmp);
        if ($text === '') {
            echo "  - PDF ($lang): pdftotext is not there - the wording of the status cells was NOT checked (not counted as passed)\n";
        } else {
            backup_test("PDF ($lang): the weeks are dated with their Monday (06.04.2026)", str_contains($text, '06.04.2026'));
            backup_test("PDF ($lang): the report is readable and its status cells have no wording", str_contains($text, 'Client X') || str_contains($text, 'Société Éléphant') ? !preg_match('/\b(OK|Failed|Échec|Incident traité|Issue followed up)\b/u', $text) : false);
        }
    }
} else {
    backup_test('PDF: TCPDF is there', false);
}
i18n_set_language('en');

echo "\nWiring\n";
$handler = file_get_contents($itflow_dir . '/agent/post/backup.php');
foreach (['backup_client_add', 'backup_client_save', 'backup_client_remove', 'backup_server_add', 'backup_server_remove', 'save_backup_check', 'generate_backup_report', 'save_backup_report', 'reset_backup_report', 'delete_backup_report'] as $action) {
    backup_test("the handler has $action", strpos($handler, "isset(\$_POST['$action'])") !== false);
}
backup_test('the handler opens the PDF, and there is no Word export', strpos($handler, "isset(\$_GET['backup_report_pdf'])") !== false && stripos($handler . file_get_contents($itflow_dir . '/agent/backup_report.php') . file_get_contents($itflow_dir . '/agent/backup_reports.php'), 'docx') === false);
backup_test('the service and its servers need the Financial module, the checks the Support module', preg_match("/backup_client_add'\]\)\) \{\s+validateCSRFToken\(\);\s+\\\$client_id = backupPostService\(\)/", $handler) === 1 && substr_count($handler, 'backupPostService()') === 5 && preg_match("/save_backup_check'\]\)\) \{\s+validateCSRFToken\(\);\s+\\\$client_id = backupPostClient\(\)/", $handler) === 1);
backup_test('the clients with the service, the service page and the tabs follow the Financial module', strpos(file_get_contents($itflow_dir . '/agent/backup_checks.php'), "enforceUserPermission('module_financial')") !== false && strpos(file_get_contents($itflow_dir . '/agent/backup_client.php'), "enforceUserPermission('module_financial')") !== false && strpos(file_get_contents($itflow_dir . '/agent/backup_client.php'), "lookupUserPermission('module_financial') >= 2") !== false);
backup_test('the corrective action words and colours', forkBackupAutonomyLabel(true) === ['Technician acts on his own', 'bg-success'] && forkBackupAutonomyLabel(false) === ['Client approval needed', 'bg-warning text-dark']);
$fork_source = file_get_contents($itflow_dir . '/functions/fork_backup.php');
backup_test('the corrective action is stored on the service, added to a table made before, defaults to approval needed', strpos($fork_source, '`bc_autonomous` tinyint(1) NOT NULL DEFAULT 0') !== false && strpos($fork_source, "LIKE 'bc_autonomous'") !== false && strpos($fork_source, 'ADD COLUMN `bc_autonomous`') !== false && strpos($fork_source, "'autonomous' => intval(\$row['bc_autonomous']) === 1") !== false);
backup_test('the corrective action checkbox is on the add form, the service page and in the lists', substr_count(file_get_contents($itflow_dir . '/agent/backup_checks.php'), 'name="autonomous"') === 1 && substr_count(file_get_contents($itflow_dir . '/agent/backup_client.php'), 'name="autonomous"') === 2 && substr_count(file_get_contents($itflow_dir . '/agent/backup_checks.php'), 'forkBackupAutonomyLabel') === 2 && substr_count($handler, "!empty(\$_POST['autonomous'])") === 2);
$ok_line = ['bl_server_name' => 'SRV1', 'bl_raid' => 'ok', 'bl_sys' => 'ok', 'bl_data' => 'ok', 'bl_cloud' => 'ok', 'bl_comment' => ''];
$bad_line = ['bl_server_name' => 'SRV2 <b>', 'bl_raid' => 'ok', 'bl_sys' => 'na', 'bl_data' => 'fail', 'bl_cloud' => 'fail', 'bl_comment' => "Disk dead\nCall the client"];
backup_test('failed parts of a line are named', forkBackupFailedParts($ok_line) === [] && forkBackupFailedParts($bad_line) === ['BKP DATA', 'Cloud']);
backup_test('a ticket is needed only when something failed, approval is needed and no ticket is linked',
    forkBackupNeedsTicket(false, [$ok_line, $bad_line], 0) === true && forkBackupNeedsTicket(true, [$ok_line, $bad_line], 0) === false
    && forkBackupNeedsTicket(false, [$ok_line, $bad_line], 12) === false && forkBackupNeedsTicket(false, [$ok_line], 0) === false && forkBackupNeedsTicket(false, [], 0) === false);
[$ticket_subject, $ticket_details] = forkBackupTicketContent('2026-11-09', [$ok_line, $bad_line]);
backup_test('the ticket says the week and the failed servers, and escapes what was entered', $ticket_subject === 'Backup check: failure in the week of 09.11.2026' && str_contains($ticket_details, '<strong>SRV2 &lt;b&gt;</strong> - BKP DATA, Cloud failed') && str_contains($ticket_details, 'Disk dead<br />') && !str_contains($ticket_details, 'SRV1') && str_contains($ticket_details, 'approve'));
$handler_source = file_get_contents($itflow_dir . '/agent/post/backup.php');
backup_test('saving a check opens the ticket and says so', strpos($handler_source, 'forkBackupTicketOpen($client_id, $week, $session_user_id)') !== false && strpos($handler_source, "opened for the client's approval") !== false);
backup_test('the ticket is for the client, assigned to the technician, linked to the check, and nothing is mailed', strpos($fork_source, "ticket_client_id = \$client_id") !== false && strpos($fork_source, 'ticket_assigned_to = $assigned_to') !== false && strpos($fork_source, 'UPDATE fork_backup_checks SET bk_ticket_id') !== false && strpos($fork_source, 'ticketMail') === false);
$archive_modal = file_get_contents($itflow_dir . '/agent/modals/backup/backup_delete.php');
$client_page = file_get_contents($itflow_dir . '/agent/backup_client.php');
$handler_archive = file_get_contents($itflow_dir . '/agent/post/backup.php');
backup_test('removing a client archives the service first (as for assets): the checks are kept', strpos($fork_source, 'SET bc_archived_at = NOW()') !== false && strpos($fork_source, 'function forkBackupClientArchive') !== false && strpos($fork_source, 'function forkBackupClientRestore') !== false && strpos($handler_archive, 'forkBackupClientArchive($client_id)') !== false && strpos($handler_archive, "isset(\$_POST['backup_client_restore'])") !== false);
backup_test('deleting for good is a second step, refused while the service is active, and needs Full access', strpos($fork_source, 'function forkBackupClientDelete') !== false && preg_match('/function forkBackupClientDelete.*?if \(forkBackupClient\(\$client_id\)\) \{\s+return null;/s', $fork_source) === 1 && strpos($handler_archive, 'backupPostService(3)') !== false && strpos($archive_modal, "enforceUserPermission('module_financial', 3)") !== false && strpos($archive_modal, 'Archive the service first') !== false);
backup_test('the deletion takes the lines, checks, reports, servers and the service, and leaves the tickets', substr_count($fork_source, 'DELETE l FROM fork_backup_lines') === 1 && strpos($fork_source, 'DELETE FROM fork_backup_reports WHERE br_client_id') !== false && strpos($fork_source, 'DELETE FROM fork_backup_servers WHERE bs_client_id') !== false && strpos($fork_source, 'DELETE FROM fork_backup_clients WHERE bc_client_id') !== false && strpos($fork_source, 'DELETE FROM tickets') === false);
backup_test('the client page offers restore and delete for an archived service, and delete for what is left without a service', strpos($client_page, 'backup_client_restore') !== false && substr_count($client_page, 'modals/backup/backup_delete.php?client_id=') === 2 && strpos($client_page, 'Archive the service') !== false && strpos($client_page, "lookupUserPermission('module_financial') >= 3") !== false);
backup_test('the list of the clients has an archived view, and an archived client is not asked for a check', strpos(file_get_contents($itflow_dir . '/agent/backup_checks.php'), 'archived=') !== false && strpos($fork_source, "bc.bc_archived_at ' . (\$archived ? 'IS NOT NULL' : 'IS NULL')") === false && strpos($fork_source, 'bc.bc_archived_at " . ($archived') !== false);
backup_test('every action checks the CSRF token', substr_count($handler, 'validateCSRFToken()') >= 11);
backup_test('checks need Support, reports need Financial', strpos($handler, "enforceUserPermission('module_support', \$level)") !== false && substr_count($handler, "'module_financial'") >= 2);
$send_block = substr($handler, strpos($handler, "isset(\$_POST['email_backup_report'])"), strpos($handler, "isset(\$_POST['reset_backup_report'])") - strpos($handler, "isset(\$_POST['email_backup_report'])"));
$cron_source = '';
foreach (glob($itflow_dir . '/cron/*.php') ?: [] as $cron_file) {
    $cron_source .= file_get_contents($cron_file);
}
backup_test('a report is only sent when an agent asks: one handler, behind the CSRF token and Financial level 2, nothing scheduled', substr_count($handler, 'billingEmailQueue(') === 1 && strpos($send_block, 'billingEmailQueue(') !== false && strpos($send_block, 'validateCSRFToken()') !== false && strpos($send_block, 'backupPostReport($_POST[\'report_id\'] ?? 0, 2)') !== false && stripos($fork_source, 'emailQueue') === false && preg_match('/(?<![A-Za-z_])mail\\(/', $handler . $fork_source) === 0 && stripos($cron_source, 'forkBackup') === false);
backup_test('the email goes from the invoice address, with the PDF attached, to the contacts picked, and is logged', strpos($send_block, "'from' => 'invoice'") !== false && strpos($send_block, "'attach' => [\$file]") !== false && strpos($send_block, 'billingEmailPdf(forkBackupPdf($report))') !== false && strpos($send_block, "billingEmailRecipients(\$client_id, (array) (\$_POST['contacts']") !== false && strpos($send_block, 'logAudit("Backup check", "Email"') !== false);
$email_window = file_get_contents($itflow_dir . '/agent/modals/backup/backup_report_email.php');
backup_test('the window is as for a quote: contacts of a quote, the primary ticked, a note, and posts email_backup_report', strpos($email_window, "enforceUserPermission('module_financial', 2)") !== false && strpos($email_window, "documentContactFilterSql('quote')") !== false && strpos($email_window, "documentDefaultContactFilterSql('quote')") !== false && strpos($email_window, 'name="note"') !== false && strpos($email_window, 'name="email_backup_report"') !== false && strpos($email_window, 'attach_pdf') === false);
backup_test('the report page and the list of reports have the button, for those who can change reports', substr_count(file_get_contents($itflow_dir . '/agent/backup_report.php') . file_get_contents($itflow_dir . '/agent/backup_reports.php'), 'modals/backup/backup_report_email.php?report_id=') === 2 && substr_count(file_get_contents($itflow_dir . '/agent/backup_report.php') . file_get_contents($itflow_dir . '/agent/backup_reports.php'), 'if ($can_edit) { ?><button') === 2);
$email_en = file_get_contents($itflow_dir . '/lang/en.php');
$email_fr = file_get_contents($itflow_dir . '/lang/fr.php');
backup_test('the sentence that introduces the report does not name the client', strpos($fork_source, "t('Please find attached the backup check report for the period {period}.', \$n)") !== false && strpos($email_fr, "'Vous trouverez ci-joint le rapport du contrôle des sauvegardes pour la période {period}.'") !== false && strpos($email_fr . $email_en, 'report of {client}') === false);
backup_test('the message: the company comes from a variable, never written in the code, and the wording is the agreed one', strpos($fork_source, "billingCompany()['company_name']") !== false && stripos($fork_source . $email_window . $send_block, 'Proxycore') === false && strpos($fork_source, "'{company}' =>") !== false
    && strpos($email_fr, "'{title} from {company} - {period}' => '{title} de {company} – {period}'") !== false
    && strpos($email_fr, "'En cas de question, répondez à cet e-mail.") === false && strpos($email_fr, 'En cas de question, répondez à cet e-mail.\',') !== false
    && strpos($email_en, 'In case of questions, reply to this email.') !== false && strpos($email_fr, 'Des questions') === strpos($email_fr, 'Des questions'));
foreach (['backup_checks', 'backup_check', 'backup_client', 'backup_reports', 'backup_report'] as $page) {
    backup_test("the page $page exists", is_file($itflow_dir . "/agent/$page.php"));
}
backup_test('functions.php loads the module', strpos(file_get_contents($itflow_dir . '/functions.php'), 'functions/fork_backup.php') !== false);
backup_test('the menus link the pages', strpos(file_get_contents($itflow_dir . '/agent/includes/side_nav.php'), '/agent/backup_checks.php') !== false && strpos(file_get_contents($itflow_dir . '/agent/includes/client_side_nav.php'), '/agent/backup_client.php') !== false);
$client_nav = file_get_contents($itflow_dir . '/agent/includes/client_side_nav.php');
backup_test('the client menu entry is in Support, before Billing, behind the Support module only', ($at = strpos($client_nav, '/agent/backup_client.php')) !== false && $at < strpos($client_nav, '>BILLING<') && $at > strpos($client_nav, '>SUPPORT<') && substr_count($client_nav, '/agent/backup_client.php') === 1 && preg_match('/module_support"\) >= 1\) \{ \?>\s*<\?php if \(\$config_module_enable_ticketing != 1\)/', $client_nav) === 1);
backup_test('servers are told from other assets', forkBackupIsServerType('Server') && forkBackupIsServerType('Virtual Machine') && forkBackupIsServerType('NAS') && !forkBackupIsServerType('Desktop') && !forkBackupIsServerType('Switch') && !forkBackupIsServerType(''));
$list = file_get_contents($itflow_dir . '/agent/backup_checks.php');
$modal = file_get_contents($itflow_dir . '/agent/modals/backup/backup_check.php');
backup_test('the list of the week opens the check in a window, not the client page', strpos($list, 'ajax-modal') !== false && strpos($list, 'modals/backup/backup_check.php?client_id=') !== false && strpos($list, 'href="backup_check.php?client_id') === false);
backup_test('the window checks the Support module and the client, and posts the same form', strpos($modal, "enforceUserPermission('module_support')") !== false && strpos($modal, 'enforceClientAccess()') !== false && strpos($modal, 'save_backup_check') !== false && strpos($modal, 'backup_check_fields.php') !== false && strpos(file_get_contents($itflow_dir . '/agent/backup_check.php'), 'backup_check_fields.php') !== false);
$veeam = '';
foreach (['functions/fork_backup.php', 'agent/backup_check.php', 'agent/includes/backup_check_fields.php', 'agent/modals/backup/backup_check.php', 'agent/post/backup.php', 'agent/backup_report.php'] as $file) {
    $veeam .= file_get_contents($itflow_dir . '/' . $file);
}
backup_test('there is no Veeam version in the check', stripos($veeam, 'veeam') === false);
// The window loads fewer files than a page: draw the fields in a fresh process that loads only what the window loads
$probe = tempnam(sys_get_temp_dir(), 'bkf');
file_put_contents($probe, '<?php
error_reporting(E_ALL); ini_set("display_errors", "1");
function escapeHtml($v) { return htmlspecialchars((string) $v, ENT_QUOTES, "UTF-8"); }
function lookupUserPermission($m) { return 3; }
$_SESSION = ["csrf_token" => "t"];
require ' . var_export($itflow_dir . '/functions/fork_backup.php', true) . ';
$client_id = 7; $week = "2026-11-09"; $session_name = "Tech"; $can_edit = true; $check = null; $user = null;
$servers = [3 => ["id" => 3, "asset_id" => 5, "name" => "SRV", "os" => "Windows Server 2022"]];
$previous = [3 => ["bl_disk1" => "C: 1 / 2 Go", "bl_disk2" => ""]]; $tickets = [1 => "TCK-1 x"];
$statuses = forkBackupStatuses(); $parts = ["raid" => "RAID", "sys" => "BKP SYS", "data" => "BKP DATA", "cloud" => "Cloud"];
ob_start(); require ' . var_export($itflow_dir . '/agent/includes/backup_check_fields.php', true) . '; $html = ob_get_clean();
echo str_contains($html, "line[3][raid]") && str_contains($html, "C: 1 / 2 Go") ? "DRAWN" : "EMPTY";');
$out = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe) . ' 2>&1');
unlink($probe);
backup_test('the fields draw with only the files the window loads', trim($out) === 'DRAWN');
backup_test('a space of a volume is trimmed, on one line, cut at 30 characters', forkBackupVolumeClean("  349 Go \n") === '349 Go' && forkBackupVolumeClean(str_repeat('9', 40)) === str_repeat('9', 30) && forkBackupVolumeClean(null) === '');
$probe = tempnam(sys_get_temp_dir(), 'bkv');
file_put_contents($probe, '<?php
error_reporting(E_ALL); ini_set("display_errors", "1");
function escapeHtml($v) { return htmlspecialchars((string) $v, ENT_QUOTES, "UTF-8"); }
function lookupUserPermission($m) { return 3; }
$_SESSION = ["csrf_token" => "t"];
require ' . var_export($itflow_dir . '/functions/fork_backup.php', true) . ';
$client_id = 7; $week = "2026-11-09"; $session_name = "Tech"; $can_edit = true; $check = null; $user = null;
$servers = [3 => ["id" => 3, "asset_id" => 5, "name" => "SRV", "os" => ""]];
$previous = [3 => ["bl_vol1_free" => "111 Go", "bl_vol1_total" => "424 Go", "bl_vol2_free" => "222 To", "bl_vol2_total" => "3.45 To"]]; $tickets = [];
$statuses = forkBackupStatuses(); $parts = ["raid" => "RAID", "sys" => "BKP SYS", "data" => "BKP DATA", "cloud" => "Cloud"];
ob_start(); require ' . var_export($itflow_dir . '/agent/includes/backup_check_fields.php', true) . '; $html = ob_get_clean();
$ok = str_contains($html, "line[3][vol1_free]") && str_contains($html, "line[3][vol1_total]") && str_contains($html, "line[3][vol2_free]") && str_contains($html, "line[3][vol2_total]")
    && str_contains($html, "424 Go") && str_contains($html, "3.45 To") && !str_contains($html, "111 Go") && !str_contains($html, "222 To")
    && !str_contains($html, "Disk 1") && !str_contains($html, "line[3][disk1]") && str_contains($html, "Volume 1 - free space") && str_contains($html, "Volume 2 - total space");
echo $ok ? "VOLUMES" : "WRONG";');
$out = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe) . ' 2>&1');
unlink($probe);
backup_test('the fields have two volumes, each with its free and its total space: the total starts from the last check, the free space is empty', trim($out) === 'VOLUMES');
backup_test('the volumes are stored in the lines, added to a table made before, and the old disks are no longer written', strpos($fork_source, '`bl_vol1_free` varchar(30)') !== false && strpos($fork_source, "ADD COLUMN `\$column` varchar(30)") !== false && strpos($fork_source, "bl_vol2_total = '") !== false && strpos($fork_source, "bl_disk1 = '") === false);
backup_test('the tables are not in ITFlow\'s own database updates', strpos(file_get_contents($itflow_dir . '/db.sql'), 'fork_backup') === false && !glob($itflow_dir . '/admin/database_updates/*') === false);

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
