<?php

/*
 * Backup checks - the fork's record of the manual checks of a client's backups, and the report the client gets.
 *
 * A client that has the service is listed in fork_backup_clients with how often it is checked (every week, every
 * two weeks, once a month) and the servers to check (fork_backup_servers, each tied to an ITFlow asset). The
 * technician enters a check for a client and a week: one line for each server (fork_backup_checks and
 * fork_backup_lines) with RAID, system and data backups, cloud, Veeam version, a comment, and a colour - green,
 * yellow (a problem that was followed up) or red. A report for a client and a period (the last civil quarter is
 * suggested) is made by hand by an agent with the Financial module, with a comment typed each time, and kept as
 * a snapshot (fork_backup_reports). It is exported as PDF or Word, never sent by itself.
 *
 * Everything that decides something - the Monday of a week, whether a check is due, the colour of a line, the
 * rows of a report - takes plain values and touches no database, so scripts/fork_backup_selftest.php can test
 * it alone. The tables are the fork's own, made when first needed; nothing of ITFlow's is changed, and a client
 * or an asset is only ever referred to by its id.
 */

/** The Monday of the week $date ('Y-m-d') is in, or null when it is not a date. */
function forkBackupMonday($date) {
    $date = trim((string) $date);
    $time = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? strtotime($date . ' 12:00:00') : false;
    if ($time === false || date('Y-m-d', $time) !== $date) {
        return null;
    }
    return date('Y-m-d', strtotime('monday this week', $time));
}

/** How often a client is checked. */
function forkBackupFrequencies() {
    return ['weekly' => 'Every week', 'biweekly' => 'Every 2 weeks', 'monthly' => 'Once a month'];
}

function forkBackupFrequencyClean($value) {
    return array_key_exists((string) $value, forkBackupFrequencies()) ? (string) $value : 'weekly';
}

/** What a part of a backup can be. */
function forkBackupStatuses() {
    return ['ok' => 'OK', 'fail' => 'Failed', 'na' => 'N/A'];
}

function forkBackupStatusClean($value) {
    return array_key_exists((string) $value, forkBackupStatuses()) ? (string) $value : 'na';
}

/** The colour of a line. */
function forkBackupStates() {
    return ['green' => 'Green', 'yellow' => 'Yellow', 'red' => 'Red'];
}

/** A colour, or '' when it is not one. */
function forkBackupStateClean($value) {
    return array_key_exists((string) $value, forkBackupStates()) ? (string) $value : '';
}

/** A second check is only needed when something failed in the first. $statuses: the RAID, system, data and cloud statuses. */
function forkBackupSecondNeeded(array $statuses) {
    foreach ($statuses as $status) {
        if (forkBackupStatusClean($status) === 'fail') {
            return true;
        }
    }
    return false;
}

/** The second check: 'none' when the first was all good (it is not needed), else 'ok', 'fail' or 'pending'. */
function forkBackupSecondClean($value, array $statuses) {
    if (!forkBackupSecondNeeded($statuses)) {
        return 'none';
    }
    return in_array((string) $value, ['ok', 'fail'], true) ? (string) $value : 'pending';
}

/** The colour a line should have: green when nothing failed, yellow when the second check put it right, red otherwise. */
function forkBackupSuggestState(array $statuses, $second) {
    if (!forkBackupSecondNeeded($statuses)) {
        return 'green';
    }
    return forkBackupSecondClean($second, $statuses) === 'ok' ? 'yellow' : 'red';
}

/** The worst of some colours (red, then yellow, then green); green when there are none. */
function forkBackupWorst(array $states) {
    foreach (['red', 'yellow'] as $state) {
        if (in_array($state, $states, true)) {
            return $state;
        }
    }
    return 'green';
}

/**
 * Whether a client is to be checked in the week of $week (a Monday). $frequency, $anchor (the Monday the service
 * starts), $done_weeks: the Mondays a check was entered for. A week already done is always shown. Every week:
 * every week. Every two weeks: two weeks after the last check - and when one is missed it stays due. Once a month:
 * until one is entered in the calendar month.
 */
function forkBackupDue($frequency, $anchor, $week, array $done_weeks) {
    if (in_array($week, $done_weeks, true)) {
        return true;
    }
    $anchor = forkBackupMonday($anchor);
    if ($anchor !== null && $week < $anchor) {
        return false;
    }
    $frequency = forkBackupFrequencyClean($frequency);
    if ($frequency === 'monthly') {
        foreach ($done_weeks as $done) {
            if (substr($done, 0, 7) === substr($week, 0, 7)) {
                return false;
            }
        }
        return true;
    }
    if ($frequency === 'biweekly') {
        $before = array_filter($done_weeks, fn($done) => $done < $week);
        if ($before) {
            return (strtotime($week . ' 12:00:00') - strtotime(max($before) . ' 12:00:00')) >= 14 * 86400 - 3600;
        }
    }
    return true;
}

