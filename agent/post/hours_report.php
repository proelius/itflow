<?php

/*
 * ITFlow - the quarterly report of a client's prepaid hours (functions/fork_hours_report.php):
 * make it for the last civil quarter, change its wording, send it to the client as a PDF, open
 * its PDF in a tab. Made, changed and sent by agents with the Financial module; once sent, only an admin can
 * change it or make it again.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** A report the agent may use - checked against the client's access. */
function hoursReportPostRow($report_id, $level = 1) {
    enforceUserPermission('module_financial', $level);
    $report = forkHoursReportRow($report_id);
    if (!$report) {
        flashAlert("That report is gone.", 'error');
        redirect();
    }
    enforceClientAccess(intval($report['report_client_id']));
    return $report;
}

if (isset($_POST['generate_hours_report'])) {

    validateCSRFToken();
    enforceUserPermission('module_financial', 2);

    $client_id = intval($_POST['client_id'] ?? 0);
    enforceClientAccess($client_id);
    if (!contractHoursReady()) {
        flashAlert("Prepaid hours need the database update - run it from Maintenance > Update first.", 'error');
        redirect();
    }
    $period = forkHoursReportPeriod();
    $existing = forkHoursReportFor($client_id, $period['start']);
    if ($existing) {
        redirect("hours_report.php?client_id=$client_id&report_id=" . intval($existing['report_id']));
    }
    $report_id = forkHoursReportCreate($client_id, $period, $session_user_id);
    if (!$report_id) {
        flashAlert("The report could not be made.", 'error');
        redirect();
    }
    logAudit("Contract", "Create", "$session_name made the prepaid hours report for " . escapeSql(forkHoursReportTitle($period['start'])), $client_id, $client_id);
    flashAlert("Report made for <strong>" . escapeHtml(forkHoursReportTitle($period['start'])) . "</strong> - read it and change its wording before it is sent");
    redirect("hours_report.php?client_id=$client_id&report_id=$report_id");

}

if (isset($_POST['save_hours_report'])) {

    validateCSRFToken();
    $report = hoursReportPostRow($_POST['report_id'] ?? 0, 2);
    if ($report['report_status'] === 'sent' && !$session_is_admin) {
        flashAlert("This report was sent to the client - only an admin can change it.", 'error');
        redirect();
    }
    forkHoursReportSave($report, $_POST['intro'] ?? '', $_POST['conclusion'] ?? '', (array) ($_POST['label'] ?? []));
    logAudit("Contract", "Edit", "$session_name changed the prepaid hours report for " . escapeSql(forkHoursReportTitle($report['report_start'])), $report['report_client_id'], $report['report_client_id']);
    flashAlert("Report saved");
    redirect();

}

if (isset($_POST['rebuild_hours_report'])) {

    validateCSRFToken();
    $report = hoursReportPostRow($_POST['report_id'] ?? 0, 2);
    if (!$session_is_admin) {
        flashAlert("Only an admin can make a report again.", 'error');
        redirect();
    }
    forkHoursReportRebuild($report);
    logAudit("Contract", "Edit", "$session_name made the prepaid hours report for " . escapeSql(forkHoursReportTitle($report['report_start'])) . " again", $report['report_client_id'], $report['report_client_id']);
    flashAlert("Report made again from what is in ITFlow now - the edits and the sent mark are gone");
    redirect();

}

if (isset($_POST['send_hours_report'])) {

    validateCSRFToken();
    $report = hoursReportPostRow($_POST['report_id'] ?? 0, 2);
    if ($report['report_status'] === 'sent' && !$session_is_admin) {
        flashAlert("This report was already sent to the client.", 'error');
        redirect();
    }
    // What is on the page goes out - save it first, when it can be changed
    if (isset($_POST['intro'])) {
        forkHoursReportSave($report, $_POST['intro'], $_POST['conclusion'] ?? '', (array) ($_POST['label'] ?? []));
        $report = forkHoursReportRow($report['report_id']);
    }
    $sent = forkHoursReportSend($report, $session_user_id);
    if (!$sent) {
        flashAlert("Nobody to send it to - the client has no contact that gets its invoices, with an email address.", 'error');
        redirect();
    }
    logAudit("Contract", "Edit", "$session_name sent the prepaid hours report for " . escapeSql(forkHoursReportTitle($report['report_start'])) . " to " . escapeSql(implode(', ', $sent)), $report['report_client_id'], $report['report_client_id']);
    flashAlert("Report sent to <strong>" . escapeHtml(implode(', ', $sent)) . "</strong> - it can no longer be changed, except by an admin");
    redirect();

}

if (isset($_GET['hours_report_pdf'])) {

    validateCSRFToken();

    $report = hoursReportPostRow($_GET['hours_report_pdf']);
    billingPdfSend(forkHoursReportPdf($report));

}
