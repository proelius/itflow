<?php

/*
 * CRM, the rest of it (functions/fork_crm.php has the deals, stages and tasks):
 *
 *  - the deal's history (every move, quote and contact), and the time spent in each stage;
 *  - several of the client's ITFlow contacts on a deal, each with a role;
 *  - quotes that move the deal (a quote sent brings it to the stage marked "a quote is sent", an
 *    accepted one wins it, all declined loses it) and a quote made from the deal;
 *  - deals nobody has touched for a while, told to their owner, and a weekly digest;
 *  - an email sent from the deal, kept in its activities;
 *  - the settings (owners see only their own deals, days before a deal is "dormant", the digest);
 *  - the figures for the dashboard: funnel, sales cycle, won by month, the CSV export.
 *
 * What decides from plain values (forkCrmQuoteTarget(), forkCrmStageTimes(), forkCrmIsStale(),
 * forkCrmFunnel(), forkCrmAverageCycle(), forkCrmWonByMonth(), forkCrmCsvCell()) takes no database.
 */

/* ---------- settings ---------- */

/** What each CRM setting is until it is saved. */
function forkCrmSettingDefaults() {
    return ['own_only' => '0', 'stale_days' => '14', 'digest' => '1', 'digest_last' => '', 'won_checkin_days' => '30', 'lost_recontact_days' => '90', 'won_notify_team' => '1'];
}

/** One CRM setting, as text. */
function forkCrmSetting($key) {
    global $mysqli;
    static $values = null;
    $defaults = forkCrmSettingDefaults();
    if ($values === null) {
        $values = $defaults;
        forkCrmEnsure();
        $result = mysqli_query($mysqli, "SELECT setting_key, setting_value FROM fork_crm_settings");
        while ($result && ($row = mysqli_fetch_assoc($result))) {
            if (array_key_exists($row['setting_key'], $defaults)) {
                $values[$row['setting_key']] = (string) $row['setting_value'];
            }
        }
    }
    return $values[$key] ?? '';
}

/** Save one CRM setting. */
function forkCrmSettingSet($key, $value) {
    global $mysqli;
    if (!array_key_exists($key, forkCrmSettingDefaults())) {
        return;
    }
    forkCrmEnsure();
    mysqli_query($mysqli, "REPLACE INTO fork_crm_settings SET setting_key = '" . escapeSql($key) . "', setting_value = '" . escapeSql((string) $value) . "'");
}

/** The days a deal may go untouched before it is dormant: 1 to 365. */
function forkCrmStaleDaysClean($days) {
    return max(1, min(365, intval($days)));
}

/**
 * With "owners see only their own deals" on, an agent who is not an admin sees the deals that are
 * theirs or nobody's. " AND ..." for a query where the deals are aliased $alias; '' for everyone else,
 * and in the nightly job (nobody is signed in).
 */
function forkCrmOwnScopeSql($alias = 'd') {
    global $session_user_id, $session_is_admin;
    if (!isset($session_user_id) || !empty($session_is_admin) || forkCrmSetting('own_only') !== '1') {
        return '';
    }
    return " AND $alias.deal_owner_id IN (0, " . intval($session_user_id) . ")";
}

/* ---------- history ---------- */

