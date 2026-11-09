<?php
/*
 * Client Portal
 * Assets - the equipment assigned to this contact, or all equipment if the
 * contact is a primary/technical/billing one. ?tab=assigned for assigned,
 * ?tab=all for all. DataTables on the browser.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

$tabs = [
    'all' => t('All Assets'),
    'assigned' => t('Assigned to me'),
];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'all';

// If the contact is not a primary/technical/billing one, they only see assigned assets
if ($session_contact_primary != 1 && $session_contact_is_billing_contact != 1 && $session_contact_is_technical_contact != 1) {
    $tab = 'assigned';
}

$where = $tab === 'assigned' ? "asset_contact_id = $session_contact_id" : "asset_archived_at IS NULL";

$sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_model, asset_make, asset_serial, asset_status, asset_purchase_date, asset_warranty_expires, asset_uri FROM assets WHERE $where ORDER BY asset_name");
?>

<div class="itflow-portal-page-head">
    <div>
        <h1><?= t('Assets') ?></h1>
        <p><?= t('Equipment assigned to you or your company') ?></p>
    </div>
</div>

<nav class="itflow-portal-tabs" aria-label="Asset views">
    <?php foreach ($tabs as $key => $label) { ?>
        <a class="<?= $tab === $key ? 'active' : '' ?>" href="?tab=<?= $key ?>"><?= $label ?></a>
    <?php } ?>
</nav>

<section class="itflow-portal-panel">
    <?php if (!mysqli_num_rows($sql)) { ?>
        <?= emptyStateHtml(['title' => t('No assets yet'), 'text' => t('Assets assigned to you will show here.'), 'icon' => 'fa-desktop', 'filtered' => false]) ?>
    <?php } else { ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th><?= t('Name') ?></th>
                        <th><?= t('Type') ?></th>
                        <th><?= t('Model') ?></th>
                        <th><?= t('Serial') ?></th>
                        <th><?= t('Assigned') ?></th>
                        <th><?= t('Purchase') ?></th>
                        <th><?= t('Warranty') ?></th>
                        <th><?= t('Status') ?></th>
                        <th><?= t('URI') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($sql)) { ?>
                        <tr>
                            <td><?= escapeHtml($row['asset_name']) ?></td>
                            <td><?= escapeHtml($row['asset_type']) ?></td>
                            <td><?= escapeHtml($row['asset_model']) ?></td>
                            <td><?= escapeHtml($row['asset_serial']) ?></td>
                            <td><?= escapeHtml($row['asset_contact_id']) ?></td>
                            <td><?= escapeHtml($row['asset_purchase_date']) ?></td>
                            <td><?= escapeHtml($row['asset_warranty_expires']) ?></td>
                            <td><?= escapeHtml($row['asset_status']) ?></td>
                            <td><?= escapeHtml($row['asset_uri']) ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</section>

<?php
require_once "includes/footer.php";
