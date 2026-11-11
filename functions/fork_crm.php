<?php

/*
 * CRM, the fork's sales follow-up: deals (an opportunity with a prospect or a client) that move
 * through the stages of a pipeline, the activities and tasks that go with each deal, and the
 * figures that come out of them (pipeline value, forecast, win rate).
 *
 *  - A deal belongs to a client or lead of ITFlow (deal_client_id), or, before there is one, to a
 *    prospect named in free text (deal_prospect). It may be linked to several ITFlow quotes of that client (fork_crm_deal_quotes).
 *  - A stage is open, won or lost. Moving a deal to a won or lost stage closes it (and sets its
 *    probability to 100 or 0); moving it back to an open stage reopens it.
 *  - Activities are notes, calls, e-mails, meetings and tasks; one with a due date is a task for
 *    its agent until it is marked done.
 *
 * The tables are ours - fork_crm_stages, fork_crm_deals, fork_crm_activities - made when first
 * needed (forkCrmEnsure()), outside ITFlow's numbered database updates and db.sql. The
 * functions that clean input, count and summarise take plain values and no database.
 */

/*
 * Who may use the CRM: the Administrator and Accountant roles (an admin role, or a role named
 * Accountant), and then only as far as the Sales module lets them - level 1 reads, 2 changes,
 * 3 archives deals and sets the stages. Any other role is turned away.
 */
function forkCrmRoleAllowed() {
    global $session_is_admin;
    static $allowed = null;
    if (!empty($session_is_admin)) {
        return true;
    }
    if ($allowed === null) {
        $allowed = strcasecmp(trim(permissionRoleName()), 'Accountant') === 0;
    }
    return $allowed;
}

/** The agent's level in the CRM: 0 for a role that may not use it, else their Sales module level. */
function forkCrmLevel() {
    return forkCrmRoleAllowed() ? lookupUserPermission('module_sales') : 0;
}

/** Turn the agent away unless they have at least $level in the CRM. */
function forkCrmEnforce($level = 1) {
    if (!forkCrmRoleAllowed()) {
        permissionRefuse("The CRM is for the Administrator and Accountant roles.");
    }
    enforceUserPermission('module_sales', $level);
}

/** The three kinds of stage. */
function forkCrmKinds() {
    return ['open' => 'Open', 'won' => 'Won', 'lost' => 'Lost'];
}

/** The stages a pipeline starts with: [name, kind, probability, follow-up days]. */
function forkCrmDefaultStages() {
    return [
        ['Prospect', 'open', 10, 0],
        ['Contact established', 'open', 25, 0],
        ['Quote sent', 'open', 50, 14],
        ['Negotiation', 'open', 75, 0],
        ['Won', 'won', 100, 0],
        ['Lost', 'lost', 0, 0],
    ];
}

/** Days to follow up after a deal enters a stage, from input: 0 (none) to 365. */
function forkCrmFollowUpDaysClean($days) {
    return max(0, min(365, intval($days)));
}

/** The date a follow-up is due: $days calendar days after $today (Y-m-d). */
function forkCrmFollowUpDate($today, $days) {
    return date('Y-m-d', strtotime($today . ' +' . forkCrmFollowUpDaysClean($days) . ' days'));
}

/** The kinds of activity. */
function forkCrmActivityTypes() {
    return ['note' => 'Note', 'call' => 'Call', 'email' => 'E-mail', 'meeting' => 'Meeting', 'task' => 'Task'];
}

/** Where a deal came from. */
function forkCrmSources() {
    return ['' => '-', 'referral' => 'Referral', 'website' => 'Website', 'existing' => 'Existing client', 'call' => 'Cold call', 'event' => 'Event', 'other' => 'Other'];
}

/** @return string a kind from input - one of forkCrmKinds(), else 'open'. */
function forkCrmKindClean($kind) {
    return isset(forkCrmKinds()[(string) $kind]) ? (string) $kind : 'open';
}

/** @return string an activity type from input, else 'note'. */
function forkCrmActivityTypeClean($type) {
    return isset(forkCrmActivityTypes()[(string) $type]) ? (string) $type : 'note';
}

/** @return string a source from input, else ''. */
function forkCrmSourceClean($source) {
    return isset(forkCrmSources()[(string) $source]) ? (string) $source : '';
}

/**
 * An amount from what an agent types: 1500, 1'500.50, 1 500,5, 1.500,50. A comma or a point
 * followed by one or two digits at the end is the decimal mark; anything else is grouping.
 * Never negative; two decimals; 0 for what is not a number.
 */
function forkCrmAmountClean($value) {
    $text = trim((string) $value);
    $text = preg_replace("/[\\s'’\\x{00A0}]/u", '', $text);
    if ($text === '' || !preg_match('/^[0-9.,]+$/', $text)) {
        return 0.0;
    }
    if (preg_match('/^(.*)[.,]([0-9]{1,2})$/', $text, $m)) {
        $whole = preg_replace('/[.,]/', '', $m[1]);
        $text = ($whole === '' ? '0' : $whole) . '.' . $m[2];
    } else {
        $text = preg_replace('/[.,]/', '', $text);
    }
    return round(max(0.0, min(999999999.99, (float) $text)), 2);
}

/** A probability in percent, 0 to 100. */
function forkCrmProbabilityClean($value) {
    return max(0, min(100, intval($value)));
}

/** A date Y-m-d from input, or null when it is not a real date. */
function forkCrmDateClean($value) {
    $text = trim((string) $value);
    if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $text, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return null;
    }
    return $text;
}

/** Text from input: tags out, trimmed, cut to $max characters. */
function forkCrmTextClean($value, $max = 255) {
    $text = trim(strip_tags((string) $value));
    return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
}

/** Amount x probability - what a deal is worth in the forecast. */
function forkCrmWeighted($amount, $probability) {
    return round(floatval($amount) * forkCrmProbabilityClean($probability) / 100, 2);
}

/**
 * Where a due date stands against $today: 'none' (no date), 'overdue', 'today', 'soon' (within
 * 7 days) or 'later'.
 */
