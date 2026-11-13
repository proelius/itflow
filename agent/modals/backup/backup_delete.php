<?php

/*
 * Delete for good what the backup checks hold for a client - the second step, after the archive (as for assets):
 * the service, its servers, the checks entered and the reports made. The window says how much that is first.
 * The handler is agent/post/backup.php (backup_client_delete, Financial module, Full access).
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 3);

$client_id = intval($_GET['client_id'] ?? 0);
enforceClientAccess();

$client = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = $client_id"));
if (!$client) {
    echo json_encode(['error' => 'That client no longer exists.']);
    exit;
}
if (forkBackupClient($client_id)) {
    echo json_encode(['error' => 'Archive the service first - an active service cannot be deleted.']);
    exit;
}
$data = forkBackupClientData($client_id);

ob_start();

?>

<div class="modal-body text-center py-4">
    <div class="mb-3"><i class="fas fa-trash fa-2x text-danger"></i></div>
    <h5 class="mb-1">Delete the backup checks of this client for good?</h5>
    <div class="fw-semibold text-break mb-2"><?= escapeHtml($client['client_name']) ?></div>
    <div class="small text-body-secondary mb-4">
        <?= $data['checks'] ?> check<?= $data['checks'] === 1 ? '' : 's' ?>, <?= $data['reports'] ?> report<?= $data['reports'] === 1 ? '' : 's' ?> and <?= $data['servers'] ?> server<?= $data['servers'] === 1 ? '' : 's' ?> are deleted.
        The tickets are not touched. This cannot be undone.
    </div>
    <form action="post.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="client_id" value="<?= $client_id ?>">
        <button type="button" class="btn btn-light px-4 me-2" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="backup_client_delete" value="1" class="btn btn-danger px-4"><i class="fas fa-trash me-2"></i>Delete for good</button>
    </form>
</div>

<?php

require_once '../../../includes/modal_footer.php';
