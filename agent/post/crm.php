<?php

/*
 * ITFlow - CRM (functions/fork_crm.php): deals, their activities and tasks, and the stages of the
 * pipeline. Agents with the Sales module: level 1 reads, 2 makes and changes, 3 archives deals and
 * changes the stages. A deal tied to a client is reached only by an agent who may reach that client.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** A deal the agent may use. */
function crmPostDeal($deal_id, $level = 2) {
    forkCrmEnforce($level);
    $deal = forkCrmDeal($deal_id);
    if (!$deal) {
        flashAlert("That deal is gone.", 'error');
        redirect('crm.php');
    }
    if (intval($deal['deal_client_id']) > 0) {
        enforceClientAccess(intval($deal['deal_client_id']));
    }
    return $deal;
}

/** An activity of a deal the agent may use. */
function crmPostActivity($activity_id, $level = 2) {
    forkCrmEnforce($level);
    $activity = forkCrmActivity($activity_id);
    if (!$activity) {
        flashAlert("That activity is gone.", 'error');
        redirect('crm.php');
    }
    $deal = crmPostDeal($activity['activity_deal_id'], $level);
    return [$activity, $deal];
}

/** The deal fields from the form. */
function crmPostDealData() {
    return [
        'title' => $_POST['title'] ?? '',
        'client_id' => $_POST['client_id'] ?? 0,
        'prospect' => $_POST['prospect'] ?? '',
        'contact' => $_POST['contact'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'amount' => $_POST['amount'] ?? 0,
        'probability' => $_POST['probability'] ?? 0,
        'expected_close' => $_POST['expected_close'] ?? '',
        'source' => $_POST['source'] ?? '',
        'owner_id' => $_POST['owner_id'] ?? 0,
        'note' => $_POST['note'] ?? '',
    ];
}

if (isset($_POST['add_crm_deal'])) {

    validateCSRFToken();
    forkCrmEnforce(2);

    $data = crmPostDealData();
    $client_id = intval($data['client_id']);
    if ($client_id > 0) {
        enforceClientAccess($client_id);
    }
    $deal_id = forkCrmDealSave(0, $data, intval($_POST['stage_id'] ?? 0), $session_user_id);
    if (!$deal_id) {
        flashAlert("The deal needs a title.", 'error');
        redirect();
    }
    logAudit("CRM", "Create", "$session_name made the deal " . escapeSql(forkCrmTextClean($data['title'], 200)), $client_id, $deal_id);
    flashAlert("Deal <strong>" . escapeHtml(forkCrmTextClean($data['title'], 200)) . "</strong> made");
    redirect("crm_deal.php?deal_id=$deal_id");

}

if (isset($_POST['edit_crm_deal'])) {

    validateCSRFToken();
    $deal = crmPostDeal($_POST['deal_id'] ?? 0, 2);

    $data = crmPostDealData();
    $client_id = intval($data['client_id']);
    if ($client_id > 0) {
        enforceClientAccess($client_id);
    }
    if (!forkCrmDealSave($deal['deal_id'], $data)) {
        flashAlert("The deal needs a title.", 'error');
        redirect();
    }
    logAudit("CRM", "Edit", "$session_name changed the deal " . escapeSql($deal['deal_title']), intval($deal['deal_client_id']), intval($deal['deal_id']));
    flashAlert("Deal saved");
    redirect();

}

if (isset($_POST['move_crm_deal'])) {

    validateCSRFToken();
    $deal = crmPostDeal($_POST['deal_id'] ?? 0, 2);

    $stage = forkCrmStage($_POST['stage_id'] ?? 0);
    $error = forkCrmDealMove($deal, $_POST['stage_id'] ?? 0, $_POST['lost_reason'] ?? '');
    if ($error !== '') {
        flashAlert($error, 'error');
        redirect();
    }
    logAudit("CRM", "Edit", "$session_name moved the deal " . escapeSql($deal['deal_title']) . " to " . escapeSql($stage['stage_name']), intval($deal['deal_client_id']), intval($deal['deal_id']));
    $message = "Deal moved to <strong>" . escapeHtml($stage['stage_name']) . "</strong>";
    // A deal won with a lead (a prospect in the contacts) is the moment to make it a client
    if ($stage['stage_kind'] === 'won' && intval($deal['deal_client_id']) > 0 && intval($deal['client_lead']) === 1 && lookupUserPermission('module_client') >= 2) {
        $message .= ' - <a href="' . escapeHtml(clientOnboardingUrl($deal['deal_client_id'], 'company')) . '">make ' . escapeHtml($deal['client_name']) . ' a client</a>';
    }
    flashAlert($message);
    redirect();

}

if (isset($_POST['archive_crm_deal'])) {

    validateCSRFToken();
    $deal = crmPostDeal($_POST['deal_id'] ?? 0, 3);

    forkCrmDealArchive($deal['deal_id']);
    logAudit("CRM", "Archive", "$session_name archived the deal " . escapeSql($deal['deal_title']), intval($deal['deal_client_id']), intval($deal['deal_id']));
    flashAlert("Deal archived");
    redirect('crm.php');

}

if (isset($_POST['add_crm_activity'])) {

    validateCSRFToken();
    $deal = crmPostDeal($_POST['deal_id'] ?? 0, 2);

    if (!forkCrmActivityAdd($deal['deal_id'], $_POST['type'] ?? '', $_POST['text'] ?? '', $_POST['due'] ?? '', $_POST['assignee'] ?? 0, $session_user_id)) {
        flashAlert("Write something first.", 'error');
        redirect();
    }
    logAudit("CRM", "Create", "$session_name added an activity to the deal " . escapeSql($deal['deal_title']), intval($deal['deal_client_id']), intval($deal['deal_id']));
    flashAlert("Activity added");
    redirect();

}

if (isset($_POST['done_crm_activity'])) {

    validateCSRFToken();
    [$activity, $deal] = crmPostActivity($_POST['activity_id'] ?? 0, 2);

    forkCrmActivityDone($activity['activity_id'], empty($_POST['reopen']));
    logAudit("CRM", "Edit", "$session_name " . (empty($_POST['reopen']) ? "finished" : "reopened") . " a task of the deal " . escapeSql($deal['deal_title']), intval($deal['deal_client_id']), intval($deal['deal_id']));
    flashAlert(empty($_POST['reopen']) ? "Task done" : "Task open again");
    redirect();

}

if (isset($_POST['delete_crm_activity'])) {

    validateCSRFToken();
    [$activity, $deal] = crmPostActivity($_POST['activity_id'] ?? 0, 2);

    forkCrmActivityDelete($activity['activity_id']);
    logAudit("CRM", "Delete", "$session_name deleted an activity of the deal " . escapeSql($deal['deal_title']), intval($deal['deal_client_id']), intval($deal['deal_id']));
    flashAlert("Activity deleted");
    redirect();

}

if (isset($_POST['add_crm_stage'])) {

    validateCSRFToken();
    forkCrmEnforce(3);

    $stage_id = forkCrmStageAdd($_POST['name'] ?? '', $_POST['kind'] ?? '', $_POST['probability'] ?? 0);
    if (!$stage_id) {
        flashAlert("A stage needs a name.", 'error');
        redirect();
    }
    logAudit("CRM", "Create", "$session_name added the pipeline stage " . escapeSql(forkCrmTextClean($_POST['name'] ?? '', 100)), 0, $stage_id);
    flashAlert("Stage added");
    redirect();

}

if (isset($_POST['edit_crm_stage'])) {

    validateCSRFToken();
    forkCrmEnforce(3);

    $error = forkCrmStageSave($_POST['stage_id'] ?? 0, $_POST['name'] ?? '', $_POST['kind'] ?? '', $_POST['probability'] ?? 0);
    if ($error !== '') {
        flashAlert($error, 'error');
        redirect();
    }
    logAudit("CRM", "Edit", "$session_name changed the pipeline stage " . escapeSql(forkCrmTextClean($_POST['name'] ?? '', 100)), 0, intval($_POST['stage_id'] ?? 0));
    flashAlert("Stage saved");
    redirect();

}

if (isset($_POST['shift_crm_stage'])) {

    validateCSRFToken();
    forkCrmEnforce(3);

    forkCrmStageShift($_POST['stage_id'] ?? 0, intval($_POST['shift_crm_stage']));
    redirect();

}

if (isset($_POST['delete_crm_stage'])) {

    validateCSRFToken();
    forkCrmEnforce(3);

    $error = forkCrmStageDelete($_POST['stage_id'] ?? 0);
    if ($error !== '') {
        flashAlert($error, 'error');
        redirect();
    }
    logAudit("CRM", "Delete", "$session_name deleted a pipeline stage", 0, intval($_POST['stage_id'] ?? 0));
    flashAlert("Stage deleted");
    redirect();

}

if (isset($_POST['link_crm_quote'])) {

    validateCSRFToken();
    $deal = crmPostDeal($_POST['deal_id'] ?? 0, 2);

    $error = forkCrmDealQuoteAdd($deal, $_POST['quote_id'] ?? 0);
    if ($error !== '') {
        flashAlert($error, 'error');
        redirect();
    }
    logAudit("CRM", "Edit", "$session_name linked a quote to the deal " . escapeSql($deal['deal_title']), intval($deal['deal_client_id']), intval($deal['deal_id']));
    flashAlert("Quote linked");
    redirect();

}

if (isset($_POST['unlink_crm_quote'])) {

    validateCSRFToken();
    $deal = crmPostDeal($_POST['deal_id'] ?? 0, 2);

    forkCrmDealQuoteRemove($deal['deal_id'], $_POST['quote_id'] ?? 0);
    logAudit("CRM", "Edit", "$session_name took a quote off the deal " . escapeSql($deal['deal_title']), intval($deal['deal_client_id']), intval($deal['deal_id']));
    flashAlert("Quote taken off the deal");
    redirect();

}

if (isset($_POST['amount_from_crm_quotes'])) {

    validateCSRFToken();
    $deal = crmPostDeal($_POST['deal_id'] ?? 0, 2);

    $total = forkCrmQuotesTotal(forkCrmDealQuotes($deal['deal_id']), $session_company_currency);
    forkCrmDealAmountSet($deal['deal_id'], $total);
    logAudit("CRM", "Edit", "$session_name set the amount of the deal " . escapeSql($deal['deal_title']) . " from its quotes", intval($deal['deal_client_id']), intval($deal['deal_id']));
    flashAlert("Amount set to <strong>" . escapeHtml(billingMoney($total)) . "</strong>, the total of the quotes still in play");
    redirect();

}

if (isset($_POST['make_crm_prospect'])) {

    validateCSRFToken();
    $deal = crmPostDeal($_POST['deal_id'] ?? 0, 2);
    enforceUserPermission('module_client', 2);

    [$client_id, $state, $error] = forkCrmProspectCreate($deal);
    if ($error !== '') {
        flashAlert($error, 'error');
        redirect();
    }
    if ($state === 'created') {
        logAudit("Client", "Create", "$session_name made " . escapeSql(forkCrmTextClean($deal['deal_prospect'], 200)) . " a prospect from the CRM deal " . escapeSql($deal['deal_title']), $client_id, $client_id);
    }
    logAudit("CRM", "Edit", "$session_name tied the deal " . escapeSql($deal['deal_title']) . " to a " . ($state === 'created' ? 'new prospect' : 'client already in ITFlow'), $client_id, intval($deal['deal_id']));
    // Prospect and client in one go: on to the onboarding, whose first step makes the lead a client
    if (!empty($_POST['onboard']) && clientOnboardingReady()) {
        flashAlert(($state === 'created' ? "Prospect made in ITFlow" : "Tied to the client already in ITFlow") . " - now make it a client");
        redirect(clientOnboardingUrl($client_id, 'company'));
    }
    flashAlert($state === 'created' ? "<strong>" . escapeHtml($deal['deal_prospect']) . "</strong> is now a prospect in ITFlow's contacts" : "A client named <strong>" . escapeHtml($deal['deal_prospect']) . "</strong> already exists - the deal is tied to it");
    redirect();

}
