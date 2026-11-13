#!/usr/bin/env php
<?php
/**
 * Fork settings self-test - functions/fork_settings.php
 * The keys and defaults, what counts as changed, and that the fork's settings are not
 * in ITFlow's own tables or numbered database updates.
 *
 * CLI only - protected from web access
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

$itflow_dir = __DIR__ . '/..';
require_once $itflow_dir . '/functions/settings.php';
require_once $itflow_dir . '/functions/fork_settings.php';

$passed = 0;
$failed = 0;

function fork_test($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "  ✓ $name\n";
        $passed++;
    } else {
        echo "  ✗ $name\n";
        $failed++;
    }
}

echo "Fork settings\n";
$defaults = forkSettingsDefaults();
fork_test('four settings', count($defaults) === 4);
fork_test('late fee defaults to percent', $defaults['config_invoice_late_fee_mode'] === 'percent');
fork_test('fixed fee defaults to 0.00', $defaults['config_invoice_late_fee_fixed'] === '0.00');
fork_test('first fee from the 2nd reminder', $defaults['config_invoice_late_fee_from'] === '2');
fork_test('no QR-IBAN by default', $defaults['config_invoice_qr_iban'] === '');

echo "\nWhat changed\n";
fork_test('same values: nothing', forkSettingsChanged($defaults, $defaults) === []);
fork_test('20 and 20.00 are the same', forkSettingsChanged(['config_invoice_late_fee_fixed' => '20.00'], ['config_invoice_late_fee_fixed' => '20']) === []);
fork_test('mode changed', forkSettingsChanged($defaults, ['config_invoice_late_fee_mode' => 'fixed']) === ['config_invoice_late_fee_mode' => 'fixed']);
fork_test('unknown key ignored', forkSettingsChanged($defaults, ['config_nothing' => '1']) === []);
fork_test('a key not saved before counts as changed', forkSettingsChanged([], ['config_invoice_qr_iban' => 'CH4431999123000889012']) === ['config_invoice_qr_iban' => 'CH4431999123000889012']);

echo "\nNothing in ITFlow's own tables\n";
$updates = glob($itflow_dir . '/admin/database_updates/*.php');
$latest = '0';
foreach ($updates as $file) {
    $version = basename($file, '.php');
    if (version_compare($version, $latest, '>')) {
        $latest = $version;
    }
}
fork_test("latest database update is ITFlow's ($latest)", $latest === '3.6.5');
$db = (string) file_get_contents($itflow_dir . '/db.sql');
foreach (array_keys($defaults) as $key) {
    fork_test("$key is not a column of settings in db.sql", strpos($db, "`$key`") === false);
}

echo "\n" . ($failed === 0 ? "All $passed tests passed" : "$failed failed, $passed passed") . "\n";
exit($failed === 0 ? 0 : 1);
