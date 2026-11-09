<?php
/*
 * Client Portal
 * Documents - the files shared with this contact. ?tab=assigned for assigned
 * to me, ?tab=all for all.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

$tabs = [
    'all' => t('All Documents'),
    'assigned' => t('Assigned to me'),
];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'all';

// If the contact is not a primary/technical/billing one, they only see assigned documents
if ($session_contact_primary != 1 && $session_contact_is_billing_contact != 1 && $session_contact_is_technical_contact != 1) {
    $tab = 'assigned';
}

$where = $tab === 'assigned' ? "document_contact_id = $session_contact_id" : "1 = 1";
$scope = "document_client_id = $session_client_id AND document_archived_at IS NULL";

$sql = mysqli_query($mysqli, "SELECT document_id, document_name, document_description, document_type, document_size, document_created_at FROM documents WHERE $scope AND $where ORDER BY document_name");
?>

<div class="itflow-portal-page-head">
    <div>
        <h1><?= t('Documents') ?></h1>
        <p><?= t('Files shared with you') ?></p>
    </div>
</div>

<nav class="itflow-portal-tabs" aria-label="Document views">
    <?php foreach ($tabs as $key => $label) { ?>
        <a class="<?= $tab === $key ? 'active' : '' ?>" href="?tab=<?= $key ?>"><?= $label ?></a>
    <?php } ?>
</nav>

<section class="itflow-portal-panel">
    <?php if (!mysqli_num_rows($sql)) { ?>
        <?= portalEmptyState(t('There are no documents shared with you yet.')) ?>
    <?php } else { ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th><?= t('Name') ?></th>
                        <th><?= t('Description') ?></th>
                        <th><?= t('Type') ?></th>
                        <th><?= t('Size') ?></th>
                        <th><?= t('Uploaded') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($sql)) { ?>
                        <tr>
                            <td><?= escapeHtml($row['document_name']) ?></td>
                            <td><?= escapeHtml($row['document_description']) ?></td>
                            <td><?= escapeHtml($row['document_type']) ?></td>
                            <td><?= escapeHtml($row['document_size']) ?></td>
                            <td><?= escapeHtml($row['document_created_at']) ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</section>

<?php
require_once "includes/footer.php";
