<?php

/*
 * One backup check report (functions/fork_backup.php): what the client will read, the comment to change, and the PDF.
 * The handlers are agent/post/backup.php.
 */

require_once "includes/inc_all_client.php";
require_once "includes/backup_common.php";

enforceUserPermission('module_financial');

$report = forkBackupReportRow(intval($_GET['report_id'] ?? 0));
if (!$report || intval($report['br_client_id']) !== intval($client_id)) {
    flashAlert("That report is gone.", 'error');
    redirect("backup_reports.php?client_id=$client_id");
}
$can_edit = lookupUserPermission('module_financial') >= 2;
$counts = $report['data']['counts'];
$token = escapeHtml($_SESSION['csrf_token']);

?>

<?= backupClientTabs($client_id, 'reports') ?>

<form action="post.php" method="post">
    <?= backupCsrf() ?>
    <input type="hidden" name="report_id" value="<?= intval($report['br_id']) ?>">
    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-file-alt me-2"></i>Backup check report - <?= escapeHtml($report['br_start']) ?> to <?= escapeHtml($report['br_end']) ?></h3>
            <div class="card-tools">
                <a class="btn btn-secondary" href="backup_reports.php?client_id=<?= $client_id ?>"><i class="fas fa-arrow-left me-2"></i>Reports</a>
                <a class="btn btn-secondary" href="post.php?backup_report_pdf=<?= intval($report['br_id']) ?>&amp;csrf_token=<?= $token ?>" target="_blank"><i class="fas fa-file-pdf me-2"></i>PDF</a>
                <?php if ($can_edit) { ?><button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/backup/backup_report_email.php?report_id=<?= intval($report['br_id']) ?>"><i class="fas fa-paper-plane me-2"></i>Send by email</button><?php } ?>
            </div>
        </div>
        <div class="card-body">
            <div class="mb-3 text-secondary"><?= escapeHtml($report['client_name']) ?> &middot; <?= intval($report['data']['total']) ?> checks:
                <?= intval($counts['green']) ?> green, <?= intval($counts['yellow']) ?> yellow, <?= intval($counts['red']) ?> red. Nothing is sent by itself - send it by email from here, or download the PDF.</div>
            <div class="mb-3"><label class="form-label fw-semibold">Comment</label>
                <textarea class="form-control" name="comment" rows="4" maxlength="3000" <?= $can_edit ? '' : 'readonly' ?>><?= escapeHtml($report['br_comment']) ?></textarea></div>
            <div class="table-responsive"><table class="table table-sm align-middle">
                <thead><tr><th style="width:6rem">Week</th><th style="width:10rem">Colour</th><th>Detail (the client reads this)</th></tr></thead>
                <tbody>
                <?php foreach ($report['data']['months'] as $month => $weeks) { ?>
                    <tr class="table-light"><td colspan="3" class="fw-semibold"><?= escapeHtml(date('F Y', strtotime($month . '-01 12:00:00'))) ?></td></tr>
                    <?php foreach ($weeks as $week) {
                        $auto = i18n_with(client_language(), fn() => forkBackupDetailText($week['detail']));
                        $text = isset($week['text']) ? $week['text'] : ($auto === '/' ? '' : $auto);
                        $changed = $week['state'] !== $week['orig_state'] || isset($week['text']); ?>
                        <tr>
                            <td><?= escapeHtml($week['week']) ?></td>
                            <td>
                                <select class="form-select form-select-sm" name="week[<?= escapeHtml($week['week']) ?>][state]" <?= $can_edit ? '' : 'disabled' ?>><?= backupOptions(forkBackupStates(), $week['state']) ?></select>
                                <?php if ($week['state'] !== $week['orig_state']) { ?><div class="text-secondary small">entered: <?= escapeHtml(strtolower(forkBackupStates()[$week['orig_state']])) ?></div><?php } ?>
                            </td>
                            <td>
                                <textarea class="form-control form-control-sm" name="week[<?= escapeHtml($week['week']) ?>][text]" rows="<?= max(1, min(4, substr_count($text, "\n") + 1)) ?>" maxlength="1000" placeholder="/" <?= $can_edit ? '' : 'readonly' ?>><?= escapeHtml($text) ?></textarea>
                                <?php if (isset($week['text']) && $auto !== '/') { ?><div class="text-secondary small">entered: <?= nl2br(escapeHtml($auto)) ?></div><?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
                </tbody>
            </table></div>
            <div class="text-secondary small">You can change the colour and the wording of a week for the client: the checks as the technicians entered them stay as they are, and "entered" shows what they put. The report is written in the client's language.</div>
        </div>
        <?php if ($can_edit) { ?>
            <div class="card-footer d-flex gap-2">
                <button type="submit" name="save_backup_report" value="1" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save the report</button>
                <button type="submit" name="reset_backup_report" value="1" class="btn btn-outline-secondary" onclick="return confirm('Put the weeks back to what the technicians entered? Your changes to the weeks go; the comment stays.')"><i class="fas fa-undo me-2"></i>Back to what was entered</button>
                <button type="submit" name="delete_backup_report" value="1" class="btn btn-outline-danger ms-auto" onclick="return confirm('Delete this report?')"><i class="fas fa-trash me-2"></i>Delete</button>
            </div>
        <?php } ?>
    </div>
</form>

<?php require_once "../includes/footer.php";
