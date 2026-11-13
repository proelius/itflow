<?php

/*
 * Backup checks (functions/fork_backup.php): the clients to check in a week, what has been entered for them, and
 * the clients that have the service. The handlers are agent/post/backup.php.
 */

require_once "includes/inc_all.php";
require_once "includes/backup_common.php";

enforceUserPermission('module_support');

$tab = ($_GET['tab'] ?? '') === 'clients' ? 'clients' : 'week';
$can_edit = lookupUserPermission('module_support') >= 2;
$week = forkBackupMonday($_GET['week'] ?? '') ?? forkBackupMonday(date('Y-m-d'));
$this_week = forkBackupMonday(date('Y-m-d'));
$previous = date('Y-m-d', strtotime($week . ' 12:00:00 -7 days'));
$next = date('Y-m-d', strtotime($week . ' 12:00:00 +7 days'));
$frequencies = forkBackupFrequencies();

?>

<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link<?= $tab === 'week' ? ' active' : '' ?>" href="backup_checks.php"><i class="fas fa-fw fa-calendar-check me-2"></i>Checks of the week</a></li>
    <li class="nav-item"><a class="nav-link<?= $tab === 'clients' ? ' active' : '' ?>" href="backup_checks.php?tab=clients"><i class="fas fa-fw fa-users me-2"></i>Clients with the service</a></li>
</ul>

<?php if ($tab === 'week') {
    $rows = forkBackupWeek($week);
    $to_do = count(array_filter($rows, fn($row) => !$row['done'])); ?>
    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-hdd me-2"></i>Backup checks - week of <?= escapeHtml($week) ?><?= $week === $this_week ? ' (this week)' : '' ?></h3>
            <div class="card-tools">
                <a class="btn btn-secondary" href="backup_checks.php?week=<?= $previous ?>"><i class="fas fa-chevron-left"></i></a>
                <?php if ($week !== $this_week) { ?><a class="btn btn-secondary" href="backup_checks.php">This week</a><?php } ?>
                <a class="btn btn-secondary" href="backup_checks.php?week=<?= $next ?>"><i class="fas fa-chevron-right"></i></a>
            </div>
        </div>
        <?php if (!$rows) { ?>
            <div class="card-body text-secondary">No client is to be checked this week. Give a client the service in the <a href="backup_checks.php?tab=clients">Clients with the service</a> tab.</div>
        <?php } else { ?>
            <div class="card-body border-bottom py-2 text-secondary"><?= $to_do ? "$to_do still to do out of " . count($rows) : 'All ' . count($rows) . ' done' ?>.</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="text-dark"><tr><th>Client</th><th>Checked</th><th>Servers</th><th>This week</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row) { ?>
                        <tr>
                            <td class="fw-semibold"><?= escapeHtml($row['client_name']) ?></td>
                            <td><?= escapeHtml($frequencies[$row['frequency']]) ?></td>
                            <td><?= $row['servers'] ?: '<span class="text-danger">none - add them</span>' ?></td>
                            <td><?= $row['done'] ? backupStateBadge($row['state']) : '<span class="badge bg-secondary">To do</span>' ?></td>
                            <td class="text-end">
                                <?php if ($row['servers']) { ?>
                                    <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="xl" data-modal-url="modals/backup/backup_check.php?client_id=<?= $row['client_id'] ?>&week=<?= $week ?>"><i class="fas fa-pen me-2"></i><?= $row['done'] ? 'Open' : 'Enter' ?></button>
                                <?php } else { ?>
                                    <a class="btn btn-sm btn-secondary" href="backup_client.php?client_id=<?= $row['client_id'] ?>">Servers</a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
<?php } else {
    $clients = forkBackupClients();
    $to_add = forkBackupClientsToAdd(); ?>
    <div class="card card-dark">
        <div class="card-header py-2"><h3 class="card-title mt-2"><i class="fas fa-fw fa-users me-2"></i>Clients with the service</h3></div>
        <?php if ($can_edit && $to_add) { ?>
            <form action="post.php" method="post" class="card-body border-bottom row g-2 align-items-end">
                <?= backupCsrf() ?>
                <div class="col-md-4"><label class="form-label">Add a client</label><select class="form-select" name="client_id" required><option value="">Choose a client</option><?= backupOptions($to_add, '') ?></select></div>
                <div class="col-md-3"><label class="form-label">Checked</label><select class="form-select" name="frequency"><?= backupOptions($frequencies, 'weekly') ?></select></div>
                <div class="col-md-3"><label class="form-label">Starting the week of</label><input type="date" class="form-control" name="anchor" value="<?= $this_week ?>"></div>
                <div class="col-md-2"><button type="submit" name="backup_client_add" value="1" class="btn btn-primary w-100"><i class="fas fa-plus me-2"></i>Add</button></div>
            </form>
        <?php } ?>
        <?php if (!$clients) { ?>
            <div class="card-body text-secondary">No client has the service yet.</div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="text-dark"><tr><th>Client</th><th>Checked</th><th>Since</th><th>Servers</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($clients as $row) { ?>
                        <tr>
                            <td class="fw-semibold"><?= escapeHtml($row['client_name']) ?></td>
                            <td><?= escapeHtml($frequencies[$row['frequency']]) ?></td>
                            <td><?= escapeHtml($row['anchor']) ?></td>
                            <td><?= $row['servers'] ?></td>
                            <td><?= $row['active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Paused</span>' ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-secondary" href="backup_client.php?client_id=<?= $row['client_id'] ?>"><i class="fas fa-cog me-2"></i>Open</a></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
<?php } ?>

<?php require_once "../includes/footer.php";
