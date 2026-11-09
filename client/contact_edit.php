<?php
/*
 * Client Portal
 * Edit a contact - update details for an existing person.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

portalItGate();

$contact_id = intval($_GET['id'] ?? 0);
$scope = "contact_id = $contact_id AND contact_client_id = $session_client_id";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle form submission
    $name = sanitize($_POST['contact_name']);
    $email = sanitize($_POST['contact_email']);
    
    // Update database...
    flashAlert(t('Contact {name} updated', ['{name}' => $name]), 'success');
    redirect('contacts.php');
}

$contact = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM contacts WHERE $scope"));
if (!$contact) {
    flashAlert(t('Contact not found'), 'error');
    redirect('contacts.php');
}
?>

<div class="itflow-portal-page-head">
    <div>
        <h1><?= t('Edit Contact') ?></h1>
        <p><?= t('Update contact details') ?></p>
    </div>
</div>

<section class="itflow-portal-panel">
    <form method="post" action="contact_edit.php?id=<?= $contact_id ?>">
        <div class="mb-3">
            <label><?= t('Name') ?> <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="contact_name" value="<?= escapeHtml($contact['contact_name']) ?>" required>
        </div>
        <div class="mb-3">
            <label><?= t('Email') ?> <strong class="text-danger">*</strong></label>
            <input type="email" class="form-control" name="contact_email" value="<?= escapeHtml($contact['contact_email']) ?>" required>
        </div>
        <div class="mb-3">
            <label><?= t('Phone') ?></label>
            <input type="text" class="form-control" name="contact_phone" value="<?= escapeHtml($contact['contact_phone'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-primary"><?= t('Save') ?></button>
        <a href="contacts.php" class="btn btn-secondary"><?= t('Cancel') ?></a>
    </form>
</section>

<?php
require_once "includes/footer.php";
