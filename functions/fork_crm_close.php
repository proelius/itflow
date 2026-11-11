<?php

/*
 * CRM - what happens by itself when a quote is answered and when a deal is won or lost.
 *
 * Automatic is the CRM's own bookkeeping: tasks, notifications, history. Anything that touches an
 * invoice, a contract or a client record is never done by itself - the deal page lists it as a
 * "next step" that an agent starts with one click (and the usual confirmation of ITFlow's own page).
 *
 *   forkCrmQuoteChanged()  a linked quote was accepted or declined: the owner hears at once
 *   forkCrmAfterClose()    a deal entered a won, lost or open stage (called by forkCrmDealMove())
 *   forkCrmDealSteps()     the "next steps" of a won deal, each todo, done or not needed
 *   forkCrmQuotesDecline() the quotes still open of a lost deal, marked declined
 *
 * What decides from plain values (forkCrmCloseDaysClean(), forkCrmStepState()) takes no database.
 * The check-in and recontact tasks carry activity_auto = 2 (the follow-up after a quote is 1).
 */

/* ---------- plain values ---------- */

/** The days before a check-in or a recontact task: 0 (none) to 365. */
function forkCrmCloseDaysClean($days) {
    $days = intval($days);
    return $days < 0 ? 0 : min($days, 365);
}

/** The next steps of a won deal: key => label. */
function forkCrmStepLabels() {
    return [
        'client' => 'Make the prospect a client',
        'invoice' => 'Invoice the accepted quote',
        'contract' => 'Contract or prepaid hours',
        'onboarding' => 'Start the client onboarding',
        'welcome' => 'Send the welcome email',
    ];
}

/**
 * Where one next step stands: 'done', 'todo' or 'na' (not needed). $stored is what an agent said
 * ('done', 'skip' or ''). $facts: client (is a client, not a prospect), accepted (quotes accepted, not yet
 * invoiced), invoiced (quotes invoiced), monthly (recurring amount), onboarding (it was started),
 * emailed (an email left from the deal since it was won). What an agent said wins.
 */
function forkCrmStepState($key, array $facts, $stored = '') {
    if ($stored === 'skip') {
        return 'na';
    }
    if ($stored === 'done') {
        return 'done';
    }
    switch ($key) {
        case 'client':
            return !empty($facts['client']) ? 'done' : 'todo';
        case 'invoice':
            if (intval($facts['accepted'] ?? 0) > 0) {
                return 'todo';
            }
            return intval($facts['invoiced'] ?? 0) > 0 ? 'done' : 'na';
        case 'contract':
            return floatval($facts['monthly'] ?? 0) > 0 ? 'todo' : 'na';
        case 'onboarding':
            return !empty($facts['onboarding']) ? 'done' : 'todo';
        case 'welcome':
            return !empty($facts['emailed']) ? 'done' : 'todo';
    }
    return 'na';
}

/* ---------- who hears ---------- */

/** The agents who hear of a deal: its owner, or the admins when it has none (or the owner is gone). [user_id => agent]. */
function forkCrmDealAgents(array $deal) {
    $agents = forkCrmAgents();
    $owner = intval($deal['deal_owner_id'] ?? 0);
    if (isset($agents[$owner])) {
        return [$owner => $agents[$owner]];
    }
    return array_filter($agents, function ($agent) { return $agent['admin']; });
}

/** An ITFlow notification for each of $agents, but not for the one who just did it. */
function forkCrmNotifyAgents(array $agents, $text, $path, $client_id, $deal_id) {
    global $mysqli, $session_user_id;
    $by = intval($session_user_id ?? 0);
    $message = escapeSql(substr($text, 0, 900));
    $told = [];
    foreach ($agents as $user_id => $agent) {
        if ($by > 0 && intval($user_id) === $by) {
            continue;
        }
        mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'CRM', notification = '$message', notification_action = '" . escapeSql($path) . "',
            notification_client_id = " . intval($client_id) . ", notification_user_id = " . intval($user_id) . ", notification_entity_id = " . intval($deal_id));
        $told[intval($user_id)] = $agent;
    }
    return $told;
}