/** Whether a period is exactly one civil quarter. */
function forkBackupIsQuarter($start, $end) {
    return preg_match('/^\d{4}-(01|04|07|10)-01$/', (string) $start) === 1
        && $end === date('Y-m-t', strtotime($start . ' +2 months'));
}

/** The weeks of a check (the lines), as the report reads them: [['week', 'lines' => [['server', 'state', 'comment']]]]. */
function forkBackupReportModel(array $checks) {
    usort($checks, fn($a, $b) => strcmp($a['week'], $b['week']));
    $months = [];
    $counts = ['green' => 0, 'yellow' => 0, 'red' => 0];
    foreach ($checks as $check) {
        $lines = $check['lines'];
        $state = forkBackupWorst(array_column($lines, 'state'));
        $several = count(array_unique(array_column($lines, 'server'))) > 1;
        $detail = [];
        foreach ($lines as $line) {
            if ($line['state'] !== 'green' && trim((string) $line['comment']) !== '') {
                $detail[] = ['server' => $several ? (string) $line['server'] : '', 'comment' => trim((string) $line['comment'])];
            }
        }
        $month = substr($check['week'], 0, 7);
        $months[$month][] = ['week' => $check['week'], 'state' => $state, 'detail' => $detail];
        $counts[$state]++;
    }
    ksort($months);
    return ['months' => $months, 'counts' => $counts, 'total' => count($checks)];
}

/* ------------------------------------------------------------------ database ---- */

