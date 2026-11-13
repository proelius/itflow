<?php

/*
 * One backup check report (functions/fork_backup.php): what the client will read, the comment to change, and the PDF and
 * Word files. The handlers are agent/post/backup.php.
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
                <a class="btn btn-secondary" href="post.php?backup_report_docx=<?= intval($report['br_id']) ?>&amp;csrf_token=<?= $token ?>"><i class="fas fa-file-word me-2"></i>Word</a>
            </div>
        </div>
        <div class="card-body">
            <div class="mb-3 text-secondary"><?= escapeHtml($report['client_name']) ?> &middot; <?= intval($report['data']['total']) ?> checks:
                <?= intval($counts['green']) ?> green, <?= intval($counts['yellow']) ?> yellow, <?= intval($counts['red']) ?> red. Not sent anywhere - download it and send it yourself.</div>
            <div class="mb-3"><label class="form-label fw-semibold">Comment</label>
                <textarea class="form-control" name="comment" rows="4" maxlength="3000" <?= $can_edit ? '' : 'readonly' ?>><?= escapeHtml($report['br_comment']) ?></textarea></div>
            <div class="table-responsive"><table class="table table-sm">
                <thead><tr><th style="width:6rem">Week</th><th style="width:9rem">Colour</th><th>Detail (the client reads this)</th></tr></thead>
                <tbody>
                <?php foreach ($report['data']['months'] as $month => $weeks) { ?>
                    <tr class="table-light"><td colspan="3" class="fw-semibold"><?= escapeHtml(date('F Y', strtotime($month . '-01 12:00:00'))) ?></td></tr>
                    <?php foreach ($weeks as $week) { ?>
                        <tr>
                            <td><?= escapeHtml($week['week']) ?></td>
                            <td><?= backupStateBadge($week['state']) ?></td>
                            <td><?= $week['detail'] ? nl2br(escapeHtml(implode("\n", array_map(fn($d) => ($d['server'] !== '' ? $d['server'] . ': ' : '') . $d['comment'], $week['detail'])))) : '<span class="text-secondary">/</span>' ?></td>
                        </tr>
                    <?php } ?>
                <?php } ?>
                </tbody>
            </table></div>
            <div class="text-secondary small">The table comes from the checks entered and is fixed once the report is made. It is written in the client's language.</div>
        </div>
        <?php if ($can_edit) { ?>
            <div class="card-footer d-flex gap-2">
                <button type="submit" name="save_backup_report" value="1" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save the comment</button>
                <button type="submit" name="delete_backup_report" value="1" class="btn btn-outline-danger ms-auto" onclick="return confirm('Delete this report?')"><i class="fas fa-trash me-2"></i>Delete</button>
            </div>
        <?php } ?>
    </div>
</form>

<?php require_once "../includes/footer.php";
