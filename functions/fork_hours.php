<?php

/*
 * Prepaid hours, the fork's additions to functions/contract_hours.php.
 *
 *  - A block bought on an invoice waits ("pending") until that invoice is paid in
 *    full, then is good to draw from (forkHoursActivate(), called by
 *    invoicePaidFollowUp()). A block made without an invoice, or one that
 *    existed before, is good at once.
 *  - How time is counted is set for each contract - to the minute (the default),
 *    or in slices of 5 or 15 minutes, to the nearest slice - and is
 *    copied into each block when it is made, so changing the contract's setting
 *    later does not change hours already bought. A ticket is counted by the
 *    rule of the first block that covers it, and keeps it. A block with no rule
 *    (made before this) leaves the ticket to the general billing time rules.
 *    Hours are kept to two decimals, as ITFlow keeps them: counted to the
 *    minute, a ticket is rounded to the nearest 0.01 h (36 seconds).
 *
 * The tables are ours - fork_hours_blocks, fork_hours_tickets, fork_contract_hours -
 * made when first needed (forkHoursEnsure()), outside ITFlow's numbered
 * database updates. forkHoursStepHours() takes plain values and no database.
 */

/** The ways to count time - minutes in a slice, 0 for to the minute. */
function forkHoursSteps() {
    return [0 => 'To the minute, no rounding', 5 => 'Slices of 5 minutes', 15 => 'Slices of 15 minutes'];
}

/** A step from input - one of forkHoursSteps(), else 0. */
function forkHoursStepClean($step) {
    $step = intval($step);
    return isset(forkHoursSteps()[$step]) ? $step : 0;
}

/**
 * The hours for some seconds of work: 0 steps to the minute, else slices of that
 * many minutes, to the nearest slice (half a slice rounds up). $step null takes the general
 * billing time rules ($rules, billingTimeRules()).
 */
function forkHoursStepHours($seconds, $step, ?array $rules = null) {
    $seconds = max(0, intval($seconds));
    if ($step === null) {
        return billingTimeHours($seconds, $rules);
    }
    if ($seconds === 0) {
        return 0.0;
    }
    $step = forkHoursStepClean($step);
    if ($step === 0) {
        return round(round($seconds / 60) * 60 / 3600, 2);
    }
    return round((int) round($seconds / ($step * 60)) * $step / 60, 2);
}

