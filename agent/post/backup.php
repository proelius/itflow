<?php

/*
 * ITFlow - backup checks (functions/fork_backup.php): give a client the service and its servers, enter a
 * client's check for a week, make a client's report for a period, change its comment, open it as PDF or Word.
 * Checks are for agents with the Support module (level 2); reports for agents with the Financial module.
 * Nothing here sends anything to a client.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** A client the agent may work on, with the Support module at $level. */
function backupPostClient($level = 2) {
    enforceUserPermission('module_support', $level);
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
    $client_id = backupPostClient();
    if (!forkBackupClientAdd($client_id, $_POST['frequency'] ?? 'weekly', $_POST['anchor'] ?? null)) {
        flashAlert("That client could not be added - it may have the service already.", 'error');
        redirect();
    }
    logAudit("Backup check", "Create", "$session_name gave the backup check service to the client", $client_id, $client_id);
    flashAlert("Client added - now add the servers to check");
    redirect("backup_client.php?client_id=$client_id");

}

if (isset($_POST['backup_client_save'])) {

    validateCSRFToken();
    $client_id = backupPostClient();
    if (!forkBackupClientSave($client_id, $_POST['frequency'] ?? 'weekly', $_POST['anchor'] ?? '', !empty($_POST['active']))) {
        flashAlert("Give the Monday the service starts, as a date.", 'error');
        redirect();
    }
    logAudit("Backup check", "Edit", "$session_name changed the backup check service of the client", $client_id, $client_id);
    flashAlert("Saved");
    redirect();

}

if (isset($_POST['backup_client_remove'])) {

    validateCSRFToken();
    $client_id = backupPostClient();
    forkBackupClientRemove($client_id);
    logAudit("Backup check", "Delete", "$session_name took the backup check service away from the client", $client_id, $client_id);
    flashAlert("Service removed - what was entered is kept");
    redirect("backup_checks.php?tab=clients");

}

if (isset($_POST['backup_server_add'])) {

    validateCSRFToken();
    $client_id = backupPostClient();
    if (!forkBackupServerAdd($client_id, $_POST['asset_id'] ?? 0, $_POST['name'] ?? '')) {
        flashAlert("That server could not be added - choose an asset or give a name, and not one already there.", 'error');
        redirect();
    }
    flashAlert("Server added");
    redirect();

}

if (isset($_POST['backup_server_remove'])) {

    validateCSRFToken();
    $client_id = backupPostClient();
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
    flashAlert("Check saved for the week of <strong>" . escapeHtml($week) . "</strong>");
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
    flashAlert("Report made - open it as PDF or Word below");
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

if (isset($_GET['backup_report_docx'])) {

    validateCSRFToken();
    $report = backupPostReport($_GET['backup_report_docx']);
    $file = forkBackupDocx($report);
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $file['name'] . '"');
    header('Content-Length: ' . strlen($file['data']));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo $file['data'];
    exit;

}
