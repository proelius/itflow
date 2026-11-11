<?php

/*
 * Prepaid hours: alerts at 20 %, 10 % and 0 % left, for each block bought
 * (functions/fork_hours.php, functions/contract_hours.php).
 *
 * When the hours left in a block of hours fall to a fifth, a tenth, or none of
 * what it was bought for, the client's invoice contacts are emailed - in the
 * client's language, no invoice attached, nothing sent for them to pay - and
 * the agents with the Financial module (or an admin role) are told in ITFlow
 * and by email, to offer a renewal themselves. Each level is told once for a
 * block: if hours drop past several at once, the lowest is told and the ones
 * above it count as told; hours put back that lift a block over a level tell
 * it again next time. The hours included each month (blocks that run out at the
 * month's end) are not alerted. The contract owner's own alert, set on the
 * contract (contractHoursAlertCheck()), is left as it is.
 *
 * forkHoursAlertPlan() decides from plain values and touches no database.
 */

/** The levels, in percent of the hours bought, highest first. */
function forkHoursAlertLevels() {
    return [20, 10, 0];
}

/**
 * What to do for a block bought for $hours, with $left hours left, when the levels in $sent were told:
 * ['send' => the level to tell (the lowest one reached and not told), or null,
 *  'mark' => the levels to record as told (that one and any above it),
 *  'clear' => the told levels the hours are back above].
 */
function forkHoursAlertPlan($hours, $left, array $sent) {
    $hours = round(floatval($hours), 2);
    $left = round(floatval($left), 2);
    $plan = ['send' => null, 'mark' => [], 'clear' => []];
    if ($hours <= 0) {
        return $plan;
    }
    $reached = [];
    foreach (forkHoursAlertLevels() as $level) {
        $reaches = $left <= round($hours * $level / 100, 2);
        if ($reaches) {
            $reached[] = $level;
        } elseif (in_array($level, $sent, true)) {
            $plan['clear'][] = $level;
        }
    }
    $new = array_values(array_diff($reached, $sent));
    if ($new) {
        $plan['send'] = min($new);
        $plan['mark'] = $new;
    }
    return $plan;
}

/** The agents who are told - active, with an admin role or the Financial module. user_id => [email, name]. */
function forkHoursAlertAgents() {
    global $mysqli;
    $module_id = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT module_id FROM modules WHERE module_name = 'module_financial' LIMIT 1"))[0] ?? 0);
    $sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_email, role_is_admin,
            COALESCE((SELECT MAX(user_role_permission_level) FROM user_role_permissions WHERE user_role_permissions.user_role_id = users.user_role_id AND module_id = $module_id), 0) AS financial_level
        FROM users LEFT JOIN user_roles ON role_id = user_role_id
        WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
    $agents = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        if ((intval($row['role_is_admin']) === 1 || intval($row['financial_level']) >= 1) && filter_var($row['user_email'], FILTER_VALIDATE_EMAIL)) {
            $agents[intval($row['user_id'])] = ['email' => $row['user_email'], 'name' => $row['user_name']];
        }
    }
    return $agents;
}

/** Check a contract's blocks of hours against the levels, and tell who is to be told. How many alerts were sent. */
function forkHoursAlertsCheck($contract_id) {
    global $mysqli;
    if (!contractHoursReady()) {
        return 0;
    }
    forkHoursEnsure();
    $contract = contractRow($contract_id);
    if (!$contract || !contractHoursLive($contract)) {
        return 0;
    }
    $sent_count = 0;
    foreach (contractBlocks($contract_id) as $block) {
        if (!empty($block['pending']) || $block['expired'] || $block['contract_block_month'] !== null || floatval($block['contract_block_hours']) <= 0) {
            continue;
        }
        $block_id = intval($block['contract_block_id']);
        $sent = [];
        $sql = mysqli_query($mysqli, "SELECT fork_alert_level FROM fork_hours_alerts WHERE fork_alert_block_id = $block_id");
        while ($row = mysqli_fetch_row($sql)) {
            $sent[] = intval($row[0]);
        }
        $plan = forkHoursAlertPlan($block['contract_block_hours'], $block['left'], $sent);
        if ($plan['clear']) {
            mysqli_query($mysqli, "DELETE FROM fork_hours_alerts WHERE fork_alert_block_id = $block_id AND fork_alert_level IN (" . implode(',', array_map('intval', $plan['clear'])) . ")");
        }
        if ($plan['send'] === null) {
            continue;
        }
        $claimed = false;
        foreach ($plan['mark'] as $level) {
            mysqli_query($mysqli, "INSERT IGNORE INTO fork_hours_alerts (fork_alert_block_id, fork_alert_level) VALUES ($block_id, " . intval($level) . ")");
            if (intval($level) === $plan['send'] && mysqli_affected_rows($mysqli) === 1) {
                $claimed = true;
            }
        }
        if ($claimed) {
            forkHoursAlertSend($contract, $block, $plan['send']);
            $sent_count++;
        }
    }
    return $sent_count;
}

