<?php

/*
 * A client's backup check service (functions/fork_backup.php): how often it is checked, the servers to check, and
 * the checks entered so far. The handlers are agent/post/backup.php.
 */

require_once "includes/inc_all_client.php";
require_once "includes/backup_common.php";

enforceUserPermission('module_support');

$service = forkBackupClient($client_id);
$can_edit = lookupUserPermission('module_support') >= 2;
$servers = $service ? forkBackupServers($client_id) : [];
$taken = array_column($servers, 'asset_id', 'asset_id');
$assets = [];
foreach ($service && $can_edit ? forkBackupAssets($client_id) : [] as $group => $options) {
    $assets[$group] = array_diff_key($options, $taken);
}
$assets = array_filter($assets);
$week = forkBackupMonday(date('Y-m-d'));
$checks = [];
if ($service) {
    $sql = mysqli_query($mysqli, "SELECT k.bk_week, k.bk_ticket_id, u.user_name, GROUP_CONCAT(l.bl_state) AS states FROM fork_backup_checks k
        JOIN fork_backup_lines l ON l.bl_check_id = k.bk_id LEFT JOIN users u ON u.user_id = k.bk_user_id
        WHERE k.bk_client_id = $client_id GROUP BY k.bk_id ORDER BY k.bk_week DESC LIMIT 26");
    while ($row = mysqli_fetch_assoc($sql)) {
        $checks[] = $row;
    }
}

?>

<?= backupClientTabs($client_id, 'service') ?>

<?php if (!$service) { ?>
    <div class="card card-dark"><div class="card-body">
        This client does not have the backup check service.
        <?php if ($can_edit) { ?>
            <form action="post.php" method="post" class="row g-2 align-items-end mt-2">
                <?= backupCsrf() ?>
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
                <div class="col-md-3"><label class="form-label">Checked</label><select class="form-select" name="frequency"><?= backupOptions(forkBackupFrequencies(), 'weekly') ?></select></div>
                <div class="col-md-3"><label class="form-label">Starting the week of</label><input type="date" class="form-control" name="anchor" value="<?= $week ?>"></div>
                <div class="col-md-2"><button type="submit" name="backup_client_add" value="1" class="btn btn-primary w-100"><i class="fas fa-plus me-2"></i>Give the service</button></div>
            </form>
        <?php } ?>
    </div></div>
<?php } else { ?>
    <div class="row">
        <div class="col-lg-5">
            <div class="card card-dark">
                <div class="card-header py-2"><h3 class="card-title mt-2"><i class="fas fa-fw fa-cog me-2"></i>Service</h3></div>
                <form action="post.php" method="post" class="card-body">
                    <?= backupCsrf() ?>
                    <input type="hidden" name="client_id" value="<?= $client_id ?>">
                    <div class="mb-2"><label class="form-label">Checked</label><select class="form-select" name="frequency" <?= $can_edit ? '' : 'disabled' ?>><?= backupOptions(forkBackupFrequencies(), $service['frequency']) ?></select></div>
                    <div class="mb-2"><label class="form-label">Starting the week of</label><input type="date" class="form-control" name="anchor" value="<?= escapeHtml($service['anchor']) ?>" <?= $can_edit ? '' : 'disabled' ?>></div>
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="active" id="bkActive" value="1" <?= $service['active'] ? 'checked' : '' ?> <?= $can_edit ? '' : 'disabled' ?>><label class="form-check-label" for="bkActive">Active - untick to pause the checks</label></div>
                    <?php if ($can_edit) { ?>
                        <button type="submit" name="backup_client_save" value="1" class="btn btn-primary"><i class="fas fa-check me-2"></i>Save</button>
                        <button type="submit" name="backup_client_remove" value="1" class="btn btn-outline-danger float-end" onclick="return confirm('Take the service away from this client? What was entered is kept.')"><i class="fas fa-times me-2"></i>Remove the service</button>
                    <?php } ?>
                </form>
            </div>
            <div class="card card-dark">
                <div class="card-header py-2"><h3 class="card-title mt-2"><i class="fas fa-fw fa-server me-2"></i>Servers to check</h3></div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($servers as $server) { ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?= escapeHtml($server['name']) ?><?= $server['os'] !== '' ? ' <span class="text-secondary">- ' . escapeHtml($server['os']) . '</span>' : '' ?><?= $server['asset_id'] ? '' : ' <span class="badge bg-secondary">no asset</span>' ?></span>
                            <?php if ($can_edit) { ?>
                                <form action="post.php" method="post" class="d-inline"><?= backupCsrf() ?><input type="hidden" name="client_id" value="<?= $client_id ?>"><input type="hidden" name="server_id" value="<?= $server['id'] ?>">
                                    <button type="submit" name="backup_server_remove" value="1" class="btn btn-sm btn-outline-danger" title="Remove"><i class="fas fa-times"></i></button></form>
                            <?php } ?>
                        </li>
                    <?php } ?>
                    <?php if (!$servers) { ?><li class="list-group-item text-secondary">No server yet.</li><?php } ?>
                </ul>
                <?php if ($can_edit) { ?>
                    <form action="post.php" method="post" class="card-body border-top">
                        <?= backupCsrf() ?>
                        <input type="hidden" name="client_id" value="<?= $client_id ?>">
                        <?php if ($assets) { ?>
                            <label class="form-label">Add a server from the client's assets</label>
                            <div class="input-group mb-3"><select class="form-select" name="asset_id"><option value="0">Choose an asset</option><?= backupOptionGroups($assets, '') ?></select><button type="submit" name="backup_server_add" value="1" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add</button></div>
                        <?php } else { ?>
                            <div class="alert alert-warning py-2">This client has no asset yet - <a href="assets.php?client_id=<?= $client_id ?>">add its servers as assets</a> first, so the Windows version comes from them.</div>
                        <?php } ?>
                        <div class="input-group"><input type="text" class="form-control" name="name" maxlength="200" placeholder="Or a server that is not an asset: its name"><button type="submit" name="backup_server_add" value="1" class="btn btn-secondary"><i class="fas fa-plus me-2"></i>Add</button></div>
                        <div class="text-secondary small mt-2">A server taken from an asset keeps in step with it: its name and Windows version are read from the asset.</div>
                    </form>
                <?php } ?>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card card-dark">
                <div class="card-header py-2">
                    <h3 class="card-title mt-2"><i class="fas fa-fw fa-calendar-check me-2"></i>Checks entered</h3>
                    <?php if ($servers && $can_edit) { ?><div class="card-tools"><a class="btn btn-primary" href="backup_check.php?client_id=<?= $client_id ?>&week=<?= $week ?>"><i class="fas fa-pen me-2"></i>Enter this week's check</a></div><?php } ?>
                </div>
                <?php if (!$checks) { ?>
                    <div class="card-body text-secondary">Nothing entered yet.</div>
                <?php } else { ?>
                    <div class="table-responsive"><table class="table table-hover mb-0">
                        <thead class="text-dark"><tr><th>Week of</th><th>Result</th><th>By</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($checks as $check) { ?>
                            <tr>
                                <td><?= escapeHtml($check['bk_week']) ?></td>
                                <td><?= backupStateBadge(forkBackupWorst(explode(',', $check['states']))) ?></td>
                                <td><?= escapeHtml((string) $check['user_name']) ?></td>
                                <td class="text-end"><a class="btn btn-sm btn-secondary" href="backup_check.php?client_id=<?= $client_id ?>&week=<?= escapeHtml($check['bk_week']) ?>"><i class="fas fa-eye"></i></a></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table></div>
                <?php } ?>
            </div>
        </div>
    </div>
<?php } ?>

<?php require_once "../includes/footer.php";
