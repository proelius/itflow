<?php

/*
 * One prepaid hours report - the blocks of hours and the tickets that took them, as the client will
 * read them, with the introduction, the conclusion and each ticket's wording to change. The numbers
 * are fixed. Send it to the client as a PDF, or download it. Once sent it is locked, except to an
 * admin. The work is in functions/fork_hours_report.php; the handlers are agent/post/hours_report.php.
 */

require_once "includes/inc_all_client.php";

enforceUserPermission('module_financial');

$report = forkHoursReportRow(intval($_GET['report_id'] ?? 0));
if (!$report || intval($report['report_client_id']) !== intval($client_id)) {
    flashAlert("That report is gone.", 'error');
    redirect("hours_reports.php?client_id=$client_id");
}
$data = $report['data'];
$sent = $report['report_status'] === 'sent';
$can_edit = lookupUserPermission('module_financial') >= 2 && (!$sent || $session_is_admin);
$can_send = lookupUserPermission('module_financial') >= 2 && (!$sent || $session_is_admin);
$recipients = billingEmailRecipients($client_id);

?>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="report_id" value="<?= intval($report['report_id']) ?>">

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-file-alt me-2"></i>Prepaid hours report - <?= escapeHtml(forkHoursReportTitle($report['report_start'])) ?></h3>
            <div class="card-tools">
                <a class="btn btn-secondary" href="hours_reports.php?client_id=<?= $client_id ?>"><i class="fas fa-arrow-left me-2"></i>Reports</a>
                <a class="btn btn-secondary" href="post.php?hours_report_pdf=<?= intval($report['report_id']) ?>"><i class="fas fa-file-pdf me-2"></i>Download PDF</a>
            </div>
        </div>
        <div class="card-body">
            <div class="mb-3 text-secondary">
                <?= escapeHtml($report['client_name']) ?> &middot; <?= escapeHtml($report['report_start']) ?> to <?= escapeHtml($report['report_end']) ?> &middot;
                <?php if ($sent) { ?>
                    <span class="badge bg-success">Sent</span> <?= escapeHtml($report['report_sent_at']) ?> to <?= escapeHtml((string) $report['report_sent_to']) ?>
                <?php } else { ?>
                    <span class="badge bg-warning text-dark">Draft - not sent yet</span>
                <?php } ?>
            </div>
            <?php if ($sent && !$session_is_admin) { ?>
                <div class="alert alert-info">This report was sent to the client, so it can no longer be changed - only an admin can.</div>
            <?php } elseif ($sent) { ?>
                <div class="alert alert-warning">This report was sent to the client. You can change it as an admin, and send it again.</div>
            <?php } ?>

            <div class="mb-3">
                <label class="form-label fw-semibold">Introduction</label>
                <textarea class="form-control" name="intro" rows="2" maxlength="2000" <?= $can_edit ? '' : 'readonly' ?>><?= escapeHtml($report['report_intro']) ?></textarea>
            </div>

            <h5>Blocks of hours</h5>
            <div class="table-responsive mb-4">
                <table class="table table-sm">
                    <thead><tr><th>Hours</th><th>Contract</th><th class="text-end">Prepaid</th><th class="text-end">Used this quarter</th><th class="text-end">Left</th><th class="text-end">Expires</th></tr></thead>
                    <tbody>
                    <?php foreach ($data['blocks'] as $block) { ?>
                        <tr>
                            <td><?= escapeHtml(forkHoursReportBlockName($block)) ?></td>
                            <td><?= escapeHtml($block['contract']) ?></td>
                            <td class="text-end"><?= escapeHtml(contractHoursText($block['hours'])) ?></td>
                            <td class="text-end"><?= escapeHtml(contractHoursText($block['period'])) ?></td>
                            <td class="text-end fw-semibold"><?= escapeHtml(contractHoursText($block['left'])) ?><?= $block['lapsed'] ? ' <span class="text-secondary">(ran out)</span>' : '' ?></td>
                            <td class="text-end"><?= $block['expires'] !== null ? escapeHtml($block['expires']) : '-' ?></td>
                        </tr>
                    <?php } ?>
                    <?php if (!$data['blocks']) { ?>
                        <tr><td colspan="6" class="text-secondary text-center">No prepaid hours this quarter.</td></tr>
                    <?php } ?>
                    </tbody>
                    <tfoot><tr class="fw-semibold"><td colspan="3" class="text-end">Total</td><td class="text-end"><?= escapeHtml(contractHoursText($data['used'])) ?></td><td class="text-end"><?= escapeHtml(contractHoursText($data['left'])) ?></td><td></td></tr></tfoot>
                </table>
            </div>

            <h5>Tickets</h5>
            <div class="table-responsive mb-4">
                <table class="table table-sm align-middle">
                    <thead><tr><th style="width:9rem">Date</th><th style="width:8rem">Ticket</th><th>Description (the client reads this)</th><th class="text-end" style="width:7rem">Hours</th></tr></thead>
                    <tbody>
                    <?php foreach ($data['lines'] as $i => $line) { ?>
                        <tr>
                            <td><?= escapeHtml($line['date']) ?></td>
                            <td><?= escapeHtml($line['reference']) ?></td>
                            <td><input type="text" class="form-control form-control-sm" name="label[<?= $i ?>]" maxlength="250" value="<?= escapeHtml($line['label']) ?>" <?= $can_edit ? '' : 'readonly' ?>></td>
                            <td class="text-end"><?= escapeHtml(contractHoursText($line['hours'])) ?></td>
                        </tr>
                    <?php } ?>
                    <?php if (!$data['lines']) { ?>
                        <tr><td colspan="4" class="text-secondary text-center">No ticket drew hours this quarter.</td></tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Conclusion</label>
                <textarea class="form-control" name="conclusion" rows="2" maxlength="2000" <?= $can_edit ? '' : 'readonly' ?>><?= escapeHtml($report['report_conclusion']) ?></textarea>
            </div>
            <div class="text-secondary small">
                The figures come from ITFlow and cannot be changed. The report is written in the client's language; the texts above were made in it.
                <?= $recipients ? 'It goes to ' . escapeHtml(implode(', ', array_column($recipients, 'email'))) . '.' : '<span class="text-danger">The client has no contact that gets its invoices, with an email address - nobody to send it to.</span>' ?>
            </div>
        </div>
        <?php if ($can_edit || $can_send) { ?>
            <div class="card-footer d-flex flex-wrap gap-2">
                <?php if ($can_edit) { ?>
                    <button type="submit" name="save_hours_report" value="1" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save</button>
                <?php } ?>
                <?php if ($can_send && $recipients) { ?>
                    <button type="submit" name="send_hours_report" value="1" class="btn btn-success" onclick="return confirm('Send this report to the client now? Once sent, only an admin can change it.');"><i class="fas fa-paper-plane me-2"></i><?= $sent ? 'Send again' : 'Send to the client' ?></button>
                <?php } ?>
                <?php if ($session_is_admin) { ?>
                    <button type="submit" name="rebuild_hours_report" value="1" class="btn btn-outline-danger ms-auto" onclick="return confirm('Make this report again from what is in ITFlow now? Your edits and the sent mark will be lost.');"><i class="fas fa-redo me-2"></i>Make it again</button>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</form>

<?php require_once "../includes/footer.php";