/* ---------- a quote was answered ---------- */

/**
 * A quote of a deal was accepted or declined: the deal's owner (the admins when it has none) is told
 * at once, in ITFlow and by email - a declined quote asks for the reason - and the deals follow their
 * quotes now rather than at the next page or night (forkCrmSyncQuotes()). Safe to call from anywhere:
 * nothing happens when the CRM was never used, and it never throws. Returns how many deals were told.
 */
function forkCrmQuoteChanged($quote_id, $status) {
    global $mysqli, $config_base_url;
    try {
        if (!in_array($status, ['Accepted', 'Declined'], true)) {
            return 0;
        }
        $exists = mysqli_query($mysqli, "SHOW TABLES LIKE 'fork_crm_deal_quotes'");
        if (!$exists || mysqli_num_rows($exists) === 0) {
            return 0;
        }
        forkCrmEnsure();
        $quote_id = intval($quote_id);
        $quote = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT quote_prefix, quote_number FROM quotes WHERE quote_id = $quote_id"));
        if (!$quote) {
            return 0;
        }
        $number = trim($quote['quote_prefix'] . $quote['quote_number']);
        $ids = [];
        $result = mysqli_query($mysqli, "SELECT dq_deal_id FROM fork_crm_deal_quotes WHERE dq_quote_id = $quote_id");
        while ($result && ($row = mysqli_fetch_row($result))) {
            $ids[] = intval($row[0]);
        }
        $told = 0;
        foreach ($ids as $deal_id) {
            $deal = forkCrmDealRaw($deal_id);
            if (!$deal) {
                continue;
            }
            $who = forkCrmDealWho($deal);
            $word = $status === 'Accepted' ? 'accepted' : 'declined';
            $path = '/agent/crm_deal.php?deal_id=' . $deal_id . ($status === 'Accepted' ? '#next-steps' : '');
            forkCrmHistoryAdd($deal_id, 'quote', "Quote $number $word", 0);
            $text = "Quote $number was $word" . ($who !== '' ? " by $who" : '') . " - deal " . $deal['deal_title'] . ($status === 'Declined' ? ". Note why it was lost." : ". Next steps are on the deal.");
            $agents = forkCrmNotifyAgents(forkCrmDealAgents($deal), $text, $path, $deal['deal_client_id'], $deal_id);
            $recipients = [];
            foreach ($agents as $agent) {
                $recipients[] = ['email' => $agent['email'], 'name' => $agent['name'], 'footnote' => 'You get this as the deal is yours in the CRM.'];
            }
            if ($recipients) {
                $subject = "Quote $number $word" . ($who !== '' ? " - $who" : '');
                emailQueue($recipients, $subject, [
                    'kind' => 'CRM', 'tone' => $status === 'Accepted' ? 'success' : 'warning',
                    'title' => $subject,
                    'intro' => [$status === 'Accepted'
                        ? "The quote was accepted. Move the deal to Won when it is signed off, then follow the next steps on the deal."
                        : "The quote was declined. Ask why, write the reason on the deal and move it to Lost - or make another offer."],
                    'summary' => array_filter(['Deal' => (string) $deal['deal_title'], 'For' => $who, 'Amount' => billingMoney($deal['deal_amount'])]),
                    'button' => ['label' => 'Open the deal', 'url' => "https://$config_base_url$path"],
                ]);
            }
            $told++;
        }
        if ($ids) {
            forkCrmSyncQuotes();
        }
        return $told;
    } catch (Throwable $e) {
        return 0;
    }
}

/* ---------- a deal was won or lost ---------- */

/**
 * A deal has just entered $stage. Won or lost: its open follow-up tasks are closed (nothing is left to
 * chase), and a task is planned for later - a check-in some days after the win, a recontact some days
 * after the loss (the days are CRM settings; 0 means none). A win also tells the other CRM agents. A deal
 * put back in an open stage loses its check-in and recontact tasks. Returns the sentences for the history.
 */