/** Put a line in a deal's history. $user_id 0 is the system. */
function forkCrmHistoryAdd($deal_id, $kind, $text, $user_id = 0, $from_stage = 0, $to_stage = 0) {
    global $mysqli;
    forkCrmEnsure();
    $text = escapeSql(forkCrmTextClean($text, 500));
    mysqli_query($mysqli, "INSERT INTO fork_crm_history SET history_deal_id = " . intval($deal_id) . ", history_kind = '" . escapeSql(forkCrmTextClean($kind, 20)) . "',
        history_text = '$text', history_from_stage = " . intval($from_stage) . ", history_to_stage = " . intval($to_stage) . ", history_user_id = " . intval($user_id));
}

/** A deal's history, newest first. */
function forkCrmHistory($deal_id) {
    global $mysqli;
    forkCrmEnsure();
    $out = [];
    $result = mysqli_query($mysqli, "SELECT h.*, u.user_name FROM fork_crm_history h LEFT JOIN users u ON u.user_id = h.history_user_id AND h.history_user_id > 0
        WHERE h.history_deal_id = " . intval($deal_id) . " ORDER BY h.history_at DESC, h.history_id DESC");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $out[] = $row;
    }
    return $out;
}

/**
 * Seconds a deal spent in each stage. $events: its stage moves, oldest first, each ['from' => stage id,
 * 'to' => stage id, 'at' => timestamp]. It began in the first move's "from" (or in $current when it never
 * moved) at $created and is in $current until $end.
 */
function forkCrmStageTimes(array $events, $created, $current, $end) {
    $times = [];
    $stage = $events ? intval($events[0]['from']) : intval($current);
    $start = intval($created);
    foreach ($events as $event) {
        $at = max($start, intval($event['at']));
        $times[$stage] = ($times[$stage] ?? 0) + ($at - $start);
        $start = $at;
        $stage = intval($event['to']);
    }
    $times[$stage] = ($times[$stage] ?? 0) + max(0, intval($end) - $start);
    return $times;
}

/** Seconds as "3 d 4 h", "5 h" or "under an hour". */
function forkCrmDurationWords($seconds) {
    $seconds = max(0, intval($seconds));
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    if ($days > 0) {
        return $days . ' d' . ($hours > 0 ? " $hours h" : '');
    }
    return $hours > 0 ? "$hours h" : 'under an hour';
}

/** The time a deal spent in each stage: [stage_id => seconds], from its history. */
function forkCrmDealStageTimes(array $deal) {
    $events = [];
    foreach (array_reverse(forkCrmHistory($deal['deal_id'])) as $row) {
        if ($row['history_kind'] === 'stage') {
            $events[] = ['from' => $row['history_from_stage'], 'to' => $row['history_to_stage'], 'at' => strtotime($row['history_at'])];
        }
    }
    $end = !empty($deal['deal_closed_at']) ? strtotime($deal['deal_closed_at']) : time();
    return forkCrmStageTimes($events, strtotime($deal['deal_created_at']), $deal['deal_stage_id'], $end);
}

/* ---------- contacts ---------- */

/** The ITFlow contacts on a deal, with their role. */
function forkCrmDealContacts($deal_id) {
    global $mysqli;
    forkCrmEnsure();
    $out = [];
    $result = mysqli_query($mysqli, "SELECT c.contact_id, c.contact_name, c.contact_title, c.contact_email, c.contact_phone, c.contact_mobile, dc.dc_role
        FROM fork_crm_deal_contacts dc JOIN contacts c ON c.contact_id = dc.dc_contact_id
        WHERE dc.dc_deal_id = " . intval($deal_id) . " AND c.contact_archived_at IS NULL ORDER BY c.contact_name");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $out[] = $row;
    }
    return $out;
}

/** The contacts of a client to pick from: [contact_id => name]. */
function forkCrmClientContacts($client_id) {
    global $mysqli;
    $out = [];
    $client_id = intval($client_id);
    if ($client_id <= 0) {
        return $out;
    }
    $result = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_name");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $out[intval($row['contact_id'])] = $row['contact_name'] . ((string) $row['contact_title'] !== '' ? ' - ' . $row['contact_title'] : '');
    }
    return $out;
}