function forkBackupEnsure() {
    global $mysqli;
    static $ready = false;
    if ($ready) {
        return;
    }
    $ready = true;
    $tail = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `fork_backup_clients` (
        `bc_client_id` int(11) NOT NULL,
        `bc_frequency` varchar(10) NOT NULL DEFAULT 'weekly',
        `bc_anchor` date NOT NULL,
        `bc_active` tinyint(1) NOT NULL DEFAULT 1,
        `bc_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`bc_client_id`)
    ) $tail");
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `fork_backup_servers` (
        `bs_id` int(11) NOT NULL AUTO_INCREMENT,
        `bs_client_id` int(11) NOT NULL,
        `bs_asset_id` int(11) NOT NULL DEFAULT 0,
        `bs_name` varchar(200) NOT NULL DEFAULT '',
        `bs_active` tinyint(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`bs_id`),
        KEY `bs_client_id` (`bs_client_id`)
    ) $tail");
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `fork_backup_checks` (
        `bk_id` int(11) NOT NULL AUTO_INCREMENT,
        `bk_client_id` int(11) NOT NULL,
        `bk_week` date NOT NULL,
        `bk_user_id` int(11) NOT NULL DEFAULT 0,
        `bk_ticket_id` int(11) NOT NULL DEFAULT 0,
        `bk_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `bk_updated_at` datetime DEFAULT NULL,
        PRIMARY KEY (`bk_id`),
        UNIQUE KEY `bk_client_week` (`bk_client_id`, `bk_week`)
    ) $tail");
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `fork_backup_lines` (
        `bl_id` int(11) NOT NULL AUTO_INCREMENT,
        `bl_check_id` int(11) NOT NULL,
        `bl_server_id` int(11) NOT NULL DEFAULT 0,
        `bl_server_name` varchar(200) NOT NULL DEFAULT '',
        `bl_disk1` varchar(100) NOT NULL DEFAULT '',
        `bl_disk2` varchar(100) NOT NULL DEFAULT '',
        `bl_raid` varchar(10) NOT NULL DEFAULT 'na',
        `bl_sys` varchar(10) NOT NULL DEFAULT 'na',
        `bl_data` varchar(10) NOT NULL DEFAULT 'na',
        `bl_cloud` varchar(10) NOT NULL DEFAULT 'na',
        `bl_veeam` varchar(50) NOT NULL DEFAULT '',
        `bl_second` varchar(10) NOT NULL DEFAULT 'none',
        `bl_state` varchar(10) NOT NULL DEFAULT 'green',
        `bl_comment` text DEFAULT NULL,
        PRIMARY KEY (`bl_id`),
        KEY `bl_check_id` (`bl_check_id`)
    ) $tail");
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `fork_backup_reports` (
        `br_id` int(11) NOT NULL AUTO_INCREMENT,
        `br_client_id` int(11) NOT NULL,
        `br_start` date NOT NULL,
        `br_end` date NOT NULL,
        `br_comment` text DEFAULT NULL,
        `br_data` longtext NOT NULL,
        `br_created_by` int(11) NOT NULL DEFAULT 0,
        `br_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`br_id`),
        KEY `br_client_id` (`br_client_id`)
    ) $tail");
}

/** The clients that have the service, not archived - client_id, client_name, frequency, anchor, active, servers. */
function forkBackupClients() {
    global $mysqli;
    forkBackupEnsure();
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT bc.*, c.client_name,
            (SELECT COUNT(*) FROM fork_backup_servers s WHERE s.bs_client_id = bc.bc_client_id AND s.bs_active = 1) AS servers
        FROM fork_backup_clients bc JOIN clients c ON c.client_id = bc.bc_client_id
        WHERE c.client_archived_at IS NULL ORDER BY c.client_name");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = ['client_id' => intval($row['bc_client_id']), 'client_name' => $row['client_name'], 'frequency' => forkBackupFrequencyClean($row['bc_frequency']),
            'anchor' => $row['bc_anchor'], 'active' => intval($row['bc_active']) === 1, 'servers' => intval($row['servers'])];
    }
    return $rows;
}

/** One client's service, or null when it has none. */
function forkBackupClient($client_id) {
    foreach (forkBackupClients() as $row) {
        if ($row['client_id'] === intval($client_id)) {
            return $row;
        }
    }
    return null;
}

/** The ITFlow clients that do not have the service yet, not archived - id => name. */
function forkBackupClientsToAdd() {
    global $mysqli;
    forkBackupEnsure();
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL
        AND client_id NOT IN (SELECT bc_client_id FROM fork_backup_clients) ORDER BY client_name");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['client_id'])] = $row['client_name'];
    }
    return $rows;
}

/** Gives a client the service, starting the Monday of $anchor (today by default). */
function forkBackupClientAdd($client_id, $frequency, $anchor = null) {
    global $mysqli;
    forkBackupEnsure();
    $client_id = intval($client_id);
    $exists = mysqli_fetch_row(mysqli_query($mysqli, "SELECT client_id FROM clients WHERE client_id = $client_id AND client_archived_at IS NULL"));
    if (!$exists) {
        return false;
    }
    $anchor = forkBackupMonday($anchor) ?? forkBackupMonday(date('Y-m-d'));
    $frequency = forkBackupFrequencyClean($frequency);
    return (bool) mysqli_query($mysqli, "INSERT INTO fork_backup_clients SET bc_client_id = $client_id, bc_frequency = '$frequency', bc_anchor = '$anchor'");
}

function forkBackupClientSave($client_id, $frequency, $anchor, $active) {
    global $mysqli;
    forkBackupEnsure();
    $anchor = forkBackupMonday($anchor);
    if ($anchor === null) {
        return false;
    }
    return (bool) mysqli_query($mysqli, "UPDATE fork_backup_clients SET bc_frequency = '" . forkBackupFrequencyClean($frequency) . "', bc_anchor = '$anchor', bc_active = " . ($active ? 1 : 0) . " WHERE bc_client_id = " . intval($client_id));
}

/** Takes the service away from a client. What was entered stays, so a report of the past still reads. */
function forkBackupClientRemove($client_id) {
    global $mysqli;
    forkBackupEnsure();
    $client_id = intval($client_id);
    mysqli_query($mysqli, "DELETE FROM fork_backup_clients WHERE bc_client_id = $client_id");
    mysqli_query($mysqli, "UPDATE fork_backup_servers SET bs_active = 0 WHERE bs_client_id = $client_id");
}

/** The servers of a client that are checked - bs_id, asset_id, name, os (from the asset). */
function forkBackupServers($client_id) {
    global $mysqli;
    forkBackupEnsure();
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT s.bs_id, s.bs_asset_id, s.bs_name, a.asset_name, a.asset_os FROM fork_backup_servers s
        LEFT JOIN assets a ON a.asset_id = s.bs_asset_id AND a.asset_client_id = s.bs_client_id
        WHERE s.bs_client_id = " . intval($client_id) . " AND s.bs_active = 1 ORDER BY COALESCE(a.asset_name, s.bs_name), s.bs_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['bs_id'])] = ['id' => intval($row['bs_id']), 'asset_id' => intval($row['bs_asset_id']),
            'name' => (string) ($row['asset_name'] ?? '') !== '' ? $row['asset_name'] : (string) $row['bs_name'], 'os' => (string) ($row['asset_os'] ?? '')];
    }
    return $rows;
}

/** The servers in the client's assets that could be checked - asset_id => "name (os)". */
function forkBackupAssets($client_id) {
    global $mysqli;
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_os FROM assets WHERE asset_client_id = " . intval($client_id) . "
        AND asset_archived_at IS NULL AND asset_type = 'Server' ORDER BY asset_name");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['asset_id'])] = $row['asset_name'] . ((string) $row['asset_os'] !== '' ? ' (' . $row['asset_os'] . ')' : '');
    }
    return $rows;
}

/** Adds a server to check, from an asset of the client or by name. */
function forkBackupServerAdd($client_id, $asset_id, $name) {
    global $mysqli;
    forkBackupEnsure();
    $client_id = intval($client_id);
    $asset_id = intval($asset_id);
    $name = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $name)), 0, 200);
    if ($asset_id > 0) {
        $asset = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_name FROM assets WHERE asset_id = $asset_id AND asset_client_id = $client_id"));
        if (!$asset) {
            return false;
        }
        $name = $asset['asset_name'];
    } elseif ($name === '') {
        return false;
    }
    $name = escapeSql($name);
    $dup = mysqli_fetch_row(mysqli_query($mysqli, "SELECT bs_id FROM fork_backup_servers WHERE bs_client_id = $client_id AND bs_active = 1 AND " . ($asset_id > 0 ? "bs_asset_id = $asset_id" : "bs_asset_id = 0 AND bs_name = '$name'")));
    if ($dup) {
        return false;
    }
    return (bool) mysqli_query($mysqli, "INSERT INTO fork_backup_servers SET bs_client_id = $client_id, bs_asset_id = $asset_id, bs_name = '$name'");
}

function forkBackupServerRemove($client_id, $server_id) {
    global $mysqli;
    forkBackupEnsure();
    mysqli_query($mysqli, "UPDATE fork_backup_servers SET bs_active = 0 WHERE bs_id = " . intval($server_id) . " AND bs_client_id = " . intval($client_id));
}

/** The Mondays a client has a check entered for. */
function forkBackupDoneWeeks($client_id) {
    global $mysqli;
    forkBackupEnsure();
    $weeks = [];
    $sql = mysqli_query($mysqli, "SELECT bk_week FROM fork_backup_checks WHERE bk_client_id = " . intval($client_id));
    while ($row = mysqli_fetch_row($sql)) {
        $weeks[] = $row[0];
    }
    return $weeks;
}

/**
 * The clients to check in a week, with what has been done: client_id, client_name, frequency, due, done, state
 * (the worst colour of the week's check, or null), servers. A client with no active service is left out.
 */
function forkBackupWeek($week) {
    global $mysqli;
    $rows = [];
    $states = [];
    $sql = mysqli_query($mysqli, "SELECT k.bk_client_id, l.bl_state FROM fork_backup_checks k JOIN fork_backup_lines l ON l.bl_check_id = k.bk_id WHERE k.bk_week = '" . escapeSql($week) . "'");
    while ($row = mysqli_fetch_assoc($sql)) {
        $states[intval($row['bk_client_id'])][] = $row['bl_state'];
    }
    foreach (forkBackupClients() as $client) {
        if (!$client['active']) {
            continue;
        }
        $done = isset($states[$client['client_id']]);
        $due = forkBackupDue($client['frequency'], $client['anchor'], $week, forkBackupDoneWeeks($client['client_id']));
        if ($due) {
            $rows[] = $client + ['due' => true, 'done' => $done, 'state' => $done ? forkBackupWorst($states[$client['client_id']]) : null];
        }
    }
    return $rows;
}

/** A check of a client for a week - the row plus 'lines' keyed by server id - or null. */
function forkBackupCheck($client_id, $week) {
    global $mysqli;
    forkBackupEnsure();
    $check = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM fork_backup_checks WHERE bk_client_id = " . intval($client_id) . " AND bk_week = '" . escapeSql($week) . "'"));
    if (!$check) {
        return null;
    }
    $check['lines'] = [];
    $sql = mysqli_query($mysqli, "SELECT * FROM fork_backup_lines WHERE bl_check_id = " . intval($check['bk_id']) . " ORDER BY bl_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $check['lines'][intval($row['bl_server_id'])] = $row;
    }
    return $check;
}

/** The last check of a client before a week - its lines keyed by server id, to start the next one from - or []. */
function forkBackupPrevious($client_id, $week) {
    global $mysqli;
    forkBackupEnsure();
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT bk_week FROM fork_backup_checks WHERE bk_client_id = " . intval($client_id) . " AND bk_week < '" . escapeSql($week) . "' ORDER BY bk_week DESC LIMIT 1"));
    $check = $row ? forkBackupCheck($client_id, $row['bk_week']) : null;
    return $check ? $check['lines'] : [];
}

/** The recent tickets of a client a check can point to - ticket_id => "TCK-1 subject". */
function forkBackupTickets($client_id, $include = 0) {
    global $mysqli;
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject FROM tickets
        WHERE ticket_client_id = " . intval($client_id) . " AND (ticket_closed_at IS NULL OR ticket_closed_at > NOW() - INTERVAL 120 DAY OR ticket_id = " . intval($include) . ")
        ORDER BY ticket_id DESC LIMIT 60");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['ticket_id'])] = $row['ticket_prefix'] . $row['ticket_number'] . ' ' . mb_strimwidth((string) $row['ticket_subject'], 0, 70, '…');
    }
    return $rows;
}

/**
 * Saves a client's check for a week. $lines: for each server id, the fields of the form (disk1, disk2, raid, sys,
 * data, cloud, veeam, second, state, comment). Returns [check_id, error] - the error says what to correct and nothing
 * is saved.
 */
function forkBackupCheckSave($client_id, $week, $user_id, $ticket_id, array $lines) {
    global $mysqli;
    forkBackupEnsure();
    $client_id = intval($client_id);
    $week = forkBackupMonday($week);
    $servers = forkBackupServers($client_id);
    if ($week === null || !$servers || !forkBackupClient($client_id)) {
        return [0, 'That client has no servers to check.'];
    }
    $ticket_id = intval($ticket_id);
    if ($ticket_id > 0 && !mysqli_fetch_row(mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_id = $ticket_id AND ticket_client_id = $client_id"))) {
        $ticket_id = 0;
    }
    $clean = [];
    foreach ($servers as $server_id => $server) {
        $line = (array) ($lines[$server_id] ?? []);
        $statuses = [forkBackupStatusClean($line['raid'] ?? ''), forkBackupStatusClean($line['sys'] ?? ''), forkBackupStatusClean($line['data'] ?? ''), forkBackupStatusClean($line['cloud'] ?? '')];
        $second = forkBackupSecondClean($line['second'] ?? '', $statuses);
        $state = forkBackupStateClean($line['state'] ?? '') ?: forkBackupSuggestState($statuses, $second);
        if ($state === 'green' && forkBackupSecondNeeded($statuses)) {
            $state = forkBackupSuggestState($statuses, $second); // something failed: the line cannot be green
        }
        $comment = mb_substr(str_replace("\r", '', trim((string) ($line['comment'] ?? ''))), 0, 2000);
        if ($state !== 'green' && $comment === '') {
            return [0, 'Say what happened on ' . $server['name'] . ' in its comment - it is ' . $state . '.'];
        }
        $clean[$server_id] = ['name' => $server['name'], 'disk1' => mb_substr(trim((string) ($line['disk1'] ?? '')), 0, 100), 'disk2' => mb_substr(trim((string) ($line['disk2'] ?? '')), 0, 100),
            'raid' => $statuses[0], 'sys' => $statuses[1], 'data' => $statuses[2], 'cloud' => $statuses[3], 'veeam' => mb_substr(trim((string) ($line['veeam'] ?? '')), 0, 50),
            'second' => $second, 'state' => $state, 'comment' => $comment];
    }
    $existing = forkBackupCheck($client_id, $week);
    if ($existing) {
        $check_id = intval($existing['bk_id']);
        mysqli_query($mysqli, "UPDATE fork_backup_checks SET bk_user_id = " . intval($user_id) . ", bk_ticket_id = $ticket_id, bk_updated_at = NOW() WHERE bk_id = $check_id");
        mysqli_query($mysqli, "DELETE FROM fork_backup_lines WHERE bl_check_id = $check_id");
    } else {
        mysqli_query($mysqli, "INSERT INTO fork_backup_checks SET bk_client_id = $client_id, bk_week = '$week', bk_user_id = " . intval($user_id) . ", bk_ticket_id = $ticket_id");
        $check_id = intval(mysqli_insert_id($mysqli));
    }
    foreach ($clean as $server_id => $line) {
        mysqli_query($mysqli, "INSERT INTO fork_backup_lines SET bl_check_id = $check_id, bl_server_id = " . intval($server_id) . ", bl_server_name = '" . escapeSql($line['name']) . "',
            bl_disk1 = '" . escapeSql($line['disk1']) . "', bl_disk2 = '" . escapeSql($line['disk2']) . "', bl_raid = '{$line['raid']}', bl_sys = '{$line['sys']}', bl_data = '{$line['data']}',
            bl_cloud = '{$line['cloud']}', bl_veeam = '" . escapeSql($line['veeam']) . "', bl_second = '{$line['second']}', bl_state = '{$line['state']}', bl_comment = '" . escapeSql($line['comment']) . "'");
    }
    return [$check_id, ''];
}

/* ---------------------------------------------------------------- the report ---- */

/** The checks of a client between two dates, as forkBackupReportModel() reads them. */
function forkBackupChecksBetween($client_id, $start, $end) {
    global $mysqli;
    forkBackupEnsure();
    $checks = [];
    $sql = mysqli_query($mysqli, "SELECT k.bk_week, l.bl_server_name, l.bl_state, l.bl_comment FROM fork_backup_checks k JOIN fork_backup_lines l ON l.bl_check_id = k.bk_id
        WHERE k.bk_client_id = " . intval($client_id) . " AND k.bk_week BETWEEN '" . escapeSql($start) . "' AND '" . escapeSql($end) . "' ORDER BY k.bk_week, l.bl_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $checks[$row['bk_week']]['week'] = $row['bk_week'];
        $checks[$row['bk_week']]['lines'][] = ['server' => $row['bl_server_name'], 'state' => forkBackupStateClean($row['bl_state']) ?: 'green', 'comment' => (string) $row['bl_comment']];
    }
    return array_values($checks);
}

/** The period to suggest: the last civil quarter - ['start', 'end']. */
function forkBackupPeriod($today = null) {
    $period = forkHoursReportPeriod($today);
    return ['start' => $period['start'], 'end' => $period['end']];
}

/** Makes a report for a client and a period, with the comment typed for it. Returns [report_id, error]. */
function forkBackupReportCreate($client_id, $start, $end, $comment, $user_id) {
    global $mysqli;
    forkBackupEnsure();
    $client_id = intval($client_id);
    $start = forkBackupDateOnly($start);
    $end = forkBackupDateOnly($end);
    $comment = mb_substr(str_replace("\r", '', trim((string) $comment)), 0, 3000);
    if ($start === null || $end === null || $end < $start) {
        return [0, 'Give a period that starts before it ends.'];
    }
    if ($comment === '') {
        return [0, 'Write the comment of the report first - it says how the backups are doing.'];
    }
    $model = forkBackupReportModel(forkBackupChecksBetween($client_id, $start, $end));
    if (!$model['total']) {
        return [0, 'No check was entered for this client in that period.'];
    }
    mysqli_query($mysqli, "INSERT INTO fork_backup_reports SET br_client_id = $client_id, br_start = '$start', br_end = '$end', br_comment = '" . escapeSql($comment) . "',
        br_data = '" . escapeSql(json_encode($model, JSON_UNESCAPED_UNICODE)) . "', br_created_by = " . intval($user_id));
    return [intval(mysqli_insert_id($mysqli)), ''];
}

/** A date 'Y-m-d' or null. */
function forkBackupDateOnly($date) {
    $date = trim((string) $date);
    $time = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? strtotime($date . ' 12:00:00') : false;
    return $time !== false && date('Y-m-d', $time) === $date ? $date : null;
}

/** A report with its client's name and its data decoded - or null. */
function forkBackupReportRow($report_id) {
    global $mysqli;
    forkBackupEnsure();
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT r.*, c.client_name FROM fork_backup_reports r JOIN clients c ON c.client_id = r.br_client_id WHERE r.br_id = " . intval($report_id)));
    if (!$row) {
        return null;
    }
    $row['data'] = json_decode((string) $row['br_data'], true) ?: ['months' => [], 'counts' => ['green' => 0, 'yellow' => 0, 'red' => 0], 'total' => 0];
    return $row;
}

function forkBackupReports($client_id) {
    global $mysqli;
    forkBackupEnsure();
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT br_id, br_start, br_end, br_created_at FROM fork_backup_reports WHERE br_client_id = " . intval($client_id) . " ORDER BY br_start DESC, br_id DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

function forkBackupReportCommentSave($report_id, $comment) {
    global $mysqli;
    $comment = mb_substr(str_replace("\r", '', trim((string) $comment)), 0, 3000);
    if ($comment === '') {
        return false;
    }
    return (bool) mysqli_query($mysqli, "UPDATE fork_backup_reports SET br_comment = '" . escapeSql($comment) . "' WHERE br_id = " . intval($report_id));
}

function forkBackupReportDelete($report_id) {
    global $mysqli;
    mysqli_query($mysqli, "DELETE FROM fork_backup_reports WHERE br_id = " . intval($report_id));
}

/* ----------------------------------------------- the report, as the client reads it ---- */

/** The title of a report, in the language in force. */
function forkBackupReportTitle(array $report) {
    return forkBackupIsQuarter($report['br_start'], $report['br_end']) ? t('Quarterly backup check report') : t('Backup check report');
}

/** What a colour means to the client, in the language in force. */
function forkBackupStateLabel($state) {
    return match ($state) {
        'red' => t('Failed'),
        'yellow' => t('Issue followed up'),
        default => t('OK'),
    };
}

/** A week's detail as text: the comment, or "/" when there is none; several servers each on a line with its name. */
function forkBackupDetailText(array $detail) {
    if (!$detail) {
        return '/';
    }
    $lines = [];
    foreach ($detail as $item) {
        $lines[] = $item['server'] !== '' ? t('{server}: {comment}', ['{server}' => $item['server'], '{comment}' => $item['comment']]) : $item['comment'];
    }
    return implode("\n", $lines);
}

/**
 * The rows of the table of a report, in the language in force: ['type' => 'month', 'label'] and
 * ['type' => 'week', 'day', 'state', 'label', 'detail'].
 */
function forkBackupReportRows(array $report) {
    $rows = [];
    foreach ($report['data']['months'] as $month => $weeks) {
        $rows[] = ['type' => 'month', 'label' => i18n_date('F', strtotime($month . '-01 12:00:00'))];
        foreach ($weeks as $week) {
            $rows[] = ['type' => 'week', 'day' => date('j', strtotime($week['week'] . ' 12:00:00')), 'state' => $week['state'],
                'label' => forkBackupStateLabel($week['state']), 'detail' => forkBackupDetailText($week['detail'])];
        }
    }
    return $rows;
}

/** The colours of a state in the report - [fill, text]. */
function forkBackupStateColours($state) {
    return ['red' => ['FF0000', 'FFFFFF'], 'yellow' => ['FFFF00', '000000']][$state] ?? ['00B050', 'FFFFFF'];
}

/** The report as a PDF, in the client's language - ['name', 'data']. */
function forkBackupPdf(array $report) {
    require_once __DIR__ . '/../libs/TCPDF/tcpdf.php';
    require_once __DIR__ . '/billing_pdf.php';
    return i18n_with(client_language(), fn() => forkBackupPdfRender($report, billingCompany()));
}

function forkBackupPdfRender(array $report, array $company) {
    billingPdfConfig();
    $e = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    $grey = '#6c757d';
    $title = forkBackupReportTitle($report);
    $format = in_array($company['company_country'] ?? '', ['United States', 'USA', 'US', 'Canada'], true) ? 'LETTER' : 'A4';
    $pdf = new BillingPdf('P', 'mm', $format, true, 'UTF-8', false);
    $pdf->SetCreator('ITFlow');
    $pdf->SetAuthor((string) ($company['company_name'] ?? ''));
    $pdf->SetTitle($title . ' ' . $report['client_name']);
    $pdf->SetMargins(16, 14, 16);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(12);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->setFontSubsetting(true);
    $pdf->footer_label = trim(($company['company_name'] ?? '') . ' · ' . $title . ' · ' . $report['client_name'], ' ·');
    $pdf->AddPage();
    $pdf->Ln(2);
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->SetTextColor(33, 37, 41);

    $pdf->writeHTML('<table cellpadding="0" cellspacing="0" width="100%"><tr><td width="45%"><span style="font-size:12pt; font-weight:bold;">' . $e($company['company_name'] ?? '') . '</span></td>
        <td width="55%" align="right"><span style="font-size:14pt; font-weight:bold;">' . $e($title) . '</span></td></tr></table>', true, false, true, false, '');
    $pdf->Ln(4);
    $pdf->writeHTML('<table cellpadding="2" cellspacing="0" width="100%"><tr><td width="18%"><b>' . $e(t('Client')) . '</b></td><td width="82%">' . $e($report['client_name']) . '</td></tr>
        <tr><td><b>' . $e(t('Period')) . '</b></td><td>' . $e(t('{from} to {to}', ['{from}' => i18n_date_value($report['br_start']), '{to}' => i18n_date_value($report['br_end'])])) . '</td></tr></table>', true, false, true, false, '');
    $pdf->Ln(3);
    $pdf->writeHTML('<b>' . $e(t('Comment')) . '</b>', true, false, true, false, '');
    $pdf->writeHTML('<span>' . nl2br($e($report['br_comment'])) . '</span>', true, false, true, false, '');
    $pdf->Ln(4);

    $th = 'font-size:8pt; font-weight:bold; border-bottom:0.6px solid #adb5bd;';
    $html = '<table cellpadding="4" cellspacing="0" width="100%"><thead><tr><td width="12%" style="' . $th . '">' . $e(t('Week')) . '</td><td width="24%" style="' . $th . '">' . $e(t('Status')) . '</td><td width="64%" style="' . $th . '">' . $e(t('Detail')) . '</td></tr></thead>';
    foreach (forkBackupReportRows($report) as $row) {
        if ($row['type'] === 'month') {
            $html .= '<tr nobr="true"><td colspan="3" style="background-color:#f1f3f5; font-weight:bold; border-bottom:0.3px solid #dee2e6;">' . $e($row['label']) . '</td></tr>';
            continue;
        }
        [$fill, $text] = forkBackupStateColours($row['state']);
        $html .= '<tr nobr="true"><td width="12%" style="border-bottom:0.3px solid #dee2e6;">' . $e($row['day']) . '</td>
            <td width="24%" style="border-bottom:0.3px solid #dee2e6; background-color:#' . $fill . '; color:#' . $text . ';" align="center">' . $e($row['label']) . '</td>
            <td width="64%" style="border-bottom:0.3px solid #dee2e6;">' . nl2br($e($row['detail'])) . '</td></tr>';
    }
    $pdf->writeHTML($html . '</table>', true, false, true, false, '');

    $name = preg_replace('/[^A-Za-z0-9_\-]+/', '_', trim($report['br_end'] . '_' . $report['client_name'] . '_Backup_check_' . $report['br_start'], '_')) . '.pdf';
    return ['name' => $name, 'data' => $pdf->Output($name, 'S')];
}

/** The report as a Word file (.docx), in the client's language - ['name', 'data']. */
function forkBackupDocx(array $report) {
    return i18n_with(client_language(), fn() => forkBackupDocxRender($report));
}

function forkBackupDocxRender(array $report) {
    $x = fn($value) => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $run = function ($text, $bold = false, $colour = '') use ($x) {
        $parts = explode("\n", (string) $text);
        $xml = '';
        foreach ($parts as $i => $part) {
            $xml .= '<w:r><w:rPr>' . ($bold ? '<w:b/>' : '') . ($colour !== '' ? '<w:color w:val="' . $colour . '"/>' : '') . '<w:sz w:val="21"/></w:rPr>'
                . ($i > 0 ? '<w:br/>' : '') . '<w:t xml:space="preserve">' . $x($part) . '</w:t></w:r>';
        }
        return $xml;
    };
    $para = fn($inner, $after = 120) => '<w:p><w:pPr><w:spacing w:after="' . $after . '"/></w:pPr>' . $inner . '</w:p>';
    $cell = fn($width, $inner, $fill = '') => '<w:tc><w:tcPr><w:tcW w:w="' . $width . '" w:type="dxa"/>' . ($fill !== '' ? '<w:shd w:val="clear" w:color="auto" w:fill="' . $fill . '"/>' : '') . '</w:tcPr>' . $para($inner, 0) . '</w:tc>';
    $widths = [1100, 2200, 5700];

    $body = $para($run(forkBackupReportTitle($report), true), 240);
    $body .= $para($run(t('Client') . ' : ', true) . $run($report['client_name']), 60);
    $body .= $para($run(t('Period') . ' : ', true) . $run(t('{from} to {to}', ['{from}' => i18n_date_value($report['br_start']), '{to}' => i18n_date_value($report['br_end'])])), 240);
    $body .= $para($run(t('Comment') . ' :', true), 60);
    $body .= $para($run($report['br_comment']), 240);

    $borders = '<w:tblBorders>' . implode('', array_map(fn($side) => '<w:' . $side . ' w:val="single" w:sz="4" w:space="0" w:color="BFBFBF"/>', ['top', 'left', 'bottom', 'right', 'insideH', 'insideV'])) . '</w:tblBorders>';
    $table = '<w:tbl><w:tblPr><w:tblW w:w="9000" w:type="dxa"/>' . $borders . '<w:tblLayout w:type="fixed"/></w:tblPr><w:tblGrid>' . implode('', array_map(fn($w) => '<w:gridCol w:w="' . $w . '"/>', $widths)) . '</w:tblGrid>';
    $table .= '<w:tr><w:trPr><w:tblHeader/></w:trPr>' . $cell($widths[0], $run(t('Week'), true), 'F1F3F5') . $cell($widths[1], $run(t('Status'), true), 'F1F3F5') . $cell($widths[2], $run(t('Detail'), true), 'F1F3F5') . '</w:tr>';
    foreach (forkBackupReportRows($report) as $row) {
        if ($row['type'] === 'month') {
            $table .= '<w:tr><w:trPr><w:cantSplit/></w:trPr><w:tc><w:tcPr><w:tcW w:w="9000" w:type="dxa"/><w:gridSpan w:val="3"/><w:shd w:val="clear" w:color="auto" w:fill="E9ECEF"/></w:tcPr>' . $para($run($row['label'], true), 0) . '</w:tc></w:tr>';
            continue;
        }
        [$fill, $text] = forkBackupStateColours($row['state']);
        $table .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>' . $cell($widths[0], $run($row['day'])) . $cell($widths[1], $run($row['label'], true, $text), $fill) . $cell($widths[2], $run($row['detail'])) . '</w:tr>';
    }
    $table .= '</w:tbl>';
    $body .= $table . $para('', 0);

    $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' . $body
        . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1418" w:bottom="1134" w:left="1418" w:header="709" w:footer="709" w:gutter="0"/></w:sectPr></w:body></w:document>';
    $types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>';
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>';

    $file = tempnam(sys_get_temp_dir(), 'bkdocx');
    $zip = new ZipArchive();
    $zip->open($file, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', $types);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('word/document.xml', $document);
    $zip->close();
    $data = (string) file_get_contents($file);
    unlink($file);

    $name = preg_replace('/[^A-Za-z0-9_\-]+/', '_', trim($report['br_end'] . '_' . $report['client_name'] . '_Backup_check_' . $report['br_start'], '_')) . '.docx';
    return ['name' => $name, 'data' => $data];
}