/** Tell the client's invoice contacts, and the agents, that a block of hours is at $level percent. */
function forkHoursAlertSend(array $contract, array $block, $level) {
    global $config_base_url;
    $client_id = intval($contract['contract_client_id']);
    $left = round(floatval($block['left']), 2);
    $total = round(floatval($block['contract_block_hours']), 2);

    // The client - in its language, nothing attached, nothing to pay
    $recipients = billingEmailRecipients($client_id);
    if ($recipients) {
        $url = "https://$config_base_url/client/contract.php?id=" . intval($contract['contract_id']);
        [$subject, $email] = billingEmailInClientLanguage(function () use ($contract, $block, $level, $left, $total, $url) {
            $n = ['{contract}' => $contract['contract_name'], '{left}' => contractHoursText($left), '{total}' => contractHoursText($total)];
            if ($level === 0) {
                $subject = t('Prepaid hours used up on {contract}', $n);
                $title = t('Your prepaid hours are used up');
                $intro = t('Your prepaid hours on {contract} are used up. Time worked from now on is invoiced at the usual rate.', $n);
                $tone = 'danger';
            } elseif ($level <= 10) {
                $subject = t('Prepaid hours: {left} left on {contract}', $n);
                $title = t('Your prepaid hours are almost used up');
                $intro = t('Your prepaid hours on {contract} are almost used up: {left} of {total} are left.', $n);
                $tone = 'warning';
            } else {
                $subject = t('Prepaid hours: {left} left on {contract}', $n);
                $title = t('Your prepaid hours are running low');
                $intro = t('Your prepaid hours on {contract} are running low: {left} of {total} are left.', $n);
                $tone = 'warning';
            }
            $company = billingCompany();
            $contact_line = !empty($company['phone_display']) ? t('Questions? Reply to this email or call us on {phone}.', ['{phone}' => $company['phone_display']]) : t('Questions? Reply to this email.');
            return [$subject, [
                'kind' => t('Prepaid hours'), 'tone' => $tone, 'title' => $title, 'intro' => [$intro],
                'summary' => array_filter([
                    t('Contract') => (string) $contract['contract_name'],
                    t('Prepaid') => contractHoursText($total),
                    t('Used') => contractHoursText($block['used']),
                    t('Left') => contractHoursText($left),
                    t('Expires') => !empty($block['contract_block_expires']) ? billingEmailDate($block['contract_block_expires']) : '',
                ]),
                'after' => [$contact_line],
                'button' => ['label' => t('View your prepaid hours'), 'url' => $url],
                'footnote' => billingEmailFootnote($contract['client_name']),
            ]];
        });
        billingEmailQueue($recipients, $subject, $email);
    }

    // The agents with the Financial module - in ITFlow, and by email, to offer a renewal
    $what = $level === 0 ? 'has no hours left' : 'has ' . contractHoursWords($left) . ' left (' . $level . ' % or less)';
    $block_name = contractHoursWords($total) . ' bought on ' . date('M j, Y', strtotime($block['contract_block_bought']));
    $path = contractAgentPath($contract);
    appNotify("Contract", escapeSql("{$contract['client_name']} $what on a block of " . contractHoursWords($total) . " ({$contract['contract_name']}) - time to offer a renewal"), $path, $client_id, intval($contract['contract_id']));
    $agents = [];
    foreach (forkHoursAlertAgents() as $agent) {
        $agents[] = $agent + ['footnote' => 'You get this as you have access to the financial side of ITFlow.'];
    }
    if ($agents) {
        emailQueue($agents, "Prepaid hours " . ($level === 0 ? 'used up' : 'running low') . ": {$contract['contract_name']} - {$contract['client_name']}", [
            'kind' => 'Contract', 'tone' => $level === 0 ? 'danger' : 'warning',
            'title' => "{$contract['client_name']} $what",
            'intro' => ["The block of prepaid hours on {$contract['contract_name']} is at $level % or less - a good time to offer the client a renewal. "
                . ($recipients ? 'The client was emailed, with no invoice: a renewal invoice is yours to make and send.' : 'The client has no invoice contact with an email address, so nobody was emailed.')],
            'summary' => [
                'Client' => (string) $contract['client_name'],
                'Block' => $block_name,
                'Used' => contractHoursWords($block['used']),
                'Left' => contractHoursWords($left),
                'Expires' => !empty($block['contract_block_expires']) ? date('M j, Y', strtotime($block['contract_block_expires'])) : 'Never',
            ],
            'button' => ['label' => 'Open the contract', 'url' => "https://$config_base_url$path"],
        ]);
    }
}