function forkCrmDueState($date, $today) {
    $date = forkCrmDateClean($date);
    if ($date === null) {
        return 'none';
    }
    if ($date < $today) {
        return 'overdue';
    }
    if ($date === $today) {
        return 'today';
    }
    return $date <= date('Y-m-d', strtotime($today . ' +7 days')) ? 'soon' : 'later';
}

/**
 * The figures of a list of deals. Each deal needs deal_amount, deal_probability and
 * stage_kind. The win rate is won / (won + lost), in percent, null when none is closed.
 */
function forkCrmSummarize(array $deals) {
    $s = ['open_count' => 0, 'open_amount' => 0.0, 'weighted' => 0.0, 'won_count' => 0, 'won_amount' => 0.0, 'lost_count' => 0, 'lost_amount' => 0.0, 'win_rate' => null, 'average_won' => null];
    foreach ($deals as $deal) {
        $amount = floatval($deal['deal_amount']);
        $kind = (string) $deal['stage_kind'];
        if ($kind === 'won') {
            $s['won_count']++;
            $s['won_amount'] += $amount;
        } elseif ($kind === 'lost') {
            $s['lost_count']++;
            $s['lost_amount'] += $amount;
        } else {
            $s['open_count']++;
            $s['open_amount'] += $amount;
            $s['weighted'] += forkCrmWeighted($amount, $deal['deal_probability']);
        }
    }
    foreach (['open_amount', 'weighted', 'won_amount', 'lost_amount'] as $key) {
        $s[$key] = round($s[$key], 2);
    }
    $closed = $s['won_count'] + $s['lost_count'];
    if ($closed > 0) {
        $s['win_rate'] = round($s['won_count'] * 100 / $closed, 1);
    }
    if ($s['won_count'] > 0) {
        $s['average_won'] = round($s['won_amount'] / $s['won_count'], 2);
    }
    return $s;
}

/**
 * The open deals by month of expected close: ['2026-12' => ['count' => 2, 'amount' => x,
 * 'weighted' => y], ...] in date order. A deal with no date is under 'none', last.
 */
function forkCrmForecast(array $deals) {
    $months = [];
    foreach ($deals as $deal) {
        if ((string) $deal['stage_kind'] !== 'open') {
            continue;
        }
        $date = forkCrmDateClean($deal['deal_expected_close'] ?? '');
        $key = $date === null ? 'none' : substr($date, 0, 7);
        if (!isset($months[$key])) {
            $months[$key] = ['count' => 0, 'amount' => 0.0, 'weighted' => 0.0];
        }
        $months[$key]['count']++;
        $months[$key]['amount'] = round($months[$key]['amount'] + floatval($deal['deal_amount']), 2);
        $months[$key]['weighted'] = round($months[$key]['weighted'] + forkCrmWeighted($deal['deal_amount'], $deal['deal_probability']), 2);
    }
    uksort($months, function ($a, $b) {
        if ($a === 'none') {
            return $b === 'none' ? 0 : 1;
        }
        return $b === 'none' ? -1 : strcmp($a, $b);
    });
    return $months;
}

/** The figures of the deals grouped by a column (deal_source, owner...): [key => summary]. */
function forkCrmGroupSummary(array $deals, $column) {
    $groups = [];
    foreach ($deals as $deal) {
        $groups[(string) ($deal[$column] ?? '')][] = $deal;
    }
    $out = [];
    foreach ($groups as $key => $list) {
        $out[$key] = forkCrmSummarize($list);
    }
    return $out;
}