/** The tables, made if they are not there yet. */
function forkHoursEnsure() {
    global $mysqli;
    static $ready = false;
    if ($ready) {
        return;
    }
    $ready = true;
    $tail = ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `fork_hours_blocks` (
        `fork_block_id` int(11) NOT NULL,
        `fork_block_state` varchar(10) NOT NULL DEFAULT 'active',
        `fork_block_step` tinyint(3) unsigned NOT NULL DEFAULT 0,
        `fork_block_activated_at` datetime DEFAULT NULL,
        PRIMARY KEY (`fork_block_id`)
    $tail");
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `fork_hours_tickets` (
        `fork_ticket_id` int(11) NOT NULL,
        `fork_ticket_step` tinyint(3) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY (`fork_ticket_id`)
    $tail");
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `fork_hours_alerts` (
        `fork_alert_block_id` int(11) NOT NULL,
        `fork_alert_level` tinyint(3) unsigned NOT NULL,
        `fork_alert_sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`fork_alert_block_id`, `fork_alert_level`)
    $tail");
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `fork_contract_hours` (
        `fork_contract_id` int(11) NOT NULL,
        `fork_contract_step` tinyint(3) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY (`fork_contract_id`)
    $tail");
}

/** How a contract's time is counted - a step, 0 (to the minute) until set. */
function forkContractStep($contract_id) {
    global $mysqli;
    forkHoursEnsure();
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT fork_contract_step FROM fork_contract_hours WHERE fork_contract_id = " . intval($contract_id)));
    return $row ? forkHoursStepClean($row[0]) : 0;
}

/** Set how a contract's time is counted. Whether it changed. */
function forkContractStepSave($contract_id, $step) {
    global $mysqli;
    forkHoursEnsure();
    $step = forkHoursStepClean($step);
    $was = forkContractStep($contract_id);
    mysqli_query($mysqli, "INSERT INTO fork_contract_hours (fork_contract_id, fork_contract_step) VALUES (" . intval($contract_id) . ", $step)
        ON DUPLICATE KEY UPDATE fork_contract_step = VALUES(fork_contract_step)");
    return $was !== $step;
}

/** A block just made - waiting for its invoice ($pending) or good at once - with the contract's way of counting time as it is now. */
function forkHoursBlockMade($block_id, $contract_id, $pending) {
    global $mysqli;
    forkHoursEnsure();
    mysqli_query($mysqli, "INSERT IGNORE INTO fork_hours_blocks (fork_block_id, fork_block_state, fork_block_step, fork_block_activated_at)
        VALUES (" . intval($block_id) . ", '" . ($pending ? 'pending' : 'active') . "', " . forkContractStep($contract_id) . ", " . ($pending ? 'NULL' : 'NOW()') . ")");
}

/**
 * An invoice was paid in full: the blocks bought on it are good to draw from,
 * from today if they were to start before. [contract_block_id => hours] of those
 * activated.
 */
function forkHoursActivate($invoice_id) {
    global $mysqli;
    forkHoursEnsure();
    $made = [];
    $sql = mysqli_query($mysqli, "SELECT contract_block_id, contract_block_hours, contract_block_contract_id FROM contract_blocks
        JOIN fork_hours_blocks ON fork_block_id = contract_block_id
        WHERE contract_block_invoice_id = " . intval($invoice_id) . " AND fork_block_state = 'pending'");
    while ($row = mysqli_fetch_assoc($sql)) {
        $id = intval($row['contract_block_id']);
        mysqli_query($mysqli, "UPDATE fork_hours_blocks SET fork_block_state = 'active', fork_block_activated_at = NOW() WHERE fork_block_id = $id AND fork_block_state = 'pending'");
        if (mysqli_affected_rows($mysqli) !== 1) {
            continue;
        }
        mysqli_query($mysqli, "UPDATE contract_blocks SET contract_block_bought = CURDATE() WHERE contract_block_id = $id AND contract_block_bought < CURDATE()");
        $made[$id] = round(floatval($row['contract_block_hours']), 2);
        contractHoursAlertReset(intval($row['contract_block_contract_id']));
    }
    return $made;
}

/** How tickets were counted - ticket id => step, for the ones a block counted. */
function forkHoursTicketSteps(array $ticket_ids = []) {
    global $mysqli;
    forkHoursEnsure();
    $ids = array_filter(array_map('intval', $ticket_ids));
    $steps = [];
    $sql = mysqli_query($mysqli, "SELECT fork_ticket_id, fork_ticket_step FROM fork_hours_tickets" . ($ids ? ' WHERE fork_ticket_id IN (' . implode(',', $ids) . ')' : ''));
    while ($row = mysqli_fetch_row($sql)) {
        $steps[intval($row[0])] = forkHoursStepClean($row[1]);
    }
    return $steps;
}

/** A ticket is counted by a step from now on - until it is put back. */
function forkHoursTicketStepSet($ticket_id, $step) {
    global $mysqli;
    forkHoursEnsure();
    mysqli_query($mysqli, "INSERT IGNORE INTO fork_hours_tickets (fork_ticket_id, fork_ticket_step) VALUES (" . intval($ticket_id) . ", " . forkHoursStepClean($step) . ")");
}

/** A ticket goes back to the general billing time rules. */
function forkHoursTicketStepClear($ticket_id) {
    global $mysqli;
    forkHoursEnsure();
    mysqli_query($mysqli, "DELETE FROM fork_hours_tickets WHERE fork_ticket_id = " . intval($ticket_id));
}
