<?php

/*
 * A billing note's fields - for billing_note_add.php and billing_note_edit.php, which set $note (a
 * forkBillingNoteRow(), or null when new). Posts to admin/post.php (admin/post/billing_note.php).
 */

isset($mysqli) || die("Direct file access is not allowed");

$n = $note ?? null;
$scope_now = $n['fork_note_scope'] ?? 'both';

?>

<div class="mb-3">
    <label class="form-label">Name <span class="text-danger">*</span> <span class="text-secondary">(what is picked from the list)</span></label>
    <input type="text" class="form-control" name="name" maxlength="<?= FORK_BILLING_NOTE_NAME_MAX ?>" value="<?= escapeHtml($n['fork_note_name'] ?? '') ?>" placeholder="Bank details, Quote validity, Thank you..." required autofocus>
</div>
<div class="mb-3">
    <label class="form-label">Note <span class="text-danger">*</span> <span class="text-secondary">(plain text - each new line is kept)</span></label>
    <textarea class="form-control" name="body" rows="7" maxlength="<?= FORK_BILLING_NOTE_BODY_MAX ?>" required><?= escapeHtml($n['fork_note_body'] ?? '') ?></textarea>
</div>
<div class="mb-0">
    <label class="form-label">Offered on</label>
    <select class="form-select" name="scope">
        <?php foreach (forkBillingNoteScopes() as $scope_key => $scope_label) { ?>
            <option value="<?= $scope_key ?>"<?= $scope_key === $scope_now ? ' selected' : '' ?>><?= escapeHtml($scope_label) ?></option>
        <?php } ?>
    </select>
    <div class="form-text">Recurring invoices are offered the invoice ones.</div>
</div>