/** The tables, made if they are not there yet, and the default stages put in an empty pipeline. */
function forkCrmEnsure() {
    global $mysqli;
    static $ready = false;
    if ($ready) {
        return;
    }
    $ready = true;
    $tail = ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `fork_crm_stages` (
        `stage_id` int(11) NOT NULL AUTO_INCREMENT,
        `stage_name` varchar(100) NOT NULL,
        `stage_kind` varchar(10) NOT NULL DEFAULT 'open',
        `stage_probability` tinyint(3) unsigned NOT NULL DEFAULT 0,
        `stage_followup_days` smallint(5) unsigned NOT NULL DEFAULT 0,
        `stage_order` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`stage_id`)
    $tail");
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `fork_crm_deals` (
        `deal_id` int(11) NOT NULL AUTO_INCREMENT,
        `deal_title` varchar(200) NOT NULL,
        `deal_client_id` int(11) NOT NULL DEFAULT 0,
        `deal_prospect` varchar(200) DEFAULT NULL,
        `deal_contact` varchar(200) DEFAULT NULL,
        `deal_email` varchar(200) DEFAULT NULL,
        `deal_phone` varchar(100) DEFAULT NULL,
        `deal_stage_id` int(11) NOT NULL,
        `deal_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
        `deal_probability` tinyint(3) unsigned NOT NULL DEFAULT 0,
        `deal_expected_close` date DEFAULT NULL,
        `deal_source` varchar(20) NOT NULL DEFAULT '',
        `deal_owner_id` int(11) NOT NULL DEFAULT 0,
        `deal_note` text DEFAULT NULL,
        `deal_lost_reason` varchar(255) DEFAULT NULL,
        `deal_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `deal_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        `deal_closed_at` datetime DEFAULT NULL,
        `deal_archived_at` datetime DEFAULT NULL,
        `deal_created_by` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`deal_id`),
        KEY `deal_stage_id` (`deal_stage_id`, `deal_archived_at`),
        KEY `deal_client_id` (`deal_client_id`)
    $tail");
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `fork_crm_deal_quotes` (
        `dq_deal_id` int(11) NOT NULL,
        `dq_quote_id` int(11) NOT NULL,
        `dq_added_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`dq_deal_id`, `dq_quote_id`),
        KEY `dq_quote_id` (`dq_quote_id`)
    $tail");
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `fork_crm_activities` (
        `activity_id` int(11) NOT NULL AUTO_INCREMENT,
        `activity_deal_id` int(11) NOT NULL,
        `activity_type` varchar(10) NOT NULL DEFAULT 'note',
        `activity_text` text NOT NULL,
        `activity_due` date DEFAULT NULL,
        `activity_done_at` datetime DEFAULT NULL,
        `activity_user_id` int(11) NOT NULL DEFAULT 0,
        `activity_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `activity_created_by` int(11) NOT NULL DEFAULT 0,
        `activity_auto` tinyint(1) NOT NULL DEFAULT 0,
        `activity_notified_at` datetime DEFAULT NULL,
        PRIMARY KEY (`activity_id`),
        KEY `activity_deal_id` (`activity_deal_id`),
        KEY `activity_open` (`activity_user_id`, `activity_done_at`, `activity_due`)
    $tail");
    // Tables made before the follow-ups: add the columns, and give the stage that is called Quote sent its 14 days
    if (mysqli_num_rows(mysqli_query($mysqli, "SHOW COLUMNS FROM fork_crm_stages LIKE 'stage_followup_days'")) === 0) {
        mysqli_query($mysqli, "ALTER TABLE fork_crm_stages ADD `stage_followup_days` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `stage_probability`");
        mysqli_query($mysqli, "UPDATE fork_crm_stages SET stage_followup_days = 14 WHERE stage_name = 'Quote sent' AND stage_kind = 'open'");
    }
    if (mysqli_num_rows(mysqli_query($mysqli, "SHOW COLUMNS FROM fork_crm_activities LIKE 'activity_auto'")) === 0) {
        mysqli_query($mysqli, "ALTER TABLE fork_crm_activities ADD `activity_auto` tinyint(1) NOT NULL DEFAULT 0, ADD `activity_notified_at` datetime DEFAULT NULL");
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM fork_crm_stages"));
    if ($row && intval($row[0]) === 0) {
        $order = 1;
        foreach (forkCrmDefaultStages() as [$name, $kind, $probability, $followup]) {
            $name = escapeSql($name);
            mysqli_query($mysqli, "INSERT INTO fork_crm_stages SET stage_name = '$name', stage_kind = '$kind', stage_probability = $probability, stage_followup_days = $followup, stage_order = $order");
            $order++;
        }
    }
}

/* ---------- stages ---------- */

/** All the stages in order. */
function forkCrmStages() {
    global $mysqli;
    forkCrmEnsure();
    $out = [];
    $result = mysqli_query($mysqli, "SELECT * FROM fork_crm_stages ORDER BY stage_order, stage_id");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $out[intval($row['stage_id'])] = $row;
    }
    return $out;
}

/** One stage, or null. */
function forkCrmStage($stage_id) {
    return forkCrmStages()[intval($stage_id)] ?? null;
}

/** The first stage of a kind, or null. */
function forkCrmFirstStage($kind) {
    foreach (forkCrmStages() as $stage) {
        if ($stage['stage_kind'] === $kind) {
            return $stage;
        }
    }
    return null;
}

/** How many deals (archived ones too) sit in each stage: [stage_id => n]. */
function forkCrmStageCounts() {
    global $mysqli;
    forkCrmEnsure();
    $out = [];
    $result = mysqli_query($mysqli, "SELECT deal_stage_id, COUNT(*) FROM fork_crm_deals GROUP BY deal_stage_id");
    while ($result && ($row = mysqli_fetch_row($result))) {
        $out[intval($row[0])] = intval($row[1]);
    }
    return $out;
}

/** Add a stage at the end. Returns its id. */
function forkCrmStageAdd($name, $kind, $probability, $followup_days = 0) {
    global $mysqli;
    forkCrmEnsure();
    $name = escapeSql(forkCrmTextClean($name, 100));
    if ($name === '') {
        return 0;
    }
    $kind = forkCrmKindClean($kind);
    $probability = $kind === 'won' ? 100 : ($kind === 'lost' ? 0 : forkCrmProbabilityClean($probability));
    $followup = $kind === 'open' ? forkCrmFollowUpDaysClean($followup_days) : 0;
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(MAX(stage_order), 0) + 1 FROM fork_crm_stages"));
    $order = intval($row[0]);
    mysqli_query($mysqli, "INSERT INTO fork_crm_stages SET stage_name = '$name', stage_kind = '$kind', stage_probability = $probability, stage_followup_days = $followup, stage_order = $order");
    return intval(mysqli_insert_id($mysqli));
}

/**
 * Change a stage. Its kind may change only when every kind is still there afterwards - there must
 * always be an open, a won and a lost stage. Returns an error text, or '' when done.
 */
function forkCrmStageSave($stage_id, $name, $kind, $probability, $followup_days = 0) {
    global $mysqli;
    $stage = forkCrmStage($stage_id);
    if (!$stage) {
        return 'That stage is gone.';
    }
    $clean_name = forkCrmTextClean($name, 100);
    if ($clean_name === '') {
        return 'A stage needs a name.';
    }
    $kind = forkCrmKindClean($kind);
    if ($kind !== $stage['stage_kind']) {
        $left = 0;
        foreach (forkCrmStages() as $other) {
            if (intval($other['stage_id']) !== intval($stage_id) && $other['stage_kind'] === $stage['stage_kind']) {
                $left++;
            }
        }
        if ($left === 0) {
            return 'There must always be at least one ' . strtolower(forkCrmKinds()[$stage['stage_kind']]) . ' stage.';
        }
    }
    $probability = $kind === 'won' ? 100 : ($kind === 'lost' ? 0 : forkCrmProbabilityClean($probability));
    $name_sql = escapeSql($clean_name);
    $id = intval($stage_id);
    $followup = $kind === 'open' ? forkCrmFollowUpDaysClean($followup_days) : 0;
    mysqli_query($mysqli, "UPDATE fork_crm_stages SET stage_name = '$name_sql', stage_kind = '$kind', stage_probability = $probability, stage_followup_days = $followup WHERE stage_id = $id");
    return '';
}

/** Move a stage one place up (-1) or down (1). */
function forkCrmStageShift($stage_id, $direction) {
    global $mysqli;
    $ids = array_keys(forkCrmStages());
    $at = array_search(intval($stage_id), $ids, true);
    $to = $at === false ? false : $at + ($direction < 0 ? -1 : 1);
    if ($at === false || $to < 0 || $to >= count($ids)) {
        return;
    }
    [$ids[$at], $ids[$to]] = [$ids[$to], $ids[$at]];
    foreach ($ids as $i => $id) {
        mysqli_query($mysqli, "UPDATE fork_crm_stages SET stage_order = " . ($i + 1) . " WHERE stage_id = " . intval($id));
    }
}

/** Delete a stage with no deal in it, keeping at least one of each kind. Returns an error text, or ''. */
function forkCrmStageDelete($stage_id) {
    global $mysqli;
    $stage = forkCrmStage($stage_id);
    if (!$stage) {
        return 'That stage is gone.';
    }
    if ((forkCrmStageCounts()[intval($stage_id)] ?? 0) > 0) {
        return 'Move its deals to another stage first.';
    }
    $same = 0;
    foreach (forkCrmStages() as $other) {
        if ($other['stage_kind'] === $stage['stage_kind']) {
            $same++;
        }
    }
    if ($same < 2) {
        return 'There must always be at least one ' . strtolower(forkCrmKinds()[$stage['stage_kind']]) . ' stage.';
    }
    mysqli_query($mysqli, "DELETE FROM fork_crm_stages WHERE stage_id = " . intval($stage_id));
    return '';
}

/* ---------- deals ---------- */

/** The SELECT of a deal with its stage, client, quote and owner. */
function forkCrmDealSelect() {
    return "SELECT d.*, s.stage_name, s.stage_kind, c.client_name, c.client_lead, u.user_name AS owner_name
        FROM fork_crm_deals d
        JOIN fork_crm_stages s ON s.stage_id = d.deal_stage_id
        LEFT JOIN clients c ON c.client_id = d.deal_client_id
        LEFT JOIN users u ON u.user_id = d.deal_owner_id AND d.deal_owner_id > 0";
}

/**
 * The deals the signed-in agent may see, not archived. $filter: owner (user id), kind (open, won,
 * lost), client_id, search (text), year (closed in that year, for won and lost), stage_id.
 */
function forkCrmDeals(array $filter = []) {
    global $mysqli;
    forkCrmEnsure();
    $where = "WHERE d.deal_archived_at IS NULL" . clientScopeSql('d.deal_client_id');
    if (!empty($filter['owner'])) {
        $where .= " AND d.deal_owner_id = " . intval($filter['owner']);
    }
    if (!empty($filter['kind'])) {
        $where .= " AND s.stage_kind = '" . forkCrmKindClean($filter['kind']) . "'";
    }
    if (!empty($filter['stage_id'])) {
        $where .= " AND d.deal_stage_id = " . intval($filter['stage_id']);
    }
    if (!empty($filter['client_id'])) {
        $where .= " AND d.deal_client_id = " . intval($filter['client_id']);
    }
    if (!empty($filter['year'])) {
        $where .= " AND (s.stage_kind = 'open' OR YEAR(d.deal_closed_at) = " . intval($filter['year']) . ")";
    }
    if (!empty($filter['search'])) {
        $like = escapeSql(str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $filter['search']));
        $where .= " AND (d.deal_title LIKE '%$like%' OR d.deal_prospect LIKE '%$like%' OR c.client_name LIKE '%$like%' OR d.deal_contact LIKE '%$like%')";
    }
    $out = [];
    $result = mysqli_query($mysqli, forkCrmDealSelect() . " $where ORDER BY s.stage_order, d.deal_expected_close IS NULL, d.deal_expected_close, d.deal_id");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $out[] = $row;
    }
    return $out;
}

/** One deal, not archived, or null. The caller checks client access. */
function forkCrmDeal($deal_id) {
    global $mysqli;
    forkCrmEnsure();
    $result = mysqli_query($mysqli, forkCrmDealSelect() . " WHERE d.deal_id = " . intval($deal_id) . " AND d.deal_archived_at IS NULL");
    return $result ? (mysqli_fetch_assoc($result) ?: null) : null;
}

/** What a deal shows as its name: the client, else the prospect. */
function forkCrmDealWho(array $deal) {
    if (intval($deal['deal_client_id']) > 0 && (string) ($deal['client_name'] ?? '') !== '') {
        return (string) $deal['client_name'];
    }
    return (string) ($deal['deal_prospect'] ?? '');
}

/**
 * Make a deal ($deal_id 0) or change one. $data: title, client_id, prospect, contact, email, phone,
 * amount, probability, expected_close, source, owner_id, note. The stage is not changed
 * here - see forkCrmDealMove(); the quotes are linked with forkCrmDealQuoteAdd(). Returns the deal id, 0 on failure.
 */
function forkCrmDealSave($deal_id, array $data, $stage_id = 0, $user_id = 0) {
    global $mysqli;
    forkCrmEnsure();
    $title = forkCrmTextClean($data['title'] ?? '', 200);
    if ($title === '') {
        return 0;
    }
    $client_id = intval($data['client_id'] ?? 0);
    if ($client_id > 0 && !mysqli_fetch_row(mysqli_query($mysqli, "SELECT client_id FROM clients WHERE client_id = $client_id AND client_archived_at IS NULL"))) {
        $client_id = 0;
    }
    $owner_id = intval($data['owner_id'] ?? 0);
    if ($owner_id > 0 && !mysqli_fetch_row(mysqli_query($mysqli, "SELECT user_id FROM users WHERE user_id = $owner_id AND user_archived_at IS NULL"))) {
        $owner_id = 0;
    }
    $close = forkCrmDateClean($data['expected_close'] ?? '');
    $close_sql = $close === null ? 'NULL' : "'$close'";
    $amount = forkCrmAmountClean($data['amount'] ?? 0);
    $probability = forkCrmProbabilityClean($data['probability'] ?? 0);
    $deal_id = intval($deal_id);
    $stage = null;
    if ($deal_id === 0) {
        $stage = forkCrmStage($stage_id) ?: forkCrmFirstStage('open');
        if (!$stage) {
            return 0;
        }
        if ($probability === 0 && $stage['stage_kind'] === 'open') {
            $probability = intval($stage['stage_probability']);
        }
    }
    $source = forkCrmSourceClean($data['source'] ?? '');
    $title_sql = escapeSql($title);
    $prospect = escapeSql(forkCrmTextClean($data['prospect'] ?? '', 200));
    $contact = escapeSql(forkCrmTextClean($data['contact'] ?? '', 200));
    $email = escapeSql(forkCrmTextClean($data['email'] ?? '', 200));
    $phone = escapeSql(forkCrmTextClean($data['phone'] ?? '', 100));
    $note = escapeSql(forkCrmTextClean($data['note'] ?? '', 5000));
    $set = "deal_title = '$title_sql', deal_client_id = $client_id, deal_prospect = '$prospect', deal_contact = '$contact',
        deal_email = '$email', deal_phone = '$phone', deal_amount = $amount, deal_probability = $probability,
        deal_expected_close = $close_sql, deal_source = '$source', deal_owner_id = $owner_id, deal_note = '$note'";
    if ($deal_id > 0) {
        mysqli_query($mysqli, "UPDATE fork_crm_deals SET $set WHERE deal_id = $deal_id AND deal_archived_at IS NULL");
        // Quotes are the client's: when the deal changes client, the quotes of the old one come off
        mysqli_query($mysqli, "DELETE FROM fork_crm_deal_quotes WHERE dq_deal_id = $deal_id AND dq_quote_id NOT IN
            (SELECT quote_id FROM quotes WHERE quote_client_id = $client_id)");
        return $deal_id;
    }
    $stage_id = intval($stage['stage_id']);
    $closed = $stage['stage_kind'] === 'open' ? 'NULL' : 'NOW()';
    mysqli_query($mysqli, "INSERT INTO fork_crm_deals SET $set, deal_stage_id = $stage_id, deal_closed_at = $closed, deal_created_by = " . intval($user_id));
    return intval(mysqli_insert_id($mysqli));
}

/**
 * Move a deal to a stage. Won: probability 100, closed now. Lost: probability 0, closed now, the
 * reason kept. Open: reopened, probability from the stage unless the deal already has one it
 * was given by hand and the stage it left was open too. Returns an error text, or ''.
 */
function forkCrmDealMove(array $deal, $stage_id, $lost_reason = '') {
    global $mysqli;
    $stage = forkCrmStage($stage_id);
    if (!$stage) {
        return 'That stage is gone.';
    }
    $id = intval($deal['deal_id']);
    $stage_id = intval($stage['stage_id']);
    if ($stage_id === intval($deal['deal_stage_id'])) {
        return '';
    }
    $was_open = $deal['stage_kind'] === 'open';
    if ($stage['stage_kind'] === 'won') {
        mysqli_query($mysqli, "UPDATE fork_crm_deals SET deal_stage_id = $stage_id, deal_probability = 100, deal_closed_at = NOW(), deal_lost_reason = NULL WHERE deal_id = $id");
    } elseif ($stage['stage_kind'] === 'lost') {
        $reason = escapeSql(forkCrmTextClean($lost_reason, 255));
        mysqli_query($mysqli, "UPDATE fork_crm_deals SET deal_stage_id = $stage_id, deal_probability = 0, deal_closed_at = NOW(), deal_lost_reason = " . ($reason === '' ? 'NULL' : "'$reason'") . " WHERE deal_id = $id");
    } else {
        $probability = intval($stage['stage_probability']);
        mysqli_query($mysqli, "UPDATE fork_crm_deals SET deal_stage_id = $stage_id, deal_probability = $probability, deal_closed_at = NULL, deal_lost_reason = NULL WHERE deal_id = $id");
    }
    return '';
}

/** Put a deal away (archive) - it leaves the lists but its history stays. */
function forkCrmDealArchive($deal_id) {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE fork_crm_deals SET deal_archived_at = NOW() WHERE deal_id = " . intval($deal_id));
}

/* ---------- follow-ups ---------- */

/**
 * A deal that has just entered an open stage with a follow-up delay gets a task to follow the client
 * up that many days later, for its owner (or $user_id, who moved it, when it has none). Not made when
 * the deal already has an open follow-up task. Returns the due date, or null when none was made.
 */
function forkCrmFollowUpCreate(array $deal, array $stage, $user_id) {
    global $mysqli;
    $days = forkCrmFollowUpDaysClean($stage['stage_followup_days'] ?? 0);
    if ($days === 0 || $stage['stage_kind'] !== 'open') {
        return null;
    }
    $deal_id = intval($deal['deal_id']);
    $open = mysqli_fetch_row(mysqli_query($mysqli, "SELECT activity_id FROM fork_crm_activities WHERE activity_deal_id = $deal_id AND activity_auto = 1 AND activity_done_at IS NULL LIMIT 1"));
    if ($open) {
        return null;
    }
    $due = forkCrmFollowUpDate(date('Y-m-d'), $days);
    $assignee = intval($deal['deal_owner_id']) ?: intval($user_id);
    $who = forkCrmDealWho($deal);
    $text = escapeSql("Follow up with " . ($who !== '' ? $who : 'the client') . " - stage " . $stage['stage_name'] . " for $days days: ask whether they have read it and what they decide.");
    mysqli_query($mysqli, "INSERT INTO fork_crm_activities SET activity_deal_id = $deal_id, activity_type = 'task', activity_text = '$text', activity_due = '$due',
        activity_user_id = $assignee, activity_created_by = " . intval($user_id) . ", activity_auto = 1");
    return $due;
}

/**
 * Each night: the follow-up tasks that fall due today (or were late) tell the agent who has them - in
 * ITFlow and by email - once. A task of a deal that was won, lost or archived in the meantime is left
 * alone. With no agent to tell (gone), the admins are told. Returns how many were told.
 */
function forkCrmNightly() {
    global $mysqli, $config_base_url;
    $exists = mysqli_query($mysqli, "SHOW TABLES LIKE 'fork_crm_activities'");
    if (!$exists || mysqli_num_rows($exists) === 0) {
        return 0;
    }
    forkCrmEnsure();
    $told = 0;
    $result = mysqli_query($mysqli, "SELECT a.activity_id, a.activity_user_id, a.activity_due, a.activity_text, d.deal_id, d.deal_title, d.deal_client_id, d.deal_prospect, c.client_name
        FROM fork_crm_activities a
        JOIN fork_crm_deals d ON d.deal_id = a.activity_deal_id
        JOIN fork_crm_stages s ON s.stage_id = d.deal_stage_id
        LEFT JOIN clients c ON c.client_id = d.deal_client_id
        WHERE a.activity_auto = 1 AND a.activity_done_at IS NULL AND a.activity_notified_at IS NULL AND a.activity_due <= CURDATE()
        AND d.deal_archived_at IS NULL AND s.stage_kind = 'open'");
    $tasks = [];
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $tasks[] = $row;
    }
    foreach ($tasks as $task) {
        $users = [];
        $found = mysqli_query($mysqli, "SELECT user_id, user_name, user_email FROM users WHERE user_id = " . intval($task['activity_user_id']) . " AND user_status = 1 AND user_archived_at IS NULL");
        while ($found && ($user = mysqli_fetch_assoc($found))) {
            $users[] = $user;
        }
        if (!$users) {
            $found = mysqli_query($mysqli, "SELECT u.user_id, u.user_name, u.user_email FROM users u JOIN user_roles r ON r.role_id = u.user_role_id
                WHERE r.role_is_admin = 1 AND u.user_status = 1 AND u.user_archived_at IS NULL");
            while ($found && ($user = mysqli_fetch_assoc($found))) {
                $users[] = $user;
            }
        }
        $who = (string) ($task['client_name'] ?: $task['deal_prospect']);
        $path = '/agent/crm_deal.php?deal_id=' . intval($task['deal_id']);
        $message = escapeSql(substr("Time to follow up with " . ($who !== '' ? $who : 'the client') . " - deal " . $task['deal_title'], 0, 900));
        $recipients = [];
        foreach ($users as $user) {
            mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'CRM', notification = '$message', notification_action = '$path',
                notification_client_id = " . intval($task['deal_client_id']) . ", notification_user_id = " . intval($user['user_id']) . ", notification_entity_id = " . intval($task['deal_id']));
            $recipients[] = ['email' => $user['user_email'], 'name' => $user['user_name'], 'footnote' => 'You get this as a CRM follow-up task is yours in ITFlow.'];
        }
        if ($recipients) {
            emailQueue($recipients, "Follow up: " . $task['deal_title'] . ($who !== '' ? " - $who" : ''), [
                'kind' => 'CRM', 'tone' => 'warning',
                'title' => 'Time to follow up' . ($who !== '' ? " with $who" : ''),
                'intro' => ["The quote went out a while ago. " . $task['activity_text']],
                'summary' => array_filter(['Deal' => (string) $task['deal_title'], 'For' => $who, 'Due' => (string) $task['activity_due']]),
                'button' => ['label' => 'Open the deal', 'url' => "https://$config_base_url$path"],
            ]);
        }
        mysqli_query($mysqli, "UPDATE fork_crm_activities SET activity_notified_at = NOW() WHERE activity_id = " . intval($task['activity_id']));
        $told++;
    }
    return $told;
}

/* ---------- prospect to client ---------- */

/** A client or lead of ITFlow with this name (any case), not archived - or null. */
function forkCrmClientByName($name) {
    global $mysqli;
    $name = escapeSql(forkCrmTextClean($name, 200));
    if ($name === '') {
        return null;
    }
    $result = mysqli_query($mysqli, "SELECT client_id, client_name, client_lead FROM clients WHERE client_archived_at IS NULL AND LOWER(client_name) = LOWER('$name') ORDER BY client_lead, client_id LIMIT 1");
    return $result ? (mysqli_fetch_assoc($result) ?: null) : null;
}

/**
 * The postal address from a form: street, zip, city, country (all four required, the country one
 * ITFlow offers) and state (optional). Returns [address, errors] - errors is [field => text].
 */
function forkCrmAddressClean(array $input, array $countries) {
    $address = [
        'address' => forkCrmTextClean($input['address'] ?? '', 200),
        'zip' => forkCrmTextClean($input['zip'] ?? '', 200),
        'city' => forkCrmTextClean($input['city'] ?? '', 200),
        'state' => forkCrmTextClean($input['state'] ?? '', 200),
        'country' => forkCrmTextClean($input['country'] ?? '', 200),
    ];
    $errors = [];
    foreach (['address' => 'the street and number', 'zip' => 'the postal code', 'city' => 'the city'] as $field => $label) {
        if ($address[$field] === '') {
            $errors[$field] = "Give $label.";
        }
    }
    if ($address['country'] === '' || !in_array($address['country'], $countries, true)) {
        $errors['country'] = 'Choose the country.';
    }
    return [$address, $errors];
}

/** Does this client have a postal address (street and city) on one of its locations? */
function forkCrmClientHasAddress($client_id) {
    global $mysqli;
    $result = mysqli_query($mysqli, "SELECT location_id FROM locations WHERE location_client_id = " . intval($client_id) . " AND location_archived_at IS NULL
        AND location_address IS NOT NULL AND location_address <> '' AND location_city IS NOT NULL AND location_city <> '' LIMIT 1");
    return $result && mysqli_num_rows($result) > 0;
}

/** Put the postal address on the client's primary location (made if there is none). */
function forkCrmAddressSave($client_id, array $a) {
    global $mysqli;
    $client_id = intval($client_id);
    $set = "location_address = '" . escapeSql($a['address']) . "', location_city = '" . escapeSql($a['city']) . "', location_state = '" . escapeSql($a['state']) . "',
        location_zip = '" . escapeSql($a['zip']) . "', location_country = '" . escapeSql($a['country']) . "'";
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT location_id FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL ORDER BY location_primary DESC, location_id LIMIT 1"));
    if ($row) {
        mysqli_query($mysqli, "UPDATE locations SET $set WHERE location_id = " . intval($row[0]));
    } else {
        mysqli_query($mysqli, "INSERT INTO locations SET location_name = 'Primary', $set, location_primary = 1, location_client_id = $client_id");
    }
}

/**
 * Make a deal's prospect a lead (a "Prospect") in ITFlow's contacts, with the deal's contact person,
 * and tie the deal to it. A client or lead with the same name is not made twice: the deal is tied to
 * that one. $address (from forkCrmAddressClean(), or null) goes on the new prospect's primary location,
 * or on an existing one that has none. The caller checks the agent may add clients. Returns [client_id, 'created'|'existing',
 * error] - client_id 0 and an error text on failure.
 */
function forkCrmProspectCreate(array $deal, ?array $address = null) {
    global $mysqli, $session_company_currency;
    if (intval($deal['deal_client_id']) > 0) {
        if ($address && !forkCrmClientHasAddress($deal['deal_client_id'])) {
            forkCrmAddressSave($deal['deal_client_id'], $address);
        }
        return [intval($deal['deal_client_id']), 'existing', ''];
    }
    $name = forkCrmTextClean($deal['deal_prospect'] ?? '', 200);
    if ($name === '') {
        return [0, '', 'Type the prospect\'s name in the deal first.'];
    }
    $found = forkCrmClientByName($name);
    if ($found) {
        $client_id = intval($found['client_id']);
        $state = 'existing';
        if ($address && !forkCrmClientHasAddress($client_id)) {
            forkCrmAddressSave($client_id, $address);
        }
    } else {
        $name_sql = escapeSql($name);
        $abbreviation = escapeSql(shortenClientName($name_sql));
        mysqli_query($mysqli, "INSERT INTO clients SET client_name = '$name_sql', client_type = '', client_website = '', client_referral = '',
            client_currency_code = '" . escapeSql($session_company_currency) . "', client_reminders = 1, client_lead = 1, client_abbreviation = '$abbreviation',
            client_notes = '', client_accessed_at = NOW()" . billingClientLegacySql());
        $client_id = intval(mysqli_insert_id($mysqli));
        if ($client_id <= 0) {
            return [0, '', 'The prospect could not be made.'];
        }
        $folder = ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/uploads/clients/$client_id";
        if (($_SERVER['DOCUMENT_ROOT'] ?? '') !== '' && !file_exists($folder)) {
            @mkdir($folder);
            @file_put_contents("$folder/index.php", "");
        }
        if ($address) {
            forkCrmAddressSave($client_id, $address);
        }
        $contact = escapeSql(forkCrmTextClean($deal['deal_contact'] ?? '', 200));
        $email = escapeSql(forkCrmTextClean($deal['deal_email'] ?? '', 200));
        $phone = preg_replace('/[^0-9]/', '', (string) ($deal['deal_phone'] ?? ''));
        if ($contact !== '' || $email !== '' || $phone !== '') {
            mysqli_query($mysqli, "INSERT INTO contacts SET contact_name = '" . ($contact !== '' ? $contact : $name_sql) . "', contact_email = '$email', contact_phone = '$phone',
                contact_primary = 1, contact_important = 1, contact_client_id = $client_id");
        }
        $state = 'created';
    }
    mysqli_query($mysqli, "UPDATE fork_crm_deals SET deal_client_id = $client_id WHERE deal_id = " . intval($deal['deal_id']));
    return [$client_id, $state, ''];
}

/* ---------- quotes ---------- */

/** The ITFlow quotes linked to a deal, oldest link first. */
function forkCrmDealQuotes($deal_id) {
    global $mysqli;
    forkCrmEnsure();
    $out = [];
    $result = mysqli_query($mysqli, "SELECT q.quote_id, q.quote_prefix, q.quote_number, q.quote_scope, q.quote_status, q.quote_amount, q.quote_currency_code, q.quote_date
        FROM fork_crm_deal_quotes dq JOIN quotes q ON q.quote_id = dq.dq_quote_id
        WHERE dq.dq_deal_id = " . intval($deal_id) . " ORDER BY dq.dq_added_at, q.quote_id");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $out[] = $row;
    }
    return $out;
}

/**
 * What the quotes come to: those in $currency, leaving out the ones turned down, expired or
 * cancelled. Plain values, no database.
 */
function forkCrmQuotesTotal(array $quotes, $currency) {
    $total = 0.0;
    foreach ($quotes as $quote) {
        if ((string) $quote['quote_currency_code'] !== (string) $currency || in_array(strtolower((string) $quote['quote_status']), ['declined', 'expired', 'cancelled'], true)) {
            continue;
        }
        $total += floatval($quote['quote_amount']);
    }
    return round($total, 2);
}

/** Link a quote of the deal's client to the deal. Returns an error text, or ''. */
function forkCrmDealQuoteAdd(array $deal, $quote_id) {
    global $mysqli;
    $quote_id = intval($quote_id);
    $client_id = intval($deal['deal_client_id']);
    if ($client_id <= 0) {
        return 'Link the deal to a client or lead first - quotes belong to a client.';
    }
    if (!mysqli_fetch_row(mysqli_query($mysqli, "SELECT quote_id FROM quotes WHERE quote_id = $quote_id AND quote_client_id = $client_id AND quote_archived_at IS NULL"))) {
        return 'That quote is not one of this client\'s.';
    }
    mysqli_query($mysqli, "INSERT IGNORE INTO fork_crm_deal_quotes SET dq_deal_id = " . intval($deal['deal_id']) . ", dq_quote_id = $quote_id");
    return '';
}

/** Take a quote off a deal (the quote itself stays). */
function forkCrmDealQuoteRemove($deal_id, $quote_id) {
    global $mysqli;
    mysqli_query($mysqli, "DELETE FROM fork_crm_deal_quotes WHERE dq_deal_id = " . intval($deal_id) . " AND dq_quote_id = " . intval($quote_id));
}

/** Set a deal's amount (and nothing else). */
function forkCrmDealAmountSet($deal_id, $amount) {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE fork_crm_deals SET deal_amount = " . forkCrmAmountClean($amount) . " WHERE deal_id = " . intval($deal_id));
}

/** The quotes of a client to pick from: [quote_id => text]. */
function forkCrmClientQuotes($client_id) {
    global $mysqli;
    $out = [];
    $client_id = intval($client_id);
    if ($client_id <= 0) {
        return $out;
    }
    $result = mysqli_query($mysqli, "SELECT quote_id, quote_prefix, quote_number, quote_status, quote_amount, quote_currency_code FROM quotes
        WHERE quote_client_id = $client_id AND quote_archived_at IS NULL ORDER BY quote_id DESC");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $out[intval($row['quote_id'])] = $row['quote_prefix'] . $row['quote_number'] . ' - ' . $row['quote_status'] . ' - ' . billingMoney($row['quote_amount'], $row['quote_currency_code']);
    }
    return $out;
}

/** The clients and leads to pick from: [client_id => name]. */
function forkCrmClientChoices() {
    global $mysqli;
    $out = [];
    $result = mysqli_query($mysqli, "SELECT client_id, client_name, client_lead FROM clients WHERE client_archived_at IS NULL" . clientScopeSql('client_id') . " ORDER BY client_name");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $out[intval($row['client_id'])] = $row['client_name'] . (intval($row['client_lead']) ? ' (lead)' : '');
    }
    return $out;
}

/** The agents to pick as owner: [user_id => name]. */
function forkCrmUsers() {
    global $mysqli;
    $out = [];
    $result = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_archived_at IS NULL AND user_status = 1 ORDER BY user_name");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $out[intval($row['user_id'])] = $row['user_name'];
    }
    return $out;
}

/* ---------- activities ---------- */

/** The activities of a deal, newest first, open tasks before the rest. */
function forkCrmActivities($deal_id) {
    global $mysqli;
    forkCrmEnsure();
    $out = [];
    $result = mysqli_query($mysqli, "SELECT a.*, u.user_name FROM fork_crm_activities a LEFT JOIN users u ON u.user_id = a.activity_user_id
        WHERE a.activity_deal_id = " . intval($deal_id) . "
        ORDER BY (a.activity_due IS NOT NULL AND a.activity_done_at IS NULL) DESC, a.activity_created_at DESC, a.activity_id DESC");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $out[] = $row;
    }
    return $out;
}

/** One activity with its deal's client, or null. */
function forkCrmActivity($activity_id) {
    global $mysqli;
    forkCrmEnsure();
    $result = mysqli_query($mysqli, "SELECT a.*, d.deal_client_id FROM fork_crm_activities a JOIN fork_crm_deals d ON d.deal_id = a.activity_deal_id
        WHERE a.activity_id = " . intval($activity_id));
    return $result ? (mysqli_fetch_assoc($result) ?: null) : null;
}

/**
 * Add an activity to a deal. A date makes it a task for $assignee until it is done. A note, call,
 * e-mail or meeting without a date is only a log line, done at once. Returns the id, 0 on failure.
 */
function forkCrmActivityAdd($deal_id, $type, $text, $due, $assignee, $user_id) {
    global $mysqli;
    forkCrmEnsure();
    $text = forkCrmTextClean($text, 5000);
    if ($text === '') {
        return 0;
    }
    $type = forkCrmActivityTypeClean($type);
    $due = forkCrmDateClean($due);
    if ($type === 'task' && $due === null) {
        $due = date('Y-m-d');
    }
    $assignee = intval($assignee);
    if ($assignee > 0 && !mysqli_fetch_row(mysqli_query($mysqli, "SELECT user_id FROM users WHERE user_id = $assignee AND user_archived_at IS NULL"))) {
        $assignee = 0;
    }
    $text_sql = escapeSql($text);
    $due_sql = $due === null ? 'NULL' : "'$due'";
    $done = $due === null ? 'NOW()' : 'NULL';
    mysqli_query($mysqli, "INSERT INTO fork_crm_activities SET activity_deal_id = " . intval($deal_id) . ", activity_type = '$type', activity_text = '$text_sql',
        activity_due = $due_sql, activity_done_at = $done, activity_user_id = $assignee, activity_created_by = " . intval($user_id));
    return intval(mysqli_insert_id($mysqli));
}

/** Mark a task done, or open it again. */
function forkCrmActivityDone($activity_id, $done) {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE fork_crm_activities SET activity_done_at = " . ($done ? 'NOW()' : 'NULL') . " WHERE activity_id = " . intval($activity_id));
}

/** Delete an activity. */
function forkCrmActivityDelete($activity_id) {
    global $mysqli;
    mysqli_query($mysqli, "DELETE FROM fork_crm_activities WHERE activity_id = " . intval($activity_id));
}

/**
 * Open tasks (an activity with a due date, not done) on deals still being worked, for the clients
 * the agent may see. $user_id 0 lists everyone's, else the agent's own and the unassigned ones.
 */
function forkCrmTasks($user_id = 0) {
    global $mysqli;
    forkCrmEnsure();
    $where = "WHERE a.activity_due IS NOT NULL AND a.activity_done_at IS NULL AND d.deal_archived_at IS NULL AND s.stage_kind = 'open'" . clientScopeSql('d.deal_client_id');
    if (intval($user_id) > 0) {
        $where .= " AND (a.activity_user_id = " . intval($user_id) . " OR a.activity_user_id = 0)";
    }
    $out = [];
    $result = mysqli_query($mysqli, "SELECT a.*, d.deal_title, d.deal_client_id, d.deal_prospect, c.client_name, u.user_name
        FROM fork_crm_activities a
        JOIN fork_crm_deals d ON d.deal_id = a.activity_deal_id
        JOIN fork_crm_stages s ON s.stage_id = d.deal_stage_id
        LEFT JOIN clients c ON c.client_id = d.deal_client_id
        LEFT JOIN users u ON u.user_id = a.activity_user_id
        $where ORDER BY a.activity_due, a.activity_id");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $out[] = $row;
    }
    return $out;
}

/** How many of the agent's tasks are due today or late - for the menu. 0 when the tables are not there yet. */
function forkCrmTasksDueCount($user_id) {
    global $mysqli;
    $exists = mysqli_query($mysqli, "SHOW TABLES LIKE 'fork_crm_activities'");
    if (!$exists || mysqli_num_rows($exists) === 0) {
        return 0;
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM fork_crm_activities a
        JOIN fork_crm_deals d ON d.deal_id = a.activity_deal_id
        JOIN fork_crm_stages s ON s.stage_id = d.deal_stage_id
        WHERE a.activity_due IS NOT NULL AND a.activity_due <= CURDATE() AND a.activity_done_at IS NULL AND d.deal_archived_at IS NULL AND s.stage_kind = 'open'
        AND (a.activity_user_id = " . intval($user_id) . " OR a.activity_user_id = 0)" . clientScopeSql('d.deal_client_id')));
    return $row ? intval($row[0]) : 0;
}
