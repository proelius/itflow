<?php

/*
 * Late fees as a fixed amount, one for each reminder.
 *
 * The invoice settings can work a late fee out in one of two ways: a percent of
 * what is owed, every 30 days late (cron/nightly_tasks.php, as ITFlow has always
 * done it), or - set here - a fixed amount that each reminder from a given one on
 * adds to the invoice as a line of its own. Fees pile up: with 20.00 from the
 * second reminder, an invoice that is sent three reminders carries two fees, 40.00.
 *
 * Reminders are counted as the invoice settings schedule them (billingReminderRules(),
 * functions/billing_email.php): the first one after the due date is reminder 1, the
 * next reminder 2, and so on - the ones before the due date, or on it, are not
 * counted. When no more reminders follow the last one (the schedule is not set to
 * repeat), agents are told on that day that the invoice is ready for collection
 * proceedings (mise en poursuite).
 *
 * Nothing here runs unless "A fixed amount" is chosen in the settings: the
 * percent code in the nightly job is left as it is. The rules - which reminder it
 * is, whether it has a fee - take plain values and touch no database, so
 * scripts/late_fee_selftest.php can test them alone.
 */

/** The late fee settings - mode ('percent' or 'fixed'), fixed (the amount), from (the first reminder with a fee), enabled. Before database 3.6.4 the percent way, as before. */
function lateFeeSettings($fresh = false) {
    global $mysqli;
    static $settings = null;
    if ($settings !== null && !$fresh) {
        return $settings;
    }
    $settings = ['mode' => 'percent', 'fixed' => 0.0, 'from' => 2, 'enabled' => false];
    try {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_invoice_late_fee_enable, config_invoice_late_fee_mode, config_invoice_late_fee_fixed, config_invoice_late_fee_from
            FROM settings WHERE company_id = 1"));
    } catch (mysqli_sql_exception $e) {
        $row = null;
    }
    if ($row) {
        $settings['enabled'] = intval($row['config_invoice_late_fee_enable']) === 1;
        $settings['mode'] = $row['config_invoice_late_fee_mode'] === 'fixed' ? 'fixed' : 'percent';
        $settings['fixed'] = max(0, round(floatval($row['config_invoice_late_fee_fixed']), 2));
        $settings['from'] = max(1, min(9, intval($row['config_invoice_late_fee_from'])));
    }
    return $settings;
}

/** Is the late fee a fixed amount for each reminder? */
function lateFeeIsFixed() {
    $settings = lateFeeSettings();
    return $settings['enabled'] && $settings['mode'] === 'fixed';
}

/** The days late reminders are sent on, in order - the schedule's, from the first day after the due date. */
function lateFeeReminderDays(array $rules) {
    $days = array_values(array_filter(array_map('intval', $rules['days'] ?? []), function ($day) {
        return $day > 0;
    }));
    sort($days);
    return $days;
}

/**
 * Which reminder an invoice $days late is sent - 1 for the first after its due date, 2 for the
 * next - or 0 when none is sent that day. It follows billingReminderOnDay(): a day in the
 * schedule, or one of the days the schedule goes on repeating.
 */
function lateFeeReminderNumber($days, array $rules) {
    $days = intval($days);
    if ($days <= 0) {
        return 0;
    }
    $after = lateFeeReminderDays($rules);
    $index = array_search($days, $after, true);
    if ($index !== false) {
        return $index + 1;
    }
    $repeat = intval($rules['repeat'] ?? 0);
    $last = max(max($rules['days'] ?: [0]), 0);
    if ($repeat > 0 && $days > $last && ($days - $last) % $repeat === 0) {
        return count($after) + intdiv($days - $last, $repeat);
    }
    return 0;
}

/** Does reminder $number carry a fee? From reminder $from on, with an amount to charge. */
function lateFeeFixedDue($number, $from, $amount) {
    return intval($number) >= max(1, intval($from)) && round(floatval($amount), 2) > 0;
}

/** Is reminder $number the last one - the schedule does not repeat, and this is the final day in it? */
function lateFeeIsLastReminder($number, array $rules) {
    $after = lateFeeReminderDays($rules);
    return intval($rules['repeat'] ?? 0) === 0 && $after && intval($number) === count($after);
}

/** An amount with its currency, for a sentence - CHF 20.00. */
function lateFeeAmountText($amount, $currency) {
    return trim($currency . ' ' . number_format(floatval($amount), 2, '.', ''));
}

