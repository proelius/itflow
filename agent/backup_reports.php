<?php

/*
 * A client's backup check reports (functions/fork_backup.php): make one for a period - the last civil quarter is
 * suggested - with the comment typed for it, and the ones made before. For agents with the Financial module.
 * Nothing is sent to the client from here. The handlers are agent/post/backup.php.
 */

require_once "includes/inc_all_client.php";
require_once "includes/backup_common.php";

enforceUserPermission('module_financial');

$can_edit = lookupUserPermission('module_financial') >= 2;
$period = forkBackupPeriod();
$reports = forkBackupReports($client_id);
$service = forkBackupClient($client_id);

?>

<?= backupClientTabs($client_id, 'reports') ?>

<div class="card card-dark">
    <div class="card-header py-2"><h3 class="card-title mt-2"><i class="fas fa-fw fa-file-alt me-2"></i>Backup check reports</h3></div>
    <?php if ($can_edit) { ?>
        <form action="post.php" method="post" class="card-body border-bottom">
            <?= backupCsrf() ?>
            <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <div class="row g-2 mb-2">
                <div class="col-md-3"><label class="form-label">From</label><input type="date" class="form-control" name="start" value="<?= $period['start'] ?>" required></div>
                <div class="col-md-3"><label class="form-label">To</label><input type="date" class="form-control" name="end" value="<?= $period['end'] ?>" required></div>
                <div class="col-md-6 text-secondary small align-self-end">The last civil quarter is suggested - change the dates for another period. The report lists the checks entered in it.</div>
            </div>
            <div class="mb-2"><label class="form-label">Comment <span class="text-danger">*</span> <span class="text-secondary">(how the backups are doing - the client reads it, so write it for this report)</span></label>
                <textarea class="form-control" name="comment" rows="4" maxlength="3000" required></textarea></div>
            <button type="submit" name="generate_backup_report" value="1" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Make the report</button>
        </form>
    <?php } ?>
    <?php if (!$reports) { ?>
        <div class="card-body text-secondary">No report made yet<?= $service ? '' : ' - and this client does not have the service' ?>.</div>
    <?php } else { ?>
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead class="text-dark"><tr><th>Period</th><th>Made</th><th class="text-center">Open</th></tr></thead>
            <tbody>
            <?php foreach ($reports as $report) { ?>
                <tr>
                    <td class="fw-semibold"><?= escapeHtml($report['br_start']) ?> &ndash; <?= escapeHtml($report['br_end']) ?></td>
                    <td><?= escapeHtml($report['br_created_at']) ?></td>
                    <td class="text-center text-nowrap">
                        <a class="btn btn-sm btn-secondary" href="backup_report.php?client_id=<?= $client_id ?>&report_id=<?= intval($report['br_id']) ?>" title="View"><i class="fas fa-eye"></i></a>
                        <a class="btn btn-sm btn-secondary" href="post.php?backup_report_pdf=<?= intval($report['br_id']) ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>" target="_blank" title="PDF"><i class="fas fa-file-pdf"></i></a>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table></div>
    <?php } ?>
</div>

<?php require_once "../includes/footer.php";
