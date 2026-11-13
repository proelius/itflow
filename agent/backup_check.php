<?php

/*
 * A client's backup check for a week (functions/fork_backup.php): one block for each server. A new check starts from
 * the last one - the disks - and the second check only appears when something failed.
 * The handlers are agent/post/backup.php.
 */

require_once "includes/inc_all_client.php";
require_once "includes/backup_common.php";

enforceUserPermission('module_support');

$service = forkBackupClient($client_id);
if (!$service && lookupUserPermission('module_financial') >= 1) {
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
    <div class="card card-dark"><div class="card-body">This client has no server to check yet.<?php if (lookupUserPermission('module_financial') >= 1) { ?> <a href="backup_client.php?client_id=<?= $client_id ?>">Add the servers</a> first.<?php } ?></div></div>
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
                <?php require __DIR__ . '/includes/backup_check_fields.php'; ?>
            </div>
            <?php if ($can_edit) { ?>
                <div class="card-footer"><button type="submit" name="save_backup_check" value="1" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save the check</button>
                    <a class="btn btn-secondary" href="backup_checks.php?week=<?= $week ?>">Back to the week</a></div>
            <?php } ?>
        </div>
    </form>

<?php } ?>

<?php require_once "../includes/footer.php";