/** Put a contact of the deal's client on the deal (or change its role). Returns an error text, or ''. */
function forkCrmDealContactAdd(array $deal, $contact_id, $role) {
    global $mysqli;
    $client_id = intval($deal['deal_client_id']);
    $contact_id = intval($contact_id);
    if ($client_id <= 0) {
        return 'Link the deal to a client or lead first - contacts belong to a client.';
    }
    if (!mysqli_fetch_row(mysqli_query($mysqli, "SELECT contact_id FROM contacts WHERE contact_id = $contact_id AND contact_client_id = $client_id AND contact_archived_at IS NULL"))) {
        return 'That contact is not one of this client\'s.';
    }
    $role = isset(forkCrmContactRoles()[(string) $role]) ? (string) $role : '';
    mysqli_query($mysqli, "REPLACE INTO fork_crm_deal_contacts SET dc_deal_id = " . intval($deal['deal_id']) . ", dc_contact_id = $contact_id, dc_role = '$role'");
    return '';
}

/** Take a contact off a deal (the contact stays in ITFlow). */
function forkCrmDealContactRemove($deal_id, $contact_id) {
    global $mysqli;
    mysqli_query($mysqli, "DELETE FROM fork_crm_deal_contacts WHERE dc_deal_id = " . intval($deal_id) . " AND dc_contact_id = " . intval($contact_id));
}

/* ---------- quotes that move the deal ---------- */

/**
 * Where a quote's state sends a deal. $counts: how many quotes the deal has (n) and how many are
 * accepted or invoiced (accepted), sent or viewed (sent), declined (declined). $stages: the stages as
 * forkCrmStages() gives them. $current: the deal's stage. Returns ['stage_id', 'reason', 'category'] or
 * null. An accepted quote wins the deal; every quote declined loses it; a quote sent brings it to the
 * "quote sent" stage when that is further on than where it is. A deal already won or lost stays.
 */
function forkCrmQuoteTarget(array $counts, array $stages, $current) {
    $now = $stages[intval($current)] ?? null;
    if (!$now || $now['stage_kind'] !== 'open') {
        return null;
    }
    $find = function ($trigger) use ($stages) {
        foreach ($stages as $stage) {
            if ($stage['stage_quote_trigger'] === $trigger) {
                return $stage;
            }
        }
        return null;
    };
    $n = intval($counts['n'] ?? 0);
    if (intval($counts['accepted'] ?? 0) > 0 && ($to = $find('accepted'))) {
        return ['stage_id' => intval($to['stage_id']), 'reason' => 'A quote was accepted', 'category' => ''];
    }
    if ($n > 0 && intval($counts['declined'] ?? 0) === $n && ($to = $find('declined'))) {
        return ['stage_id' => intval($to['stage_id']), 'reason' => 'Every quote was declined', 'category' => 'quote'];
    }
    if (intval($counts['sent'] ?? 0) > 0 && ($to = $find('sent')) && intval($to['stage_order']) > intval($now['stage_order'])) {
        return ['stage_id' => intval($to['stage_id']), 'reason' => 'A quote was sent', 'category' => ''];
    }
    return null;
}

/** One deal with its stage, client and owner, whoever asks (no scope - for the system). */
function forkCrmDealRaw($deal_id) {
    global $mysqli;
    $result = mysqli_query($mysqli, forkCrmDealSelect() . " WHERE d.deal_id = " . intval($deal_id) . " AND d.deal_archived_at IS NULL");
    return $result ? (mysqli_fetch_assoc($result) ?: null) : null;
}

/**
 * Bring the open deals that have quotes in line with what happened to the quotes (forkCrmQuoteTarget()).
 * Run when the pipeline is opened, and each night. Returns how many deals moved.
 */
