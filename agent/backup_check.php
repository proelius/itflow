<?php

/*
 * A client's backup check for a week (functions/fork_backup.php): one block for each server. A new check starts from
 * the last one - the disks and the Veeam version - and the second check only appears when something failed.
 * The handlers are agent/post/backup.php.
 */

require_once "includes/inc_all_client.php";
require_once "includes/backup_common.php";

enforceUserPermission('module_support');

$service = forkBackupClient($client_id);
if (!$service) {
    redirect("backup_client.php?client_id=$client_id");
}
$week = forkBackupMonday($_GET['week'] ?? '') ?? forkBackupMonday(date('Y-m-d'));
$servers = forkBackupServers($client_id);
$check = forkBackupCheck($client_id, $week);
$previous = $check ? [] : forkBackupPrevious($client_id, $week);
$can_edit = lookupUserPermission('module_support') >= 2;
$tickets = forkBackupTickets($client_id, intval($check['bk_ticket_id'] ?? 0));
$statuses = forkBackupStatuses();
$parts = ['raid' => 'RAID', 'sys' => 'BKP SYS', 'data' => 'BKP DATA', 'cloud' => 'Cloud'];
$user = $check ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_name FROM users WHERE user_id = " . intval($check['bk_user_id']))) : null;

?>

<?= backupClientTabs($client_id, 'service') ?>

