<?php
/*
 * Client Portal
 * Contacts - the company's contacts, or all contacts if the user is a
 * primary/technical/billing one. ?tab=assigned for assigned to me, ?tab=all
 * for all.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

$tabs = [
    'all' => t('All Contacts'),
    'assigned' => t('Assigned to me'),
];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'all';

// If the contact is not a primary/technical/billing one, they only see assigned contacts
if ($session_contact_primary != 1 && $session_contact_is_billing_contact != 1 && $session_contact_is_technical_contact != 1) {
    $tab = 'assigned';
}

$where = $tab === 'assigned' ? "contact_id IN (SELECT contact_contact_id FROM contacts_contacts WHERE contact_primary_contact_id = $session_contact_id)" : "1 = 1";
$scope = "contact_client_id = $session_client_id AND contact_archived_at IS NULL";

$sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_email, contact_phone, contact_type FROM contacts WHERE $scope AND $where ORDER BY contact_name");
?>

<div class="itflow-portal-page-head">
    <div>
        <h1><?= t('Contacts') ?></h1>
        <p><?= t('People at your company') ?></p>
    </div>
</div>

<nav class="itflow-portal-tabs" aria-label="Contact views">
    <?php foreach ($tabs as $key => $label) { ?>
        <a class="<?= $tab === $key ? 'active' : '' ?>" href="?tab=<?= $key ?>"><?= $label ?></a>
    <?php } ?>
</nav>

<section class="itflow-portal-panel">
    <?php if (!mysqli_num_rows($sql)) { ?>
        <?= portalEmptyState(t('There are no contacts on this account yet.')) ?>
    <?php } else { ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th><?= t('Name') ?></th>
                        <th><?= t('Email') ?></th>
                        <th><?= t('Phone') ?></th>
                        <th><?= t('Type') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($sql)) { ?>
                        <tr>
                            <td><?= escapeHtml($row['contact_name']) ?></td>
                            <td><?= escapeHtml($row['contact_email']) ?></td>
                            <td><?= escapeHtml($row['contact_phone']) ?></td>
                            <td><?= escapeHtml($row['contact_type']) ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</section>

<?php
require_once "includes/footer.php";