function forkCrmSyncQuotes() {
    global $mysqli;
    forkCrmEnsure();
    $stages = forkCrmStages();
    $moves = [];
    $result = mysqli_query($mysqli, "SELECT d.deal_id, d.deal_stage_id, COUNT(*) AS n,
            SUM(q.quote_status IN ('Accepted', 'Invoiced')) AS accepted, SUM(q.quote_status IN ('Sent', 'Viewed')) AS sent, SUM(q.quote_status = 'Declined') AS declined
        FROM fork_crm_deals d
        JOIN fork_crm_stages s ON s.stage_id = d.deal_stage_id AND s.stage_kind = 'open'
        JOIN fork_crm_deal_quotes dq ON dq.dq_deal_id = d.deal_id
        JOIN quotes q ON q.quote_id = dq.dq_quote_id AND q.quote_archived_at IS NULL
        WHERE d.deal_archived_at IS NULL GROUP BY d.deal_id, d.deal_stage_id");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $target = forkCrmQuoteTarget($row, $stages, $row['deal_stage_id']);
        if ($target) {
            $moves[] = [intval($row['deal_id']), $target];
        }
    }
    $moved = 0;
    foreach ($moves as [$deal_id, $target]) {
        $deal = forkCrmDealRaw($deal_id);
        if (!$deal || forkCrmDealMove($deal, $target['stage_id'], $target['reason'], 0, $target['category']) !== '') {
            continue;
        }
        forkCrmHistoryAdd($deal_id, 'auto', $target['reason'] . ' - the deal moved by itself', 0, intval($deal['deal_stage_id']), $target['stage_id']);
        forkCrmFollowUpCreate($deal, $stages[$target['stage_id']], 0);
        $moved++;
    }
    return $moved;
}

