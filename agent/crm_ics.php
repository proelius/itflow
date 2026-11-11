<?php

/*
 * One planned CRM meeting, call or task as an iCalendar file (.ics) for the agent's calendar. Reading
 * only, nothing changes; the agent must be able to reach the deal. The file is made by
 * forkCrmIcs() in functions/fork_crm.php.
 */

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../functions.php";
require_once __DIR__ . "/../includes/check_login.php";

forkCrmEnforce(1);

$activity = forkCrmActivity($_GET['activity_id'] ?? 0);
$deal = $activity ? forkCrmDeal($activity['activity_deal_id']) : null;
if (!$activity || !$deal || forkCrmDateClean($activity['activity_due'] ?? '') === null) {
    flashAlert("That activity is gone, or has no date.", 'error');
    redirect('crm.php');
}
if (intval($deal['deal_client_id']) > 0) {
    enforceClientAccess(intval($deal['deal_client_id']));
}

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="crm-' . intval($activity['activity_id']) . '.ics"');
echo forkCrmIcs($activity, $deal, time(), (string) ($_SERVER['HTTP_HOST'] ?? 'itflow'));
