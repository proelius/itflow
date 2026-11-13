<?php

/*
 * Admin > Templates > Billing Notes - ready-made notes for invoices, quotes and recurring invoices.
 *
 * Each is offered on quotes, on invoices (recurring invoices too) or on both. On a document the
 * Notes box stays free text and a picker beside it puts a note in; the document keeps its own copy.
 * A note can be changed, archived - not offered on documents - or deleted. The work is in
 * functions/fork_billing_note.php; the handlers are admin/post/billing_note.php.
 */

// Default Column Sortby Filter
$sort = "fork_note_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$show_archived = ($_GET['show'] ?? '') === 'archived';

[$notes, $found] = forkBillingNoteList($q, $show_archived, $record_from, $record_to);
$num_rows = [$found];
$counts = forkBillingNoteCounts($q);
$scopes = forkBillingNoteScopes();
$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-sticky-note me-2"></i>Billing Notes</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/billing_note/billing_note_add.php" data-modal-size="lg"><i class="fas fa-plus me-2"></i>New Billing Note</button>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($show_archived) { ?><input type="hidden" name="show" value="archived"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-md-7">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search billing notes..." title="Name or text">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2">
            <a class="nav-link <?= $show_archived ? '' : 'active' ?>" href="?<?= escapeHtml(http_build_query(array_filter(['q' => $_GET['q'] ?? '']))) ?>"><i class="fas fa-fw fa-sticky-note me-1"></i>Active <span class="badge rounded-pill ms-1"><?= $counts[0] ?></span></a>
            <a class="nav-link <?= $show_archived ? 'active' : '' ?>" href="?<?= escapeHtml(http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'show' => 'archived']))) ?>" title="Not offered on documents"><i class="fas fa-fw fa-archive me-1"></i>Archived <span class="badge rounded-pill ms-1"><?= $counts[1] ?></span></a>
        </nav>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3">Note</th>
                <th class="d-none d-sm-table-cell">Offered on</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($notes as $note) {
                $note_id = intval($note['fork_note_id']); ?>
                <tr>
                    <td class="ps-3">
                        <a class="ajax-modal fw-semibold text-body" href="#" data-modal-url="modals/billing_note/billing_note_edit.php?id=<?= $note_id ?>" data-modal-size="lg"><?= escapeHtml($note['fork_note_name']) ?></a>
                        <div class="small text-body-secondary"><?= escapeHtml(forkBillingNotePreview($note['fork_note_body'], 160)) ?></div>
                    </td>
                    <td class="d-none d-sm-table-cell small"><?= escapeHtml($scopes[$note['fork_note_scope']] ?? $scopes['both']) ?></td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/billing_note/billing_note_edit.php?id=<?= $note_id ?>" data-modal-size="lg"><i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit</a>
                                <?php if ($note['fork_note_archived_at']) { ?>
                                    <a class="dropdown-item" href="post.php?restore_billing_note=<?= $note_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore</a>
                                <?php } else { ?>
                                    <a class="dropdown-item" href="post.php?archive_billing_note=<?= $note_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-archive text-secondary me-2"></i>Archive</a>
                                <?php } ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_billing_note=<?= $note_id ?>&csrf_token=<?= $csrf ?>"
                                    data-confirm-title="Delete this billing note?" data-confirm-text="Invoices and quotes that used it keep the text they were given." data-confirm-button="Delete"><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
    $empty_state = [
        'thing' => 'billing notes',
        'icon' => 'fa-sticky-note',
        'filtered' => $q !== '',
        'archived' => $show_archived,
        'views' => ['show'],
        'text' => 'Payment details, how long a quote holds, a thank you - written once, then picked from the Notes box of an invoice or a quote.',
        'actions' => [['label' => 'New Billing Note', 'modal' => 'modals/billing_note/billing_note_add.php', 'size' => 'lg']],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php
require_once "../includes/footer.php";
