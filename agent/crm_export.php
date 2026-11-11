<?php

/*
 * CRM export - the deals of the pipeline as a CSV file for a spreadsheet (semicolons, UTF-8 with a mark
 * so Excel reads the accents). The deals the agent may see, for the year, owner and search of the
 * pipeline page; reading only, nothing changes. The work is in functions/fork_crm.php.
 */

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../functions.php";
require_once __DIR__ . "/../includes/check_login.php";

forkCrmEnforce(1);

$year = intval($_GET['year'] ?? date('Y'));
$year = $year >= 2000 && $year <= 2100 ? $year : intval(date('Y'));
$deals = forkCrmDeals(['year' => $year, 'owner' => intval($_GET['owner'] ?? 0), 'search' => forkCrmTextClean($_GET['q'] ?? '', 100)]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="crm-deals-' . $year . '.csv"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
$head = ['Deal', 'For', 'Stage', 'Kind', 'Amount', 'A month', 'Probability %', 'Forecast', 'Expected close', 'Closed', 'Source', 'Owner', 'Contact', 'Email', 'Phone', 'Lost reason', 'Made'];
fwrite($out, implode(';', array_map('forkCrmCsvCell', $head)) . "\r\n");
foreach ($deals as $deal) {
    $row = [
        $deal['deal_title'], forkCrmDealWho($deal), $deal['stage_name'], $deal['stage_kind'], number_format((float) $deal['deal_amount'], 2, '.', ''),
        number_format((float) $deal['deal_monthly'], 2, '.', ''), $deal['deal_probability'], number_format(forkCrmWeighted($deal['deal_amount'], $deal['deal_probability']), 2, '.', ''),
        $deal['deal_expected_close'], $deal['deal_closed_at'], forkCrmSources()[$deal['deal_source']] ?? '', $deal['owner_name'], $deal['deal_contact'], $deal['deal_email'], $deal['deal_phone'],
        trim((forkCrmLostCategories()[$deal['deal_lost_category']] ?? '') . ((string) $deal['deal_lost_reason'] !== '' ? ' - ' . $deal['deal_lost_reason'] : ''), ' -'), $deal['deal_created_at'],
    ];
    fwrite($out, implode(';', array_map('forkCrmCsvCell', $row)) . "\r\n");
}
fclose($out);
logAudit("CRM", "Export", "$session_name exported the CRM deals of $year", 0, 0);
