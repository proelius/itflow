<?php
/*
 * The billing note picker beside the Notes box of an invoice, a quote or a recurring invoice - a button
 * opening a list of the notes on offer to search, each with a line of what it says. Picking one puts it into the
 * box after what is there (js/billing_note_picker.js), which saves it as typing does; the document keeps its own copy.
 * Notes are written in Admin > Templates > Billing Notes (functions/fork_billing_note.php).
 *
 * Set before including it:
 *   $paper_note_templates  forkBillingNotesFor() - nothing is drawn when it is empty, but the way to make one
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

?>
<div class="dropdown ms-3 d-print-none fw-normal text-transform-none itflow-note-picker" data-note-picker data-editor="billingNotes">
    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle py-0" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
        <i class="fas fa-fw fa-sticky-note me-1"></i>Note template
    </button>
    <div class="dropdown-menu p-0" style="min-width: 22rem; max-width: 30rem;">
        <?php if ($paper_note_templates) { ?>
            <div class="p-2 border-bottom d-flex align-items-center gap-1">
                <input type="search" class="form-control form-control-sm" placeholder="Search notes..." aria-label="Search notes" autocomplete="off" data-note-search>
                <button type="button" class="btn btn-sm btn-link text-decoration-none flex-shrink-0" title="Close the list (Esc)" data-note-close>Cancel</button>
            </div>
            <div style="max-height: 20rem; overflow-y: auto;" data-note-list>
                <?php foreach ($paper_note_templates as $paper_note_template) { ?>
                    <button type="button" class="dropdown-item" data-note-body="<?= escapeHtml($paper_note_template['body']) ?>"
                        data-note-search-text="<?= escapeHtml(mb_strtolower($paper_note_template['name'] . ' ' . $paper_note_template['preview'])) ?>">
                        <span class="d-block fw-semibold text-truncate"><?= escapeHtml($paper_note_template['name']) ?></span>
                        <span class="d-block small text-body-secondary text-truncate"><?= escapeHtml($paper_note_template['preview']) ?></span>
                    </button>
                <?php } ?>
                <div class="px-3 py-3 small text-body-secondary" data-note-none hidden>No note matches.</div>
            </div>
        <?php } else { ?>
            <div class="px-3 py-3 small text-body-secondary">No billing notes yet - an admin writes them in Admin &gt; Templates &gt; Billing Notes.</div>
        <?php } ?>
    </div>
</div>