/** The reminders and their fees in words, for the settings page - so the schedule and the fee can be checked against each other. */
function lateFeePlanText(array $rules, $from, $amount, $currency) {
    $after = lateFeeReminderDays($rules);
    if (!$after) {
        return 'No reminder after the due date is set under Reminders and statements, so no fee is added.';
    }
    $parts = [];
    $total = 0.0;
    foreach ($after as $i => $day) {
        $number = $i + 1;
        $has_fee = lateFeeFixedDue($number, $from, $amount);
        $total += $has_fee ? round(floatval($amount), 2) : 0;
        $parts[] = "reminder $number ($day day" . ($day === 1 ? '' : 's') . ' late): ' . ($has_fee ? lateFeeAmountText($amount, $currency) . ' fee' : 'no fee');
    }
    $text = ucfirst(implode('; ', $parts)) . '.';
    $repeat = intval($rules['repeat'] ?? 0);
    if ($repeat > 0) {
        $text .= " Then a reminder every $repeat days" . (lateFeeFixedDue(count($after) + 1, $from, $amount) ? ', each with another ' . lateFeeAmountText($amount, $currency) . ' fee' : '') . '.';
    } else {
        $text .= ' ' . ($total > 0 ? 'Fees add up to ' . lateFeeAmountText($total, $currency) . ' on the last reminder. ' : '')
            . 'No reminder follows the last one: agents are told it is time for collection proceedings.';
    }
    return $text;
}

/** Has something been written to an invoice's history today that starts like this? */
function lateFeeHistoryToday($invoice_id, $description_prefix) {
    global $mysqli;
    $invoice_id = intval($invoice_id);
    $prefix = escapeSql($description_prefix);
    $sql = mysqli_query($mysqli, "SELECT history_id FROM history
        WHERE history_invoice_id = $invoice_id AND history_description LIKE '$prefix%' AND history_created_at >= CURDATE()
        LIMIT 1");
    return mysqli_num_rows($sql) > 0;
}

/**
 * What the nightly job does for an invoice on a reminder day when the late fee is a fixed
 * amount: add the fee, if this reminder has one, and tell agents when it is the last reminder.
 * Called before the reminder is emailed, so the reminder and its PDF show the fee. At most
 * once a day, however often the job runs. Returns the invoice as it is now.
 *
 * $invoice  the invoice (billingRow), $row its line of the reminders query (client_reminders),
 * $days     days after its due date, $rules billingReminderRules().
 */
function lateFeeFixedApply(array $invoice, array $row, $days, array $rules) {
    $number = lateFeeReminderNumber($days, $rules);
    // Not a reminder day, the client is left out of reminders (so is not charged for one), or the fee is not a fixed one
    if ($number === 0 || !intval($row['client_reminders'] ?? 0) || !lateFeeIsFixed()) {
        return $invoice;
    }
    $settings = lateFeeSettings();
    $invoice_id = intval($invoice['invoice_id']);
    $invoice_number = billingNumber('invoice', $invoice);
    $client_id = intval($invoice['client_id']);
    $client_name = (string) $invoice['client_name'];
    $currency = (string) $invoice['invoice_currency_code'];
    $url = "/agent/invoice.php?invoice_id=$invoice_id";

    if (lateFeeFixedDue($number, $settings['from'], $settings['fixed']) && !lateFeeHistoryToday($invoice_id, 'Cron applied a reminder fee') && !lateFeeHistoryToday($invoice_id, 'Cron did not apply a reminder fee')) {
        $company_currency = (string) (billingCompany()['company_currency'] ?? '');
        if ($company_currency !== '' && $currency !== '' && $company_currency !== $currency) {
            // The amount is in the company's currency - a bill in another one is left for an agent
            billingLog('invoice', $invoice_id, $invoice['invoice_status'], "Cron did not apply a reminder fee - the fee is set in $company_currency and this invoice is in $currency");
            appNotify("Invoice Late Charge", escapeSql("Invoice $invoice_number for $client_name is in $currency, so the fixed reminder fee (set in $company_currency) was not added - reminder $number"), $url, $client_id, $invoice_id);
        } else {
            // The line is written in the client's language, as the reminder is
            [$name, $description] = i18n_with(client_language($client_id), function () use ($number) {
                return [t('Reminder fee'), t('Fee for reminder {number}, applied on {date}', ['{number}' => $number, '{date}' => i18n_date('M j, Y')])];
            });
            billingItemSave('invoice', $invoice_id, ['name' => $name, 'description' => $description, 'qty' => 1, 'price' => $settings['fixed'], 'tax_id' => 0]);
            $amount_text = lateFeeAmountText($settings['fixed'], $currency);
            billingLog('invoice', $invoice_id, $invoice['invoice_status'], "Cron applied a reminder fee of $amount_text (reminder $number)");
            appNotify("Invoice Late Charge", escapeSql("Invoice $invoice_number for $client_name was charged a reminder fee of $amount_text with reminder $number"), $url, $client_id, $invoice_id);
            $invoice = billingRow('invoice', $invoice_id, false) ?: $invoice;
        }
    }

    // No reminder follows the last one: time to take the invoice further
    if (lateFeeIsLastReminder($number, $rules) && !lateFeeHistoryToday($invoice_id, 'Cron flagged the last reminder')) {
        billingLog('invoice', $invoice_id, $invoice['invoice_status'], "Cron flagged the last reminder (reminder $number of $number) - no more are sent, collection proceedings can begin");
        appNotify("Invoice Collection", escapeSql("Invoice $invoice_number for $client_name: reminder $number of $number is the last - no more are sent. It is time to start collection proceedings (mise en poursuite)"), $url, $client_id, $invoice_id);
    }

    return $invoice;
}
