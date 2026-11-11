#!/usr/bin/env php
<?php
/**
 * Fork CRM self-test - functions/fork_crm.php
 * Cleaning of input, forecast and win-rate figures, due dates, default stages, and that the CRM
 * tables are not in ITFlow's own database updates or db.sql. The queries are not run here (no
 * database): they are checked by use on the test server.
 *
 * CLI only - protected from web access
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

$itflow_dir = __DIR__ . '/..';
require_once $itflow_dir . '/functions/fork_crm.php';
require_once $itflow_dir . '/functions/fork_crm_extra.php';
require_once $itflow_dir . '/functions/fork_crm_close.php';

$passed = 0;
$failed = 0;

function crm_test($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "  ✓ $name\n";
        $passed++;
    } else {
        echo "  ✗ $name\n";
        $failed++;
    }
}

echo "Amounts\n";
crm_test('plain', forkCrmAmountClean('1500') === 1500.0);
crm_test('point decimal', forkCrmAmountClean('1500.50') === 1500.5);
crm_test('comma decimal', forkCrmAmountClean('1500,5') === 1500.5);
crm_test("swiss grouping 1'500.50", forkCrmAmountClean("1'500.50") === 1500.5);
crm_test('typographic apostrophe 1’500', forkCrmAmountClean('1’500') === 1500.0);
crm_test('space grouping 1 500,50', forkCrmAmountClean('1 500,50') === 1500.5);
crm_test('european 1.500,50', forkCrmAmountClean('1.500,50') === 1500.5);
crm_test('thousands only 1.500', forkCrmAmountClean('1.500') === 1500.0);
crm_test('three-digit comma group 12,345', forkCrmAmountClean('12,345') === 12345.0);
crm_test('letters give 0', forkCrmAmountClean('abc') === 0.0 && forkCrmAmountClean('12 CHF') === 0.0);
crm_test('negative gives 0', forkCrmAmountClean('-5') === 0.0);
crm_test('empty gives 0', forkCrmAmountClean('') === 0.0 && forkCrmAmountClean(null) === 0.0);
crm_test('capped', forkCrmAmountClean('99999999999') === 999999999.99);

echo "\nProbability, dates, text\n";
crm_test('probability clamped', forkCrmProbabilityClean(150) === 100 && forkCrmProbabilityClean(-3) === 0 && forkCrmProbabilityClean('40') === 40);
crm_test('real date kept', forkCrmDateClean('2026-12-31') === '2026-12-31');
crm_test('2026-02-30 is not a date', forkCrmDateClean('2026-02-30') === null);
crm_test('junk is not a date', forkCrmDateClean('31.12.2026') === null && forkCrmDateClean('') === null);
crm_test('tags removed', forkCrmTextClean('  <b>Hi</b> <script>x</script> ') === 'Hi x');
crm_test('cut to length, by characters', forkCrmTextClean('éééé', 2) === 'éé');
crm_test('kind clean', forkCrmKindClean('won') === 'won' && forkCrmKindClean('x') === 'open');
crm_test('type clean', forkCrmActivityTypeClean('call') === 'call' && forkCrmActivityTypeClean('x') === 'note');
crm_test('source clean', forkCrmSourceClean('referral') === 'referral' && forkCrmSourceClean('x') === '');

echo "\nDue dates\n";
crm_test('no date', forkCrmDueState(null, '2026-11-11') === 'none');
crm_test('overdue', forkCrmDueState('2026-11-10', '2026-11-11') === 'overdue');
crm_test('today', forkCrmDueState('2026-11-11', '2026-11-11') === 'today');
crm_test('soon, 7 days', forkCrmDueState('2026-11-18', '2026-11-11') === 'soon');
crm_test('later, 8 days', forkCrmDueState('2026-11-19', '2026-11-11') === 'later');
crm_test('across a year end', forkCrmDueState('2027-01-03', '2026-12-30') === 'soon');

echo "\nFigures\n";
crm_test('weighted', forkCrmWeighted(1000, 25) === 250.0 && forkCrmWeighted(333.33, 50) === 166.67);
$deals = [
    ['deal_amount' => '1000.00', 'deal_probability' => 50, 'stage_kind' => 'open', 'deal_expected_close' => '2026-12-15', 'deal_source' => 'referral'],
    ['deal_amount' => '2000.00', 'deal_probability' => 25, 'stage_kind' => 'open', 'deal_expected_close' => '2026-11-30', 'deal_source' => 'website'],
    ['deal_amount' => '500.00', 'deal_probability' => 75, 'stage_kind' => 'open', 'deal_expected_close' => null, 'deal_source' => 'website'],
    ['deal_amount' => '3000.00', 'deal_probability' => 100, 'stage_kind' => 'won', 'deal_expected_close' => '2026-10-01', 'deal_source' => 'referral'],
    ['deal_amount' => '1000.00', 'deal_probability' => 100, 'stage_kind' => 'won', 'deal_expected_close' => null, 'deal_source' => 'referral'],
    ['deal_amount' => '700.00', 'deal_probability' => 0, 'stage_kind' => 'lost', 'deal_expected_close' => null, 'deal_source' => 'website'],
];
$s = forkCrmSummarize($deals);
crm_test('open count and amount', $s['open_count'] === 3 && $s['open_amount'] === 3500.0);
crm_test('weighted forecast of open deals only', $s['weighted'] === 1375.0);
crm_test('won', $s['won_count'] === 2 && $s['won_amount'] === 4000.0 && $s['average_won'] === 2000.0);
crm_test('lost', $s['lost_count'] === 1 && $s['lost_amount'] === 700.0);
crm_test('win rate 2 of 3', $s['win_rate'] === 66.7);
$empty = forkCrmSummarize([]);
crm_test('nothing: no win rate, no average', $empty['win_rate'] === null && $empty['average_won'] === null && $empty['open_count'] === 0);
$f = forkCrmForecast($deals);
crm_test('forecast months in order, undated last', array_keys($f) === ['2026-11', '2026-12', 'none']);
crm_test('forecast amounts', $f['2026-11']['amount'] === 2000.0 && $f['2026-11']['weighted'] === 500.0 && $f['2026-12']['weighted'] === 500.0);
$g = forkCrmGroupSummary($deals, 'deal_source');
crm_test('by source', $g['referral']['won_count'] === 2 && $g['website']['open_count'] === 2 && $g['website']['lost_count'] === 1);

echo "\nQuotes of a deal\n";
$quotes = [
    ['quote_amount' => '1000.00', 'quote_currency_code' => 'CHF', 'quote_status' => 'Sent'],
    ['quote_amount' => '500.50', 'quote_currency_code' => 'CHF', 'quote_status' => 'Accepted'],
    ['quote_amount' => '900.00', 'quote_currency_code' => 'CHF', 'quote_status' => 'Declined'],
    ['quote_amount' => '300.00', 'quote_currency_code' => 'EUR', 'quote_status' => 'Sent'],
    ['quote_amount' => '200.00', 'quote_currency_code' => 'CHF', 'quote_status' => 'Expired'],
];
crm_test('total: the quotes still in play, in the company currency', forkCrmQuotesTotal($quotes, 'CHF') === 1500.5);
crm_test('total of none is 0', forkCrmQuotesTotal([], 'CHF') === 0.0);
crm_test('total in another currency', forkCrmQuotesTotal($quotes, 'EUR') === 300.0);

echo "\nPostal address\n";
$countries = ['Switzerland', 'France'];
[$a, $e] = forkCrmAddressClean(['address' => ' Rue du Lac 1 ', 'zip' => '1003', 'city' => 'Lausanne', 'country' => 'Switzerland'], $countries);
crm_test('a full address is accepted and trimmed', !$e && $a['address'] === 'Rue du Lac 1' && $a['state'] === '');
[$a, $e] = forkCrmAddressClean(['address' => '', 'zip' => '', 'city' => '', 'country' => 'Switzerland'], $countries);
crm_test('street, zip and city are required', array_keys($e) === ['address', 'zip', 'city']);
[$a, $e] = forkCrmAddressClean(['address' => 'x', 'zip' => '1', 'city' => 'y', 'country' => 'Atlantis'], $countries);
crm_test('the country must be one ITFlow offers', array_keys($e) === ['country']);
[$a, $e] = forkCrmAddressClean(['address' => '<b>x</b>', 'zip' => '1', 'city' => 'y', 'country' => ''], $countries);
crm_test('no country is refused, tags are removed', array_keys($e) === ['country'] && $a['address'] === 'x');
crm_test('the handler asks for the address', strpos(file_get_contents($itflow_dir . '/agent/post/crm.php'), 'forkCrmAddressClean(') !== false);

echo "\nFollow-ups\n";
$defaults = [];
foreach (forkCrmDefaultStages() as $st) {
    $defaults[$st[0]] = $st[3];
}
crm_test('Quote sent follows up after 14 days, no other stage does', $defaults['Quote sent'] === 14 && array_sum($defaults) === 14);
crm_test('only open stages follow up', array_sum(array_map(function ($st) { return $st[1] !== 'open' ? $st[3] : 0; }, forkCrmDefaultStages())) === 0);
crm_test('days clamped', forkCrmFollowUpDaysClean(-5) === 0 && forkCrmFollowUpDaysClean('14') === 14 && forkCrmFollowUpDaysClean(9999) === 365);
crm_test('due two weeks later', forkCrmFollowUpDate('2026-11-11', 14) === '2026-11-25');
crm_test('across a year end', forkCrmFollowUpDate('2026-12-25', 14) === '2027-01-08');
crm_test('across a leap day', forkCrmFollowUpDate('2028-02-20', 14) === '2028-03-05');
$handler_code = file_get_contents($itflow_dir . '/agent/post/crm.php');
crm_test('moving a deal makes the follow-up task', strpos($handler_code, 'forkCrmFollowUpCreate(') !== false);
crm_test('the nightly job tells the agents', strpos(file_get_contents($itflow_dir . '/cron/nightly_tasks.php'), 'forkCrmNightly()') !== false);
crm_test('the stages page sets the delay', strpos(file_get_contents($itflow_dir . '/agent/crm_stages.php'), 'followup_days') !== false);

echo "\nQuotes that move a deal\n";
$st = [
    1 => ['stage_id' => 1, 'stage_order' => 1, 'stage_kind' => 'open', 'stage_quote_trigger' => ''],
    2 => ['stage_id' => 2, 'stage_order' => 2, 'stage_kind' => 'open', 'stage_quote_trigger' => 'sent'],
    3 => ['stage_id' => 3, 'stage_order' => 3, 'stage_kind' => 'open', 'stage_quote_trigger' => ''],
    4 => ['stage_id' => 4, 'stage_order' => 4, 'stage_kind' => 'won', 'stage_quote_trigger' => 'accepted'],
    5 => ['stage_id' => 5, 'stage_order' => 5, 'stage_kind' => 'lost', 'stage_quote_trigger' => 'declined'],
];
crm_test('a quote sent brings the deal forward', forkCrmQuoteTarget(['n' => 1, 'sent' => 1], $st, 1)['stage_id'] === 2);
crm_test('a quote sent never takes a deal back', forkCrmQuoteTarget(['n' => 1, 'sent' => 1], $st, 3) === null);
crm_test('an accepted quote wins the deal, from anywhere', forkCrmQuoteTarget(['n' => 2, 'accepted' => 1, 'sent' => 1], $st, 1)['stage_id'] === 4);
crm_test('all quotes declined loses it, with the reason', ($t = forkCrmQuoteTarget(['n' => 2, 'declined' => 2], $st, 2)) && $t['stage_id'] === 5 && $t['category'] === 'quote');
crm_test('one declined of two does not lose it', forkCrmQuoteTarget(['n' => 2, 'declined' => 1], $st, 2) === null);
crm_test('a declined one and an accepted one: won', forkCrmQuoteTarget(['n' => 2, 'declined' => 1, 'accepted' => 1], $st, 2)['stage_id'] === 4);
crm_test('a deal already won stays', forkCrmQuoteTarget(['n' => 1, 'declined' => 1], $st, 4) === null);
crm_test('drafts alone change nothing', forkCrmQuoteTarget(['n' => 1], $st, 1) === null);
crm_test('no stage set for the trigger: nothing', forkCrmQuoteTarget(['n' => 1, 'accepted' => 1], [1 => $st[1]], 1) === null);
crm_test('a trigger fits its kind only', forkCrmTriggerClean('sent', 'open') === 'sent' && forkCrmTriggerClean('sent', 'won') === '' && forkCrmTriggerClean('accepted', 'won') === 'accepted' && forkCrmTriggerClean('x', 'open') === '');

echo "\nTime in each stage\n";
$t0 = 1000000;
$times = forkCrmStageTimes([['from' => 1, 'to' => 2, 'at' => $t0 + 86400], ['from' => 2, 'to' => 3, 'at' => $t0 + 86400 * 4]], $t0, 3, $t0 + 86400 * 6);
crm_test('a day, three days, two days', $times === [1 => 86400, 2 => 259200, 3 => 172800]);
crm_test('never moved: all in the current stage', forkCrmStageTimes([], $t0, 2, $t0 + 500) === [2 => 500]);
$back = forkCrmStageTimes([['from' => 1, 'to' => 2, 'at' => $t0 + 100], ['from' => 2, 'to' => 1, 'at' => $t0 + 300]], $t0, 1, $t0 + 400);
crm_test('back to a stage: its time adds up', $back === [1 => 200, 2 => 200]);
crm_test('durations in words', forkCrmDurationWords(90000) === '1 d 1 h' && forkCrmDurationWords(7200) === '2 h' && forkCrmDurationWords(60) === 'under an hour' && forkCrmDurationWords(172800) === '2 d');

echo "\nDormant deals\n";
crm_test('stale after the days', forkCrmIsStale(0, 15 * 86400, 14) && !forkCrmIsStale(0, 14 * 86400, 14));
crm_test('stale days clamped', forkCrmStaleDaysClean(0) === 1 && forkCrmStaleDaysClean(9999) === 365 && forkCrmStaleDaysClean('14') === 14);

echo "\nFunnel, cycle, months, CSV\n";
$f = forkCrmFunnel([2 => 1, 3 => 2, 4 => 3], [10 => 1, 11 => 2, 12 => 3, 13 => PHP_INT_MAX, 14 => 0]);
crm_test('funnel counts who got that far', $f[2]['reached'] === 4 && $f[3]['reached'] === 3 && $f[4]['reached'] === 2);
crm_test('funnel rates', $f[3]['of_previous'] === 75.0 && $f[4]['of_first'] === 50.0 && $f[2]['of_previous'] === null);
crm_test('an empty funnel has no rate', forkCrmFunnel([2 => 1], [])[2]['of_first'] === null);
$won = [
    ['stage_kind' => 'won', 'deal_created_at' => '2026-01-01 00:00:00', 'deal_closed_at' => '2026-01-11 00:00:00', 'deal_amount' => '1000'],
    ['stage_kind' => 'won', 'deal_created_at' => '2026-02-01 00:00:00', 'deal_closed_at' => '2026-02-21 00:00:00', 'deal_amount' => '500.5'],
    ['stage_kind' => 'won', 'deal_created_at' => '2026-02-02 00:00:00', 'deal_closed_at' => '2026-03-02 00:00:00', 'deal_amount' => '250'],
    ['stage_kind' => 'open', 'deal_created_at' => '2026-01-01 00:00:00', 'deal_closed_at' => null, 'deal_amount' => '9'],
];
crm_test('average sales cycle', forkCrmAverageCycle($won) === 19.3);
crm_test('no won deal: no cycle', forkCrmAverageCycle([$won[3]]) === null);
$m = forkCrmWonByMonth($won);
crm_test('won by month, in order', array_keys($m) === ['2026-01', '2026-02', '2026-03'] && $m['2026-02']['amount'] === 500.5);
crm_test('CSV: plain text as it is', forkCrmCsvCell('Hello') === 'Hello');
crm_test('CSV: a separator or quote is quoted', forkCrmCsvCell('a;b') === '"a;b"' && forkCrmCsvCell('say "hi"') === '"say ""hi"""');
crm_test('CSV: a line break is quoted', forkCrmCsvCell("a\nb") === "\"a\nb\"");
crm_test('CSV: a formula is made harmless', forkCrmCsvCell('=1+1') === "'=1+1" && forkCrmCsvCell('@x') === "'@x" && forkCrmCsvCell('-5') === "'-5");

echo "\nMonthly amounts\n";
$ms = forkCrmSummarize([['deal_amount' => '100', 'deal_monthly' => '10', 'deal_probability' => 50, 'stage_kind' => 'open'], ['deal_amount' => '200', 'deal_monthly' => '20', 'deal_probability' => 100, 'stage_kind' => 'won'], ['deal_amount' => '5', 'deal_probability' => 0, 'stage_kind' => 'lost']]);
crm_test('recurring open and won', $ms['open_monthly'] === 10.0 && $ms['won_monthly'] === 20.0);
$mq = [['quote_amount' => '1000', 'quote_monthly_amount' => '50', 'quote_currency_code' => 'CHF', 'quote_status' => 'Sent'], ['quote_amount' => '1', 'quote_monthly_amount' => '9', 'quote_currency_code' => 'CHF', 'quote_status' => 'Declined']];
crm_test('the monthly total of the quotes in play', forkCrmQuotesTotal($mq, 'CHF', 'quote_monthly_amount') === 50.0);
crm_test('default stages carry the quote triggers', array_column(forkCrmDefaultStages(), 4) === ['', '', 'sent', '', 'accepted', 'declined']);
crm_test('lost reasons and contact roles', isset(forkCrmLostCategories()['price']) && forkCrmLostCategoryClean('x') === '' && forkCrmContactRoles()[''] === '-');
$page_code = file_get_contents($itflow_dir . '/agent/crm_deal.php');
foreach (['create_crm_quote', 'link_crm_contact', 'send_crm_email', 'amount_from_crm_quotes'] as $action) {
    crm_test("the deal page and the handler have $action", strpos($page_code, $action) !== false && strpos(file_get_contents($itflow_dir . '/agent/post/crm.php'), "isset(\$_POST['$action'])") !== false);
}
crm_test('the nightly job syncs quotes, tells dormant deals and sends the digest', strpos(file_get_contents($itflow_dir . '/functions/fork_crm.php'), 'forkCrmSyncQuotes();') !== false && strpos(file_get_contents($itflow_dir . '/functions/fork_crm.php'), 'forkCrmStaleNightly();') !== false && strpos(file_get_contents($itflow_dir . '/functions/fork_crm.php'), 'forkCrmDigest();') !== false);
crm_test('an email goes only to the deal or its contacts', strpos(file_get_contents($itflow_dir . '/agent/post/crm.php'), "forkCrmDealContacts(") !== false);

echo "\nMeetings, calls and their debrief\n";
crm_test('time of day', forkCrmTimeClean('14:30') === '14:30' && forkCrmTimeClean('9:05') === '09:05' && forkCrmTimeClean('14h30') === '14:30' && forkCrmTimeClean('14:30:00') === '14:30');
crm_test('not a time', forkCrmTimeClean('24:00') === null && forkCrmTimeClean('12:60') === null && forkCrmTimeClean('noon') === null && forkCrmTimeClean('') === null);
crm_test('lengths', forkCrmMinutesClean(45) === 45 && forkCrmMinutesClean(7) === 60 && forkCrmMinutesClean('') === 60);
crm_test('when, with and without a time', forkCrmWhen('2026-11-20', '14:30') === '2026-11-20 14:30' && forkCrmWhen('2026-11-20', '') === '2026-11-20' && forkCrmWhen('x', '14:30') === '');
crm_test('a planned meeting needs a debrief; a logged one or a task does not',
    forkCrmNeedsDebrief(['activity_type' => 'meeting', 'activity_due' => '2026-11-20', 'activity_done_at' => null])
    && forkCrmNeedsDebrief(['activity_type' => 'call', 'activity_due' => '2026-11-20', 'activity_done_at' => null])
    && !forkCrmNeedsDebrief(['activity_type' => 'task', 'activity_due' => '2026-11-20', 'activity_done_at' => null])
    && !forkCrmNeedsDebrief(['activity_type' => 'call', 'activity_due' => null, 'activity_done_at' => '2026-11-01 10:00:00'])
    && !forkCrmNeedsDebrief(['activity_type' => 'meeting', 'activity_due' => '2026-11-20', 'activity_done_at' => '2026-11-20 15:00:00']));
$ics = forkCrmIcs(['activity_id' => 7, 'activity_type' => 'meeting', 'activity_due' => '2026-11-20', 'activity_due_time' => '14:30', 'activity_minutes' => 45, 'activity_text' => "Fix the price; bring, the quote\nSecond line"], ['deal_title' => 'ACME IT', 'deal_client_id' => 0, 'deal_prospect' => 'ACME SA'], 1763000000, 'itflow.example.ch');
crm_test('ics: a calendar with one event', strpos($ics, "BEGIN:VCALENDAR\r\n") === 0 && substr_count($ics, 'BEGIN:VEVENT') === 1 && substr($ics, -15) === "END:VCALENDAR\r\n");
crm_test('ics: the start and the end, 45 minutes later', strpos($ics, "DTSTART:20261120T143000\r\n") !== false && strpos($ics, "DTEND:20261120T151500\r\n") !== false);
crm_test('ics: ; , and line breaks are escaped', strpos($ics, 'Fix the price\\; bring\\, the quote\\nSecond line') !== false);
crm_test('ics: a stable id', strpos($ics, "UID:crm-7@itflow.example.ch\r\n") !== false);
crm_test('ics: every line ends in CRLF and none is longer than 75 bytes', !preg_match('/(?<!\r)\n/', $ics) && max(array_map('strlen', explode("\r\n", $ics))) <= 75);
$ics_long = forkCrmIcs(['activity_id' => 8, 'activity_type' => 'call', 'activity_due' => '2026-11-20', 'activity_due_time' => '', 'activity_minutes' => 0, 'activity_text' => str_repeat('é', 120)], ['deal_title' => 'D', 'deal_client_id' => 0, 'deal_prospect' => ''], 1763000000);
crm_test('ics: long lines are folded without cutting a character', max(array_map('strlen', explode("\r\n", $ics_long))) <= 75 && strpos(str_replace("\r\n ", '', $ics_long), str_repeat('é', 120)) !== false);
crm_test('ics: no time is a whole day', strpos($ics_long, "DTSTART;VALUE=DATE:20261120\r\n") !== false && strpos($ics_long, "DTEND;VALUE=DATE:20261121\r\n") !== false);
$deal_page = file_get_contents($itflow_dir . '/agent/crm_deal.php');
$handler_all = file_get_contents($itflow_dir . '/agent/post/crm.php');
crm_test('the deal page can log a call and plan the next step', strpos($deal_page, 'crmNextStepFields(') !== false && strpos($handler_all, 'crmPostNextStep(') !== false);
crm_test('the debrief form and its handler', strpos($deal_page, 'complete_crm_activity') !== false && strpos($handler_all, "isset(\$_POST['complete_crm_activity'])") !== false);
crm_test('the ics page checks the role and the deal', strpos(file_get_contents($itflow_dir . '/agent/crm_ics.php'), 'forkCrmEnforce(1)') !== false && strpos(file_get_contents($itflow_dir . '/agent/crm_ics.php'), 'forkCrmDeal(') !== false);

echo "\nWho may use it\n";
$GLOBALS['session_is_admin'] = true;
crm_test('an admin role is let in', forkCrmRoleAllowed() === true);
$gate = file_get_contents($itflow_dir . '/functions/fork_crm.php');
crm_test('the gate is the admin role or the Sales module, no role name', strpos($gate, "lookupUserPermission('module_sales') >= 1") !== false && stripos($gate, 'permissionRoleName') === false);
crm_test('the agents who hear are the admins and the roles with the Sales module', strpos(file_get_contents($itflow_dir . '/functions/fork_crm_extra.php'), "m.module_name = 'module_sales'") !== false);

echo "\nStages\n";
$stages = forkCrmDefaultStages();
$kinds = array_column($stages, 1);
crm_test('six default stages', count($stages) === 6);
crm_test('one won and one lost, the rest open', array_count_values($kinds) === ['open' => 4, 'won' => 1, 'lost' => 1]);
crm_test('won is 100, lost is 0', $stages[4][2] === 100 && $stages[5][2] === 0);
crm_test('open probabilities rise', $stages[0][2] < $stages[1][2] && $stages[1][2] < $stages[2][2] && $stages[2][2] < $stages[3][2]);

echo "\nOutside ITFlow's own updates\n";
$db = file_get_contents($itflow_dir . '/db.sql');
crm_test('no fork_crm table in db.sql', strpos($db, 'fork_crm_') === false);
$hits = 0;
foreach (glob($itflow_dir . '/admin/database_updates*.php') as $file) {
    $hits += substr_count(file_get_contents($file), 'fork_crm_');
}
crm_test('no fork_crm table in the database updates', $hits === 0);

echo "\nHandler and pages\n";
$handler = file_get_contents($itflow_dir . '/agent/post/crm.php');
preg_match_all('/if \(isset\(\$_POST\[\'([a-z_]+)\'\]\)\) \{(.*?)\n\}\n/s', $handler, $blocks, PREG_SET_ORDER);
crm_test('the handler has actions', count($blocks) >= 10);
$bad = [];
foreach ($blocks as $block) {
    if (strpos($block[2], 'validateCSRFToken()') === false) {
        $bad[] = $block[1] . ' (csrf)';
    }
    if (strpos($block[2], 'forkCrmEnforce(') === false && strpos($block[2], 'enforceUserPermission(') === false && strpos($block[2], 'crmPostDeal(') === false && strpos($block[2], 'crmPostActivity(') === false) {
        $bad[] = $block[1] . ' (permission)';
    }
}
crm_test('every action checks the CSRF token and a permission' . ($bad ? ': ' . implode(', ', $bad) : ''), !$bad);
crm_test('the handler does not look at the Sales level on its own', strpos($handler, "'module_sales'") === false);
crm_test('the menu and the common parts go through the CRM level', strpos(file_get_contents($itflow_dir . '/agent/includes/crm_common.php'), "'module_sales'") === false);
crm_test('no GET action in the handler', strpos($handler, '$_GET') === false);
foreach (glob($itflow_dir . '/agent/crm*.php') as $page) {
    $code = file_get_contents($page);
    crm_test(basename($page) . ' checks the role and the permission', strpos($code, 'forkCrmEnforce(') !== false);
    crm_test(basename($page) . ' does not look at the Sales level on its own', strpos($code, "'module_sales'") === false);
    crm_test(basename($page) . ' escapes nothing raw: no echo of $_GET/$_POST', !preg_match('/<\?=\s*\$_(GET|POST|REQUEST)/', $code));
}

echo "\nAfter a quote is answered and after a deal is closed\n";
crm_test('days: 0 stays 0', forkCrmCloseDaysClean(0) === 0);
crm_test('days: negative becomes 0', forkCrmCloseDaysClean(-5) === 0);
crm_test('days: text becomes 0', forkCrmCloseDaysClean('abc') === 0);
crm_test('days: 30 stays 30', forkCrmCloseDaysClean('30') === 30);
crm_test('days: capped at 365', forkCrmCloseDaysClean(9999) === 365);
$defaults = forkCrmSettingDefaults();
crm_test('defaults: check-in 30 days, recontact 90 days, team told', $defaults['won_checkin_days'] === '30' && $defaults['lost_recontact_days'] === '90' && $defaults['won_notify_team'] === '1');
$none = ['client' => false, 'accepted' => 0, 'invoiced' => 0, 'monthly' => 0, 'onboarding' => false, 'emailed' => false];
crm_test('step client: a prospect is to do', forkCrmStepState('client', $none) === 'todo');
crm_test('step client: a client is done', forkCrmStepState('client', ['client' => true] + $none) === 'done');
crm_test('step invoice: no quote is not needed', forkCrmStepState('invoice', $none) === 'na');
crm_test('step invoice: an accepted quote is to do', forkCrmStepState('invoice', ['accepted' => 1] + $none) === 'todo');
crm_test('step invoice: an accepted and an invoiced quote is still to do', forkCrmStepState('invoice', ['accepted' => 1, 'invoiced' => 1] + $none) === 'todo');
crm_test('step invoice: only invoiced quotes is done', forkCrmStepState('invoice', ['invoiced' => 2] + $none) === 'done');
crm_test('step contract: no monthly amount is not needed', forkCrmStepState('contract', $none) === 'na');
crm_test('step contract: a monthly amount is to do', forkCrmStepState('contract', ['monthly' => 120.0] + $none) === 'todo');
crm_test('step onboarding: not started is to do, started is done', forkCrmStepState('onboarding', $none) === 'todo' && forkCrmStepState('onboarding', ['onboarding' => true] + $none) === 'done');
crm_test('step welcome: no email is to do, an email is done', forkCrmStepState('welcome', $none) === 'todo' && forkCrmStepState('welcome', ['emailed' => true] + $none) === 'done');
crm_test('what an agent said wins: skip is not needed', forkCrmStepState('client', $none, 'skip') === 'na');
crm_test('what an agent said wins: done is done', forkCrmStepState('invoice', ['accepted' => 1] + $none, 'done') === 'done');
crm_test('an unknown step is not needed', forkCrmStepState('nothing', $none) === 'na');
crm_test('five next steps', array_keys(forkCrmStepLabels()) === ['client', 'invoice', 'contract', 'onboarding', 'welcome']);
$close_code = file_get_contents($itflow_dir . '/functions/fork_crm_close.php');
$crm_code = file_get_contents($itflow_dir . '/functions/fork_crm.php');
crm_test('moving a deal runs the close hook', strpos($crm_code, 'forkCrmAfterClose(') !== false);
crm_test('the steps table is made with the other CRM tables', strpos($crm_code, 'fork_crm_deal_steps') !== false);
crm_test('the check-in and recontact tasks stay on the task lists', substr_count($crm_code, 'a.activity_auto = 2') >= 3);
crm_test('the close module never invoices, archives clients or writes accounting', !preg_match('/INSERT INTO (invoices|clients|contracts|recurring)|UPDATE (invoices|clients)/', $close_code));
$billing = file_get_contents($itflow_dir . '/functions/billing.php');
$quote_post = file_get_contents($itflow_dir . '/agent/post/quote.php');
crm_test('the portal answer calls the CRM hook behind function_exists', preg_match("/function_exists\('forkCrmQuoteChanged'\)\) \{\s+forkCrmQuoteChanged\(\\\$quote_id, \\\$word\);/", $billing) === 1);
crm_test('the agent accept and decline call the CRM hook behind function_exists', preg_match_all("/function_exists\('forkCrmQuoteChanged'\)\) \{\s+forkCrmQuoteChanged\(\\\$quote_id, '(Accepted|Declined)'\);/", $quote_post) === 2);
crm_test('the handler has the step and decline actions', strpos($handler, "isset(\$_POST['set_crm_step'])") !== false && strpos($handler, "isset(\$_POST['decline_crm_quotes'])") !== false);
crm_test('the settings are saved by the handler and shown on the stages page', strpos($handler, "'won_checkin_days'") !== false && strpos(file_get_contents($itflow_dir . '/agent/crm_stages.php'), 'lost_recontact_days') !== false);
$mail_keys_ok = true;
$en = include $itflow_dir . '/lang/en.php';
$fr = include $itflow_dir . '/lang/fr.php';
foreach (['Welcome aboard', 'Thank you for your time', 'If your needs change, we will gladly start again.'] as $key) {
    $mail_keys_ok = $mail_keys_ok && isset($en[$key], $fr[$key]);
}
crm_test('the welcome and goodbye email texts are in both language files', $mail_keys_ok);

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
