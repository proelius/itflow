<?php
/*
 * Client Portal
 * Add a contact - a new person at this company.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

portalItGate();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle form submission
    $name = sanitize($_POST['contact_name']);
    $email = sanitize($_POST['contact_email']);
    
    // Insert into database...
    flashAlert(t('Contact {name} created', ['{name}' => $name]), 'success');
    redirect('contacts.php');
}
?>

<div class="itflow-portal-page-head">
    <div>
        <h1><?= t('Add Contact') ?></h1>
        <p><?= t('Add a new person to your company') ?></p>
    </div>
</div>

<section class="itflow-portal-panel">
    <form method="post" action="contact_add.php">
        <div class="mb-3">
            <label><?= t('Name') ?> <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="contact_name" required>
        </div>
        <div class="mb-3">
            <label><?= t('Email') ?> <strong class="text-danger">*</strong></label>
            <input type="email" class="form-control" name="contact_email" required>
        </div>
        <div class="mb-3">
            <label><?= t('Phone') ?></label>
            <input type="text" class="form-control" name="contact_phone">
        </div>
        <button type="submit" class="btn btn-primary"><?= t('Save') ?></button>
        <a href="contacts.php" class="btn btn-secondary"><?= t('Cancel') ?></a>
    </form>
</section>

<?php
require_once "includes/footer.php";
