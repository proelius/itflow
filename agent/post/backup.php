<?php

/*
 * ITFlow - backup checks (functions/fork_backup.php): give a client the service and its servers, enter a
 * client's check for a week, make a client's report for a period, change its comment, open it as PDF.
 * Checks are for agents with the Support module (level 2); reports for agents with the Financial module.
 * Nothing here sends anything to a client.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** A client the agent may work on, with the Support module at $level - the checks. */
function backupPostClient($level = 2) {
    enforceUserPermission('module_support', $level);
    $client_id = intval($_POST['client_id'] ?? $_GET['client_id'] ?? 0);
    enforceClientAccess($client_id);
    return $client_id;
}

/** A client the agent may work on, with the Financial module at $level - the service and its servers, like the reports. */
function backupPostService($level = 2) {
    enforceUserPermission('module_financial', $level);
    $client_id = intval($_POST['client_id'] ?? $_GET['client_id'] ?? 0);
    enforceClientAccess($client_id);
    return $client_id;
}

/** A report the agent may use - the Financial module, and the client's access. */
function backupPostReport($report_id, $level = 1) {
    enforceUserPermission('module_financial', $level);
    $report = forkBackupReportRow($report_id);
    if (!$report) {
        flashAlert("That report is gone.", 'error');
        redirect();
    }
    enforceClientAccess(intval($report['br_client_id']));
    return $report;
}

if (isset($_POST['backup_client_add'])) {

    validateCSRFToken();
    $client_id = backupPostService();
    if (!forkBackupClientAdd($client_id, $_POST['frequency'] ?? 'weekly', $_POST['anchor'] ?? null, !empty($_POST['autonomous']))) {
        flashAlert("That client could not be added - it may have the service already.", 'error');
        redirect();
    }
    logAudit("Backup check", "Create", "$session_name gave the backup check service to the client", $client_id, $client_id);
    flashAlert("Client added - now add the servers to check");
    redirect("backup_client.php?client_id=$client_id");

}

if (isset($_POST['backup_client_save'])) {

    validateCSRFToken();
    $client_id = backupPostService();
    if (!forkBackupClientSave($client_id, $_POST['frequency'] ?? 'weekly', $_POST['anchor'] ?? '', !empty($_POST['active']), !empty($_POST['autonomous']))) {
        flashAlert("Give the Monday the service starts, as a date.", 'error');
        redirect();
    }
    logAudit("Backup check", "Edit", "$session_name changed the backup check service of the client", $client_id, $client_id);
    flashAlert("Saved");
    redirect();

}

if (isset($_POST['backup_client_remove'])) {

    validateCSRFToken();
    $client_id = backupPostService();
    forkBackupClientArchive($client_id);
    logAudit("Backup check", "Archive", "$session_name archived the backup check service of the client", $client_id, $client_id);
    flashAlert("Service archived - what was entered is kept. Restore it, or delete it for good, from the client's backup checks", 'error');
    redirect("backup_checks.php?tab=clients");

}

if (isset($_POST['backup_client_restore'])) {

    validateCSRFToken();
    $client_id = backupPostService(2);
    forkBackupClientRestore($client_id);
    logAudit("Backup check", "Restore", "$session_name restored the backup check service of the client", $client_id, $client_id);
    flashAlert("Service restored");
    redirect("backup_client.php?client_id=$client_id");

}

if (isset($_POST['backup_client_delete'])) {

    validateCSRFToken();
    $client_id = backupPostService(3);
    $deleted = forkBackupClientDelete($client_id);
    if ($deleted === null) {
        flashAlert("Archive the service first - an active service cannot be deleted.", 'error');
        redirect();
    }
    logAudit("Backup check", "Delete", "$session_name deleted the backup checks of the client for good ({$deleted['checks']} checks, {$deleted['reports']} reports, {$deleted['servers']} servers)", $client_id, $client_id);
    flashAlert("Backup checks deleted for good - {$deleted['checks']} checks, {$deleted['reports']} reports, {$deleted['servers']} servers", 'error');
    redirect("backup_checks.php?tab=clients");

}

if (isset($_POST['backup_server_add'])) {

    validateCSRFToken();
    $client_id = backupPostService();
    if (!forkBackupServerAdd($client_id, $_POST['asset_id'] ?? 0, $_POST['name'] ?? '')) {
        flashAlert("That server could not be added - choose an asset or give a name, and not one already there.", 'error');
        redirect();
    }
    flashAlert("Server added");
    redirect();

}

if (isset($_POST['backup_server_remove'])) {

    validateCSRFToken();
    $client_id = backupPostService();
    forkBackupServerRemove($client_id, $_POST['server_id'] ?? 0);
    flashAlert("Server removed - what was entered for it is kept");
    redirect();

}