function forkCrmAfterClose(array $deal, array $stage, $user_id) {
    global $mysqli;
    $id = intval($deal['deal_id']);
    $kind = $stage['stage_kind'];
    $notes = [];
    if ($kind === 'open') {
        mysqli_query($mysqli, "UPDATE fork_crm_activities SET activity_done_at = NOW() WHERE activity_deal_id = $id AND activity_auto = 2 AND activity_done_at IS NULL");
        return $notes;
    }
    mysqli_query($mysqli, "UPDATE fork_crm_activities SET activity_done_at = NOW() WHERE activity_deal_id = $id AND activity_auto IN (1, 2) AND activity_done_at IS NULL");
    if (mysqli_affected_rows($mysqli) > 0) {
        $notes[] = 'follow-up tasks closed';
    }
    $who = forkCrmDealWho($deal);
    $with = $who !== '' ? $who : 'the client';
    $assignee = intval($deal['deal_owner_id'] ?? 0) ?: intval($user_id);
    if ($kind === 'won') {
        $days = forkCrmCloseDaysClean(forkCrmSetting('won_checkin_days'));
        $text = "Check in with $with: is everything running well since the start? Ask what they need and whether there is more to do together.";
    } else {
        $days = forkCrmCloseDaysClean(forkCrmSetting('lost_recontact_days'));
        $reason = trim((string) ($deal['deal_lost_reason'] ?? ''));
        $text = "Recontact $with: the deal " . $deal['deal_title'] . " was lost" . ($reason !== '' ? " ($reason)" : '') . ". Ask whether the situation changed.";
    }
    if ($days > 0) {
        $due = forkCrmFollowUpDate(date('Y-m-d'), $days);
        mysqli_query($mysqli, "INSERT INTO fork_crm_activities SET activity_deal_id = $id, activity_type = 'task', activity_text = '" . escapeSql($text) . "', activity_due = '$due',
            activity_user_id = $assignee, activity_created_by = " . intval($user_id) . ", activity_auto = 2");
        $notes[] = ($kind === 'won' ? 'check-in' : 'recontact') . " task planned for $due";
    }
    if ($kind === 'won' && forkCrmSetting('won_notify_team') === '1') {
        $path = '/agent/crm_deal.php?deal_id=' . $id . '#next-steps';
        forkCrmNotifyAgents(forkCrmAgents(), "Deal won: " . $deal['deal_title'] . ($who !== '' ? " ($who)" : '') . " - " . billingMoney($deal['deal_amount'] ?? 0) . ". A new client starts.", $path, $deal['deal_client_id'] ?? 0, $id);
    }
    if ($notes) {
        forkCrmHistoryAdd($id, 'auto', ucfirst(implode('; ', $notes)), 0);
    }
    return $notes;
}

/* ---------- next steps of a won deal ---------- */

/** What an agent said about each step of a deal: key => 'done' | 'skip'. */
function forkCrmStepsStored($deal_id) {
    global $mysqli;
    forkCrmEnsure();
    $out = [];
    $result = mysqli_query($mysqli, "SELECT step_key, step_state FROM fork_crm_deal_steps WHERE step_deal_id = " . intval($deal_id));
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $out[$row['step_key']] = $row['step_state'];
    }
    return $out;
}

/** The plain facts the steps are worked out from (see forkCrmStepState()). */
function forkCrmStepFacts(array $deal) {
    global $mysqli;
    $id = intval($deal['deal_id']);
    $accepted = 0;
    $invoiced = 0;
    foreach (forkCrmDealQuotes($id) as $quote) {
        $accepted += $quote['quote_status'] === 'Accepted' ? 1 : 0;
        $invoiced += $quote['quote_status'] === 'Invoiced' ? 1 : 0;
    }
    $client_id = intval($deal['deal_client_id']);
    $closed = (string) ($deal['deal_closed_at'] ?? '');
    $emailed = false;
    if ($closed !== '') {
        $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM fork_crm_activities WHERE activity_deal_id = $id AND activity_type = 'email' AND activity_created_at >= '" . escapeSql($closed) . "'"));
        $emailed = $row && intval($row[0]) > 0;
    }
    return [
        'client' => $client_id > 0 && intval($deal['client_lead'] ?? 1) === 0,
        'accepted' => $accepted,
        'invoiced' => $invoiced,
        'monthly' => floatval($deal['deal_monthly'] ?? 0),
        'onboarding' => $client_id > 0 && function_exists('clientOnboardingRow') && clientOnboardingRow($client_id) !== null,
        'emailed' => $emailed,
    ];
}