/** Make a draft quote for the deal's client from the deal, and link it. Returns [quote_id, error]. */
function forkCrmQuoteCreate(array $deal, $user_id) {
    global $mysqli, $config_quote_prefix, $session_company_currency;
    $client_id = intval($deal['deal_client_id']);
    if ($client_id <= 0) {
        return [0, 'Link the deal to a client or lead first - quotes belong to a client.'];
    }
    $category = mysqli_fetch_row(mysqli_query($mysqli, "SELECT category_id FROM categories WHERE category_type = 'Income' AND category_archived_at IS NULL ORDER BY category_name LIMIT 1"));
    if (!$category) {
        return [0, 'There is no income category yet - add one in Admin > Categories first.'];
    }
    mysqli_query($mysqli, "UPDATE settings SET config_quote_next_number = LAST_INSERT_ID(config_quote_next_number), config_quote_next_number = config_quote_next_number + 1 WHERE company_id = 1");
    $number = intval(mysqli_insert_id($mysqli));
    $prefix = escapeSql((string) $config_quote_prefix);
    $scope = escapeSql(forkCrmTextClean($deal['deal_title'], 255));
    $key = randomString(32);
    $today = date('Y-m-d');
    $expire = date('Y-m-d', strtotime('+30 days'));
    mysqli_query($mysqli, "INSERT INTO quotes SET quote_prefix = '$prefix', quote_number = $number, quote_scope = '$scope', quote_date = '$today', quote_expire = '$expire',
        quote_discount_amount = 0, quote_amount = 0, quote_currency_code = '" . escapeSql((string) $session_company_currency) . "', quote_category_id = " . intval($category[0]) . ",
        quote_status = 'Draft', quote_url_key = '$key', quote_client_id = $client_id");
    $quote_id = intval(mysqli_insert_id($mysqli));
    if ($quote_id <= 0) {
        return [0, 'The quote could not be made.'];
    }
    mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Draft', history_description = 'Quote created!', history_quote_id = $quote_id");
    mysqli_query($mysqli, "INSERT IGNORE INTO fork_crm_deal_quotes SET dq_deal_id = " . intval($deal['deal_id']) . ", dq_quote_id = $quote_id");
    forkCrmHistoryAdd($deal['deal_id'], 'quote', "Quote $prefix$number made from the deal", $user_id);
    if (function_exists('triggerCustomAction')) {
        triggerCustomAction('quote_create', $quote_id);
    }
    return [$quote_id, ''];
}

/* ---------- dormant deals, and the weekly digest ---------- */

/** Is it more than $days days since $last (timestamp) at $now? */
function forkCrmIsStale($last, $now, $days) {
    return intval($now) - intval($last) > forkCrmStaleDaysClean($days) * 86400;
}

/**
 * The open deals nobody has touched for $days days - no activity, no move - that have no task still to
 * do, and that were not told already since the last touch. Each has its owner's id and name.
 */
function forkCrmStaleDeals($days) {
    global $mysqli;
    forkCrmEnsure();
    $days = forkCrmStaleDaysClean($days);
    $out = [];
    $result = mysqli_query($mysqli, "SELECT d.deal_id, d.deal_title, d.deal_owner_id, d.deal_client_id, d.deal_prospect, d.deal_amount, c.client_name,
            GREATEST(d.deal_created_at, COALESCE(d.deal_stage_at, d.deal_created_at),
                COALESCE((SELECT MAX(a.activity_created_at) FROM fork_crm_activities a WHERE a.activity_deal_id = d.deal_id), d.deal_created_at),
                COALESCE((SELECT MAX(a.activity_done_at) FROM fork_crm_activities a WHERE a.activity_deal_id = d.deal_id), d.deal_created_at)) AS last_at,
            d.deal_stale_notified_at
        FROM fork_crm_deals d
        JOIN fork_crm_stages s ON s.stage_id = d.deal_stage_id AND s.stage_kind = 'open'
        LEFT JOIN clients c ON c.client_id = d.deal_client_id
        WHERE d.deal_archived_at IS NULL
        AND NOT EXISTS (SELECT 1 FROM fork_crm_activities t WHERE t.activity_deal_id = d.deal_id AND t.activity_due IS NOT NULL AND t.activity_done_at IS NULL AND t.activity_due >= CURDATE())
        HAVING last_at < DATE_SUB(NOW(), INTERVAL $days DAY) AND (deal_stale_notified_at IS NULL OR deal_stale_notified_at < last_at)");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $out[] = $row;
    }
    return $out;
}

/** The agents who may use the CRM: [user_id => ['name', 'email']] - an admin role or a role with the Sales module. */
function forkCrmAgents() {
    global $mysqli;
    $out = [];
    $result = mysqli_query($mysqli, "SELECT u.user_id, u.user_name, u.user_email, r.role_is_admin FROM users u JOIN user_roles r ON r.role_id = u.user_role_id
        WHERE u.user_status = 1 AND u.user_archived_at IS NULL AND (r.role_is_admin = 1 OR EXISTS (
            SELECT 1 FROM user_role_permissions p JOIN modules m ON m.module_id = p.module_id
            WHERE p.user_role_id = r.role_id AND m.module_name = 'module_sales' AND p.user_role_permission_level >= 1))");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $out[intval($row['user_id'])] = ['name' => $row['user_name'], 'email' => $row['user_email'], 'admin' => intval($row['role_is_admin']) === 1];
    }
    return $out;
}

/**
 * Tell each owner, in ITFlow and by one email, about their dormant deals; the admins about the ones with no
 * owner or whose owner is gone. Each deal is told once until it is touched again. Returns how many deals.
 */
function forkCrmStaleNightly() {
    global $mysqli, $config_base_url;
    $deals = forkCrmStaleDeals(forkCrmSetting('stale_days'));
    if (!$deals) {
        return 0;
    }
    $agents = forkCrmAgents();
    $admins = array_filter($agents, function ($agent) { return $agent['admin']; });
    $per_user = [];
    foreach ($deals as $deal) {
        $owner = intval($deal['deal_owner_id']);
        $to = isset($agents[$owner]) ? [$owner => $agents[$owner]] : $admins;
        foreach ($to as $user_id => $agent) {
            $per_user[$user_id]['agent'] = $agent;
            $per_user[$user_id]['deals'][] = $deal;
        }
        mysqli_query($mysqli, "UPDATE fork_crm_deals SET deal_stale_notified_at = NOW() WHERE deal_id = " . intval($deal['deal_id']));
    }
    $days = forkCrmStaleDaysClean(forkCrmSetting('stale_days'));
    foreach ($per_user as $user_id => $info) {
        $rows = [];
        foreach ($info['deals'] as $deal) {
            $who = (string) ($deal['client_name'] ?: $deal['deal_prospect']);
            $path = '/agent/crm_deal.php?deal_id=' . intval($deal['deal_id']);
            $message = escapeSql(substr("Nothing happened on the deal " . $deal['deal_title'] . ($who !== '' ? " ($who)" : '') . " for $days days", 0, 900));
            mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'CRM', notification = '$message', notification_action = '$path',
                notification_client_id = " . intval($deal['deal_client_id']) . ", notification_user_id = " . intval($user_id) . ", notification_entity_id = " . intval($deal['deal_id']));
            $rows[] = [['text' => $deal['deal_title'], 'url' => "https://$config_base_url$path"], $who !== '' ? $who : '-', billingMoney($deal['deal_amount']), substr((string) $deal['last_at'], 0, 10)];
        }
        emailQueue([['email' => $info['agent']['email'], 'name' => $info['agent']['name'], 'footnote' => 'You get this as these deals are yours in the CRM.']],
            count($rows) . ' deal' . (count($rows) === 1 ? '' : 's') . ' with nothing happening', [
            'kind' => 'CRM', 'tone' => 'warning',
            'title' => 'Deals going cold',
            'intro' => ["Nothing was done on " . (count($rows) === 1 ? 'this deal' : 'these deals') . " for more than $days days, and no task is waiting on " . (count($rows) === 1 ? 'it' : 'them') . ". Follow up, or close " . (count($rows) === 1 ? 'it' : 'them') . "."],
            'lines' => ['head' => ['Deal', 'For', 'Amount', 'Last touched'], 'align' => ['left', 'left', 'right', 'left'], 'rows' => $rows],
            'button' => ['label' => 'Open the pipeline', 'url' => "https://$config_base_url/agent/crm.php"],
        ]);
    }
    return count($deals);
}

/**
 * Mondays, once: each CRM agent gets a digest - their tasks late or due, their open deals, the dormant
 * ones. Nothing for an agent with nothing to say. Returns how many were sent.
 */
function forkCrmDigest($today = null) {
    global $mysqli, $config_base_url;
    $today = $today ?? date('Y-m-d');
    if (forkCrmSetting('digest') !== '1' || (int) date('N', strtotime($today)) !== 1 || forkCrmSetting('digest_last') === $today) {
        return 0;
    }
    forkCrmSettingSet('digest_last', $today);
    $days = forkCrmStaleDaysClean(forkCrmSetting('stale_days'));
    $sent = 0;
    foreach (forkCrmAgents() as $user_id => $agent) {
        $uid = intval($user_id);
        $tasks = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*), SUM(a.activity_due < CURDATE()) FROM fork_crm_activities a
            JOIN fork_crm_deals d ON d.deal_id = a.activity_deal_id JOIN fork_crm_stages s ON s.stage_id = d.deal_stage_id
            WHERE a.activity_due IS NOT NULL AND a.activity_due <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND a.activity_done_at IS NULL AND d.deal_archived_at IS NULL
            AND (s.stage_kind = 'open' OR a.activity_auto = 2) AND (a.activity_user_id = $uid OR a.activity_user_id = 0)"));
        $open = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*), COALESCE(SUM(d.deal_amount), 0), COALESCE(SUM(d.deal_amount * d.deal_probability / 100), 0) FROM fork_crm_deals d
            JOIN fork_crm_stages s ON s.stage_id = d.deal_stage_id WHERE d.deal_archived_at IS NULL AND s.stage_kind = 'open' AND d.deal_owner_id = $uid"));
        $stale = 0;
        foreach (forkCrmStaleDealsFor($days, $uid) as $unused) {
            $stale++;
        }
        $n_tasks = intval($tasks[0] ?? 0);
        $n_open = intval($open[0] ?? 0);
        if ($n_tasks === 0 && $n_open === 0) {
            continue;
        }
        emailQueue([['email' => $agent['email'], 'name' => $agent['name'], 'footnote' => 'You get this every Monday as you use the CRM in ITFlow.']], 'Your CRM week', [
            'kind' => 'CRM', 'tone' => 'primary',
            'title' => 'Your CRM week',
            'intro' => ["Here is where your deals and tasks stand this Monday."],
            'summary' => [
                'Tasks due in the next 7 days or late' => (string) $n_tasks . (intval($tasks[1] ?? 0) > 0 ? ' (' . intval($tasks[1]) . ' late)' : ''),
                'Your open deals' => (string) $n_open . ($n_open > 0 ? ' - ' . billingMoney($open[1]) . ', forecast ' . billingMoney($open[2]) : ''),
                "Deals with nothing happening for $days days" => (string) $stale,
            ],
            'button' => ['label' => 'Open the CRM', 'url' => "https://$config_base_url/agent/crm.php"],
        ]);
        $sent++;
    }
    return $sent;
}