<?php if (!$servers) { ?>
    <div class="card card-dark"><div class="card-body">This client has no server to check yet. <a href="backup_client.php?client_id=<?= $client_id ?>">Add the servers</a> first.</div></div>
<?php } else { ?>
    <form action="post.php" method="post" autocomplete="off">
        <?= backupCsrf() ?>
        <input type="hidden" name="client_id" value="<?= $client_id ?>">
        <input type="hidden" name="week" value="<?= $week ?>">
        <div class="card card-dark">
            <div class="card-header py-2">
                <h3 class="card-title mt-2"><i class="fas fa-fw fa-hdd me-2"></i>Backup check - week of <?= escapeHtml($week) ?></h3>
                <div class="card-tools">
                    <a class="btn btn-secondary" href="backup_check.php?client_id=<?= $client_id ?>&week=<?= date('Y-m-d', strtotime($week . ' 12:00:00 -7 days')) ?>"><i class="fas fa-chevron-left"></i></a>
                    <a class="btn btn-secondary" href="backup_check.php?client_id=<?= $client_id ?>&week=<?= date('Y-m-d', strtotime($week . ' 12:00:00 +7 days')) ?>"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-4"><label class="form-label">Technician</label><input type="text" class="form-control" value="<?= escapeHtml($user['user_name'] ?? $session_name) ?>" disabled></div>
                    <div class="col-md-8"><label class="form-label">Ticket <span class="text-secondary">(optional)</span></label>
                        <select class="form-select" name="ticket_id" <?= $can_edit ? '' : 'disabled' ?>><option value="0">No ticket</option><?= backupOptions($tickets, $check['bk_ticket_id'] ?? 0) ?></select></div>
                </div>
                <?php if (!$check) { ?><div class="text-secondary small mb-3">The statuses start at OK: change what is not, and say why in the comment. The disks and the Veeam version are those of the last check.</div><?php } ?>

                <?php foreach ($servers as $server_id => $server) {
                    $line = $check['lines'][$server_id] ?? null;
                    $old = $previous[$server_id] ?? [];
                    $value = fn($key, $default = '') => $line ? (string) $line['bl_' . $key] : (string) ($old['bl_' . $key] ?? $default);
                    $name = "line[$server_id]"; ?>
                    <div class="border rounded p-3 mb-3 bk-line" data-state="<?= escapeHtml($line['bl_state'] ?? '') ?>">
                        <div class="fw-semibold mb-2"><i class="fas fa-server me-2"></i><?= escapeHtml($server['name']) ?><?= $server['os'] !== '' ? ' <span class="text-secondary fw-normal">- ' . escapeHtml($server['os']) . '</span>' : '' ?></div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-3"><label class="form-label">Disk 1</label><input type="text" class="form-control" name="<?= $name ?>[disk1]" maxlength="100" placeholder="C: 349 / 424 Go" value="<?= escapeHtml($value('disk1')) ?>" <?= $can_edit ? '' : 'disabled' ?>></div>
                            <div class="col-md-3"><label class="form-label">Disk 2</label><input type="text" class="form-control" name="<?= $name ?>[disk2]" maxlength="100" placeholder="D: 2.60 / 3.45 To" value="<?= escapeHtml($value('disk2')) ?>" <?= $can_edit ? '' : 'disabled' ?>></div>
                            <div class="col-md-3"><label class="form-label">Veeam version</label><input type="text" class="form-control" name="<?= $name ?>[veeam]" maxlength="50" value="<?= escapeHtml($value('veeam')) ?>" <?= $can_edit ? '' : 'disabled' ?>></div>
                        </div>
                        <div class="row g-2 mb-2">
                            <?php foreach ($parts as $key => $label) { ?>
                                <div class="col-6 col-md-3"><label class="form-label"><?= $label ?></label><select class="form-select bk-status" name="<?= $name ?>[<?= $key ?>]" <?= $can_edit ? '' : 'disabled' ?>><?= backupOptions($statuses, $line ? $line['bl_' . $key] : 'ok') ?></select></div>
                            <?php } ?>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-3 bk-second-box"><label class="form-label">Second check</label>
                                <select class="form-select bk-second" name="<?= $name ?>[second]" <?= $can_edit ? '' : 'disabled' ?>><?= backupOptions(['pending' => 'Not done yet', 'ok' => 'OK', 'fail' => 'Failed'], in_array($line['bl_second'] ?? '', ['ok', 'fail'], true) ? $line['bl_second'] : 'pending') ?></select></div>
                            <div class="col-md-3"><label class="form-label">Colour</label>
                                <select class="form-select bk-state" name="<?= $name ?>[state]" <?= $can_edit ? '' : 'disabled' ?>><?= backupOptions(forkBackupStates(), $line['bl_state'] ?? 'green') ?></select></div>
                        </div>
                        <label class="form-label">Comment <span class="text-secondary bk-need">(what happened - needed when it is not green)</span></label>
                        <textarea class="form-control" name="<?= $name ?>[comment]" rows="2" maxlength="2000" <?= $can_edit ? '' : 'disabled' ?>><?= escapeHtml($line['bl_comment'] ?? '') ?></textarea>
                    </div>
                <?php } ?>
            </div>
            <?php if ($can_edit) { ?>
                <div class="card-footer"><button type="submit" name="save_backup_check" value="1" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save the check</button>
                    <a class="btn btn-secondary" href="backup_checks.php?week=<?= $week ?>">Back to the week</a></div>
            <?php } ?>
        </div>
    </form>
    <script>
    document.querySelectorAll('.bk-line').forEach(function (box) {
        var statuses = box.querySelectorAll('.bk-status'), second = box.querySelector('.bk-second'), secondBox = box.querySelector('.bk-second-box'),
            state = box.querySelector('.bk-state'), touched = box.dataset.state !== '';
        function refresh() {
            var failed = Array.prototype.some.call(statuses, function (s) { return s.value === 'fail'; });
            secondBox.style.display = failed ? '' : 'none';
            if (!touched) { state.value = !failed ? 'green' : (second.value === 'ok' ? 'yellow' : 'red'); }
        }
        state.addEventListener('change', function () { touched = true; });
        statuses.forEach(function (s) { s.addEventListener('change', refresh); });
        second.addEventListener('change', refresh);
        refresh();
    });
    </script>
<?php } ?>

<?php require_once "../includes/footer.php";
