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

echo "\nWho may use it\n";
$GLOBALS['session_is_admin'] = true;
crm_test('an admin role is let in', forkCrmRoleAllowed() === true);

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

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