/** The open deals of an owner nobody touched for $days days (not only the ones not told yet). */
function forkCrmStaleDealsFor($days, $user_id) {
    global $mysqli;
    $days = forkCrmStaleDaysClean($days);
    $out = [];
    $result = mysqli_query($mysqli, "SELECT d.deal_id,
            GREATEST(d.deal_created_at, COALESCE(d.deal_stage_at, d.deal_created_at),
                COALESCE((SELECT MAX(a.activity_created_at) FROM fork_crm_activities a WHERE a.activity_deal_id = d.deal_id), d.deal_created_at),
                COALESCE((SELECT MAX(a.activity_done_at) FROM fork_crm_activities a WHERE a.activity_deal_id = d.deal_id), d.deal_created_at)) AS last_at
        FROM fork_crm_deals d JOIN fork_crm_stages s ON s.stage_id = d.deal_stage_id AND s.stage_kind = 'open'
        WHERE d.deal_archived_at IS NULL AND d.deal_owner_id = " . intval($user_id) . "
        AND NOT EXISTS (SELECT 1 FROM fork_crm_activities t WHERE t.activity_deal_id = d.deal_id AND t.activity_due IS NOT NULL AND t.activity_done_at IS NULL AND t.activity_due >= CURDATE())
        HAVING last_at < DATE_SUB(NOW(), INTERVAL $days DAY)");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $out[] = $row;
    }
    return $out;
}