if (isset($_POST['save_backup_check'])) {

    validateCSRFToken();
    $client_id = backupPostClient();
    $week = forkBackupMonday($_POST['week'] ?? '');
    if ($week === null) {
        flashAlert("That week is not a date.", 'error');
        redirect();
    }
    [$check_id, $error] = forkBackupCheckSave($client_id, $week, $session_user_id, $_POST['ticket_id'] ?? 0, (array) ($_POST['line'] ?? []));
    if ($error !== '') {
        flashAlert($error, 'error');
        redirect();
    }
    logAudit("Backup check", "Edit", "$session_name entered the backup check of the week of $week", $client_id, $check_id);
    // Something failed and the technician may not act without the client's approval: a ticket is opened and linked
    $ticket = forkBackupTicketOpen($client_id, $week, $session_user_id);
    flashAlert("Check saved for the week of <strong>" . escapeHtml($week) . "</strong>" . ($ticket ? " - ticket <strong>" . escapeHtml($ticket['reference']) . "</strong> opened for the client's approval" : ''));
    redirect("backup_checks.php?week=$week");

}

if (isset($_POST['generate_backup_report'])) {

    validateCSRFToken();
    enforceUserPermission('module_financial', 2);
    $client_id = intval($_POST['client_id'] ?? 0);
    enforceClientAccess($client_id);
    [$report_id, $error] = forkBackupReportCreate($client_id, $_POST['start'] ?? '', $_POST['end'] ?? '', $_POST['comment'] ?? '', $session_user_id);
    if ($error !== '') {
        flashAlert($error, 'error');
        redirect();
    }
    logAudit("Backup check", "Create", "$session_name made the backup check report of " . escapeSql($_POST['start']) . " to " . escapeSql($_POST['end']), $client_id, $report_id);
    flashAlert("Report made - open it as PDF below");
    redirect("backup_report.php?client_id=$client_id&report_id=$report_id");

}

if (isset($_POST['save_backup_report'])) {

    validateCSRFToken();
    $report = backupPostReport($_POST['report_id'] ?? 0, 2);
    if (!forkBackupReportEditSave($report, $_POST['comment'] ?? '', (array) ($_POST['week'] ?? []))) {
        flashAlert("The comment cannot be empty.", 'error');
        redirect();
    }
    logAudit("Backup check", "Edit", "$session_name changed a backup check report (the checks as the technicians entered them are untouched)", $report['br_client_id'], $report['br_id']);
    flashAlert("Report saved - the checks entered by the technicians are untouched");
    redirect();

}

if (isset($_POST['email_backup_report'])) {

    validateCSRFToken();
    $report = backupPostReport($_POST['report_id'] ?? 0, 2);
    $client_id = intval($report['br_client_id']);
    $recipients = billingEmailRecipients($client_id, (array) ($_POST['contacts'] ?? []));
    if (!$recipients) {
        flashAlert(empty($_POST['contacts']) ? "Select at least one contact to send to" : "None of the selected contacts have a usable email address", 'error');
        redirect();
    }
    // The report as the client reads it, with the agent's own words, and the PDF attached - the same way a quote goes out
    [$subject, $email] = forkBackupReportEmail($report, ['note' => trim(mb_substr((string) ($_POST['note'] ?? ''), 0, 2000))]);
    $file = billingEmailPdf(forkBackupPdf($report));
    if (!$file) {
        flashAlert("The PDF could not be made, so nothing was sent.", 'error');
        redirect();
    }
    $sent = billingEmailQueue($recipients, $subject, $email, ['from' => 'invoice', 'attach' => [$file]]);
    foreach ($recipients as $recipient) {
        logAudit("Backup check", "Email", "$session_name emailed the backup check report of " . escapeSql($report['br_start']) . " to " . escapeSql($report['br_end']) . " to " . escapeSql($recipient['email']) . " with the PDF", $client_id, $report['br_id']);
    }
    flashAlert("Report queued to " . count($sent) . " " . (count($sent) === 1 ? "recipient" : "recipients"));
    redirect();

}

if (isset($_POST['reset_backup_report'])) {

    validateCSRFToken();
    $report = backupPostReport($_POST['report_id'] ?? 0, 2);
    forkBackupReportReset($report);
    logAudit("Backup check", "Edit", "$session_name made the weeks of a backup check report again from the checks entered", $report['br_client_id'], $report['br_id']);
    flashAlert("The weeks are back to what the technicians entered");
    redirect();

}

if (isset($_POST['delete_backup_report'])) {

    validateCSRFToken();
    $report = backupPostReport($_POST['report_id'] ?? 0, 2);
    forkBackupReportDelete($report['br_id']);
    logAudit("Backup check", "Delete", "$session_name deleted a backup check report", $report['br_client_id'], $report['br_id']);
    flashAlert("Report deleted");
    redirect("backup_reports.php?client_id=" . intval($report['br_client_id']));

}

if (isset($_GET['backup_report_pdf'])) {

    validateCSRFToken();
    $report = backupPostReport($_GET['backup_report_pdf']);
    billingPdfSend(forkBackupPdf($report));

}
