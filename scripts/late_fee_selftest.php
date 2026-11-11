#!/usr/bin/env php
<?php
/**
 * Fixed late fee self-test - functions/late_fee_fixed.php
 * Which reminder an invoice is sent on a given day, which reminders carry a fee, which one is
 * the last, and that all of it agrees with the schedule the nightly job really follows
 * (billingReminderOnDay()).
 *
 * CLI only - protected from web access
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

$itflow_dir = __DIR__ . '/..';
require_once $itflow_dir . '/functions/i18n.php';
require_once $itflow_dir . '/functions/billing_email.php';
require_once $itflow_dir . '/functions/late_fee_fixed.php';

$passed = 0;
$failed = 0;

function fee_test($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "  ✓ $name\n";
        $passed++;
    } else {
        echo "  ✗ $name\n";
        $failed++;
    }
}

function fee_rules(array $days, $repeat) {
    return ['enabled' => true, 'days' => $days, 'repeat' => $repeat, 'statement_monthly' => false, 'statement_day' => 1, 'attach_pdf' => true];
}

echo "Fixed late fee\n";

// Three reminders, then nothing: the first the day after the due date, the others 30 days apart
$three = fee_rules([1, 31, 61], 0);
fee_test('day 1 is reminder 1', lateFeeReminderNumber(1, $three) === 1);
fee_test('day 31 is reminder 2', lateFeeReminderNumber(31, $three) === 2);
fee_test('day 61 is reminder 3', lateFeeReminderNumber(61, $three) === 3);
fee_test('no reminder after the third', lateFeeReminderNumber(91, $three) === 0 && lateFeeReminderNumber(121, $three) === 0);
fee_test('a day between two reminders is none', lateFeeReminderNumber(2, $three) === 0 && lateFeeReminderNumber(45, $three) === 0);
fee_test('the due date and the days before it are none', lateFeeReminderNumber(0, $three) === 0 && lateFeeReminderNumber(-3, $three) === 0);

// Reminders before and on the due date are not counted
$mixed = fee_rules([-7, -3, 0, 7, 14], 0);
fee_test('reminders before or on the due date are not counted: day 7 is reminder 1, day 14 reminder 2', lateFeeReminderNumber(7, $mixed) === 1 && lateFeeReminderNumber(14, $mixed) === 2 && lateFeeReminderNumber(0, $mixed) === 0);

// A schedule that goes on repeating
$repeat = fee_rules([1, 30], 30);
fee_test('with a repeat the count goes on: 1, 30, 60, 90 are reminders 1 to 4', array_map(function ($d) use ($repeat) { return lateFeeReminderNumber($d, $repeat); }, [1, 30, 60, 90]) === [1, 2, 3, 4]);
fee_test('a day off the repeat is none', lateFeeReminderNumber(45, $repeat) === 0 && lateFeeReminderNumber(61, $repeat) === 0);
$only_repeat = fee_rules([0], 30);
fee_test('a repeat with no day after the due date starts at its first step', lateFeeReminderNumber(30, $only_repeat) === 1 && lateFeeReminderNumber(60, $only_repeat) === 2);
fee_test('nothing after the due date and no repeat: none', lateFeeReminderNumber(10, fee_rules([-3], 0)) === 0 && lateFeeReminderNumber(10, fee_rules([], 0)) === 0);

// It agrees with the nightly job, day by day
$agree = true;
foreach ([$three, $mixed, $repeat, $only_repeat, fee_rules([], 0), fee_rules([1], 7), fee_rules([5, 10, 20], 15)] as $rules) {
    for ($day = 1; $day <= 400; $day++) {
        if ((lateFeeReminderNumber($day, $rules) > 0) !== billingReminderOnDay($day, $rules)) {
            $agree = false;
        }
    }
}
fee_test('a reminder number exists on exactly the days billingReminderOnDay() sends one (400 days, 7 schedules)', $agree);
$counting = true;
$previous = 0;
for ($day = 1; $day <= 400; $day++) {
    $number = lateFeeReminderNumber($day, $repeat);
    if ($number > 0) {
        $counting = $counting && $number === $previous + 1;
        $previous = $number;
    }
}
fee_test('reminders are numbered 1, 2, 3... with no gap', $counting && $previous > 10);

// Which carry a fee
fee_test('from reminder 2: none on the first, one on the second and the third', !lateFeeFixedDue(1, 2, 20) && lateFeeFixedDue(2, 2, 20) && lateFeeFixedDue(3, 2, 20));
fee_test('from reminder 1: every reminder', lateFeeFixedDue(1, 1, 20));
fee_test('no amount, no fee', !lateFeeFixedDue(3, 2, 0) && !lateFeeFixedDue(3, 2, 0.004));
fee_test('a from below 1 counts as 1', lateFeeFixedDue(1, 0, 20));

// The last one
fee_test('the third of three is the last', lateFeeIsLastReminder(3, $three) && !lateFeeIsLastReminder(2, $three) && !lateFeeIsLastReminder(1, $three));
fee_test('with a repeat there is no last', !lateFeeIsLastReminder(3, $repeat) && !lateFeeIsLastReminder(1, $repeat));
fee_test('no reminder after the due date: no last', !lateFeeIsLastReminder(1, fee_rules([-3, 0], 0)));
fee_test('the last reminder is the one that is not followed by another, for each schedule', (function () use ($three, $mixed) {
    foreach ([$three, $mixed] as $rules) {
        $numbers = [];
        for ($day = 1; $day <= 400; $day++) {
            if (($n = lateFeeReminderNumber($day, $rules)) > 0) {
                $numbers[] = $n;
            }
        }
        if (!$numbers || !lateFeeIsLastReminder(max($numbers), $rules) || lateFeeIsLastReminder(max($numbers) - 1 ?: 99, $rules)) {
            return false;
        }
    }
    return true;
})());

// What the settings page says
$plan = lateFeePlanText($three, 2, 20, 'CHF');
fee_test('the plan names each reminder, its day and its fee', strpos($plan, 'Reminder 1 (1 day late): no fee') === 0 && strpos($plan, 'reminder 2 (31 days late): CHF 20.00 fee') !== false && strpos($plan, 'reminder 3 (61 days late): CHF 20.00 fee') !== false);
fee_test('the plan adds the fees up and says collection follows the last reminder', strpos($plan, 'Fees add up to CHF 40.00') !== false && strpos($plan, 'collection proceedings') !== false);
fee_test('the plan of a repeating schedule says each reminder has a fee', strpos(lateFeePlanText($repeat, 2, 20, 'CHF'), 'Then a reminder every 30 days, each with another CHF 20.00 fee') !== false);
fee_test('with no reminder after the due date the plan says no fee is added', strpos(lateFeePlanText(fee_rules([-3], 0), 2, 20, 'CHF'), 'no fee is added') !== false);
fee_test('an amount is written with two decimals', lateFeeAmountText(20, 'CHF') === 'CHF 20.00' && lateFeeAmountText(7.5, '') === '7.50');

// The names of the fees, and what a reminder email shows
fee_test('the fees are named for their reminder, in English', [lateFeeLabel(1), lateFeeLabel(2), lateFeeLabel(3), lateFeeLabel(4), lateFeeLabel(5), lateFeeLabel(6)] === ['First reminder fee', 'Second reminder fee', 'Third reminder fee', 'Fourth reminder fee', 'Fifth reminder fee', 'Reminder fee']);
$french = i18n_with('fr', function () {
    return [lateFeeLabel(2), lateFeeLabel(3), t('Later reminder fees')];
});
fee_test('and in French', $french === ['Frais de deuxième rappel', 'Frais de troisième rappel', 'Frais des rappels suivants']);
$history = [
    'Reminder emailed to Contact Test (contact.test@example.com) - 31 days late',
    'Cron applied a reminder fee of CHF 30.00 (reminder 2)',
    'Cron did not apply a reminder fee - the fee is set in CHF and this invoice is in EUR',
    'Cron applied a reminder fee of CHF 30.50 (reminder 3)',
    'Cron applied a reminder fee of 30.00 (reminder 2)',
    'Cron applied a late fee of CHF 12.00',
];
fee_test('the fees of an invoice are read from its history, each reminder once, in order', lateFeeParseHistory($history) === [2 => 30.0, 3 => 30.5]);
fee_test('an invoice with no reminder fee has none', lateFeeParseHistory(['Cron applied a late fee of CHF 12.00', 'Reminder emailed to X - 1 day late']) === []);
fee_test('what the job writes is what is read back', lateFeeParseHistory(['Cron applied a reminder fee of ' . lateFeeAmountText(30, 'CHF') . ' (reminder 4)']) === [4 => 30.0]);

echo "\n=== Summary ===\nTests passed: $passed\nTests failed: $failed\n";
if ($failed > 0) {
    exit(1);
}
echo "\n✓ All tests passed!\n";