/* ---------- an email from the deal ---------- */

/**
 * Send an email to a person on the deal from ITFlow's mailbox, and keep it in the deal's activities.
 * The body's paragraphs are the lines between blank lines. Returns an error text, or ''.
 */
function forkCrmEmailSend(array $deal, $to, $name, $subject, $body, $agent_name, $agent_email, $user_id) {
    $to = trim((string) $to);
    $subject = forkCrmTextClean($subject, 200);
    $body = trim(strip_tags((string) $body));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return 'Give a valid email address.';
    }
    if ($subject === '' || $body === '') {
        return 'An email needs a subject and a message.';
    }
    if (!emailReady()) {
        return 'Email is not set up in ITFlow - set it up in Admin > Mail first.';
    }
    $paragraphs = array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', $body))));
    if (trim((string) $agent_email) !== '') {
        $paragraphs[] = "You can reply to " . trim((string) $agent_name) . " at " . trim((string) $agent_email) . ".";
    }
    $sent = emailQueue([['email' => $to, 'name' => (string) $name, 'greet' => (string) $name]], $subject, [
        'kind' => 'CRM', 'tone' => 'primary', 'title' => $subject, 'intro' => $paragraphs,
    ]);
    if (!$sent) {
        return 'The email could not be queued.';
    }
    forkCrmActivityAdd($deal['deal_id'], 'email', "To $to - $subject\n\n$body", '', 0, $user_id);
    return '';
}

