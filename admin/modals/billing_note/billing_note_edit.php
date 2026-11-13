<?php

require_once '../../includes/modal_header.php';

$note_id = intval($_GET['id'] ?? 0);
$note = forkBillingNoteRow($note_id);

ob_start();

if (!$note) { ?>
    <div class="modal-body text-center text-secondary py-5">That billing note is gone.</div>
<?php } else { ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fas fa-fw fa-sticky-note me-2"></i>Editing billing note: <strong><?= escapeHtml($note['fork_note_name']) ?></strong></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="note_id" value="<?= $note_id ?>">

        <div class="modal-body">
            <div class="alert alert-info small">
                <i class="fas fa-fw fa-info-circle me-1"></i>A document keeps its own copy of the note it was given - changing this one changes no invoice or quote already made.
            </div>
            <?php require __DIR__ . '/billing_note_form.php'; ?>
        </div>
        <div class="modal-footer">
            <button type="submit" name="edit_billing_note" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
            <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
        </div>
    </form>
<?php }

require_once '../../../includes/modal_footer.php';
