<?php

/*
 * A client's backup check for a week, in a window - opened from the list of the week (agent/backup_checks.php) so the
 * technician enters it without leaving the list. The fields are those of the client's page (agent/includes/backup_check_fields.php);
 * the handler is agent/post/backup.php, which brings the technician back to the list.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support');

$client_id = intval($_GET['client_id'] ?? 0);
enforceClientAccess();

$client = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = $client_id AND client_archived_at IS NULL"));
$service = $client ? forkBackupClient($client_id) : null;
$week = forkBackupMonday($_GET['week'] ?? '') ?? forkBackupMonday(date('Y-m-d'));
$servers = $service ? forkBackupServers($client_id) : [];
$check = $servers ? forkBackupCheck($client_id, $week) : null;
$previous = $servers && !$check ? forkBackupPrevious($client_id, $week) : [];
$can_edit = lookupUserPermission('module_support') >= 2;
$tickets = $servers ? forkBackupTickets($client_id, intval($check['bk_ticket_id'] ?? 0)) : [];
$statuses = forkBackupStatuses();
$parts = ['raid' => 'RAID', 'sys' => 'BKP SYS', 'data' => 'BKP DATA', 'cloud' => 'Cloud'];
$user = $check ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_name FROM users WHERE user_id = " . intval($check['bk_user_id']))) : null;

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-hdd me-2"></i>Backup check - <?= escapeHtml($client['client_name'] ?? '') ?> - week of <?= escapeHtml($week) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<?php if (!$servers) { ?>
    <div class="modal-body">This client has no server to check yet.<?php if (lookupUserPermission('module_financial') >= 1) { ?> <a href="backup_client.php?client_id=<?= $client_id ?>">Add the servers</a> first.<?php } ?></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button></div>
<?php } else { ?>
    <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="client_id" value="<?= $client_id ?>">
        <input type="hidden" name="week" value="<?= $week ?>">
        <div class="modal-body">
            <?php require __DIR__ . '/../../includes/backup_check_fields.php'; ?>
        </div>
        <div class="modal-footer">
            <?php if ($can_edit) { ?>
                <button type="submit" name="save_backup_check" value="1" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save the check</button>
            <?php } ?>
            <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
        </div>
    </form>
<?php } ?>

<?php

require_once '../../../includes/modal_footer.php';