/* ---------- figures for the dashboard and the export ---------- */

/**
 * The funnel: for each open stage, how many deals got that far. $stage_orders: [stage_id => order] of the
 * open stages, in order; $reached: for each deal, the furthest order it got to. Each stage also says what
 * share of the previous stage's deals went on to it, and of the first's.
 */
function forkCrmFunnel(array $stage_orders, array $reached) {
    $out = [];
    $first = null;
    $previous = null;
    foreach ($stage_orders as $stage_id => $order) {
        $count = 0;
        foreach ($reached as $max) {
            if ($max >= $order) {
                $count++;
            }
        }
        $first = $first ?? $count;
        $out[$stage_id] = [
            'reached' => $count,
            'of_first' => $first > 0 ? round($count * 100 / $first, 1) : null,
            'of_previous' => $previous === null ? null : ($previous > 0 ? round($count * 100 / $previous, 1) : null),
        ];
        $previous = $count;
    }
    return $out;
}

/** The furthest open stage each deal got to: [deal_id => order]. A won deal got to the end. */
function forkCrmDealsReached(array $deals, array $stages) {
    global $mysqli;
    $reached = [];
    $ids = [];
    foreach ($deals as $deal) {
        $ids[] = intval($deal['deal_id']);
        $order = intval($stages[intval($deal['deal_stage_id'])]['stage_order'] ?? 0);
        $reached[intval($deal['deal_id'])] = $deal['stage_kind'] === 'won' ? PHP_INT_MAX : ($deal['stage_kind'] === 'open' ? $order : 0);
    }
    if ($ids) {
        $result = mysqli_query($mysqli, "SELECT h.history_deal_id, MAX(s.stage_order) FROM fork_crm_history h
            JOIN fork_crm_stages s ON s.stage_id = h.history_to_stage AND s.stage_kind = 'open'
            WHERE h.history_deal_id IN (" . implode(',', $ids) . ") GROUP BY h.history_deal_id");
        while ($result && ($row = mysqli_fetch_row($result))) {
            $reached[intval($row[0])] = max($reached[intval($row[0])] ?? 0, intval($row[1]));
        }
    }
    return $reached;
}

/** The average days from a deal made to won, over the won deals that have both dates. Null when none. */
function forkCrmAverageCycle(array $deals) {
    $total = 0;
    $n = 0;
    foreach ($deals as $deal) {
        if ($deal['stage_kind'] !== 'won' || empty($deal['deal_closed_at']) || empty($deal['deal_created_at'])) {
            continue;
        }
        $total += max(0, strtotime($deal['deal_closed_at']) - strtotime($deal['deal_created_at']));
        $n++;
    }
    return $n > 0 ? round($total / $n / 86400, 1) : null;
}

/** What was won, by month of closing: ['2026-10' => ['count' => 2, 'amount' => x], ...] in date order. */
function forkCrmWonByMonth(array $deals) {
    $months = [];
    foreach ($deals as $deal) {
        if ($deal['stage_kind'] !== 'won' || empty($deal['deal_closed_at'])) {
            continue;
        }
        $key = substr($deal['deal_closed_at'], 0, 7);
        $months[$key] = $months[$key] ?? ['count' => 0, 'amount' => 0.0];
        $months[$key]['count']++;
        $months[$key]['amount'] = round($months[$key]['amount'] + floatval($deal['deal_amount']), 2);
    }
    ksort($months);
    return $months;
}

/** A cell for the CSV export: quoted when it has a separator, quote or line break; a formula made harmless. */
function forkCrmCsvCell($value) {
    $text = (string) $value;
    if ($text !== '' && strpos("=+-@\t\r", $text[0]) !== false) {
        $text = "'" . $text;
    }
    if (preg_match('/[;"\r\n]/', $text)) {
        $text = '"' . str_replace('"', '""', $text) . '"';
    }
    return $text;
}