/** The steps of a deal: key => [label, state (done/todo/na), stored (done/skip/'')]. */
function forkCrmDealSteps(array $deal) {
    $facts = forkCrmStepFacts($deal);
    $stored = forkCrmStepsStored($deal['deal_id']);
    $out = [];
    foreach (forkCrmStepLabels() as $key => $label) {
        $out[$key] = ['label' => $label, 'state' => forkCrmStepState($key, $facts, $stored[$key] ?? ''), 'stored' => $stored[$key] ?? ''];
    }
    return $out;
}

/** An agent says a step is done, not needed, or back to what the deal shows ($state 'reset'). False for an unknown step. */
function forkCrmStepSet($deal_id, $key, $state, $user_id) {
    global $mysqli;
    if (!isset(forkCrmStepLabels()[$key]) || !in_array($state, ['done', 'skip', 'reset'], true)) {
        return false;
    }
    forkCrmEnsure();
    $deal_id = intval($deal_id);
    $key = escapeSql($key);
    if ($state === 'reset') {
        mysqli_query($mysqli, "DELETE FROM fork_crm_deal_steps WHERE step_deal_id = $deal_id AND step_key = '$key'");
    } else {
        mysqli_query($mysqli, "REPLACE INTO fork_crm_deal_steps SET step_deal_id = $deal_id, step_key = '$key', step_state = '$state', step_user_id = " . intval($user_id));
    }
    return true;
}

/* ---------- a deal was lost ---------- */

/**
 * The quotes of a lost deal that are still waiting for an answer (sent or viewed) are marked declined, so
 * the client no longer sees them as open. Returns how many.
 */
function forkCrmQuotesDecline(array $deal, $by) {
    global $mysqli;
    $count = 0;
    foreach (forkCrmDealQuotes($deal['deal_id']) as $quote) {
        if (!in_array($quote['quote_status'], ['Sent', 'Viewed'], true)) {
            continue;
        }
        mysqli_query($mysqli, "UPDATE quotes SET quote_status = 'Declined' WHERE quote_id = " . intval($quote['quote_id']) . " AND quote_status IN ('Sent', 'Viewed')");
        if (mysqli_affected_rows($mysqli) === 1) {
            billingLog('quote', $quote['quote_id'], 'Declined', "Declined by $by - the deal was lost");
            $count++;
        }
    }
    return $count;
}

/* ---------- emails to prefill ---------- */

/**
 * The subject and text of the welcome email ('welcome') or the courteous goodbye ('lost') to prefill in
 * the deal's email form, in the client's language. [subject, body].
 */
function forkCrmMailPrefill($kind, array $deal) {
    $name = trim((string) ($deal['deal_contact'] ?? ''));
    $company = trim((string) forkCrmDealWho($deal));
    $params = ['{name}' => $name !== '' ? $name : $company, '{company}' => $company];
    return i18n_with(client_language(), function () use ($kind, $params) {
        $hello = t('Hello {name},', $params);
        if ($kind === 'welcome') {
            return [t('Welcome aboard'), implode("\n\n", [$hello,
                t('Thank you for your trust. We are happy to start working with {company}.', $params),
                t('We will get in touch shortly to set everything up. Do not hesitate to ask if you have any question.')])];
        }
        return [t('Thank you for your time'), implode("\n\n", [$hello,
            t('Thank you for the time you gave us. We understand that {company} goes another way for now.', $params),
            t('If your needs change, we will gladly start again.')])];
    });
}
