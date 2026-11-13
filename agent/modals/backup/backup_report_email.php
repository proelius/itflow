<?php

/*
 * Send a backup check report to the client's contacts picked, with a note and the PDF - the same window as for a quote
 * (agent/modals/quote/quote_email.php). ?report_id=. Posts email_backup_report (agent/post/backup.php).
 * The PDF is always attached: the email has no page to read the report on.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 2);

$report = forkBackupReportRow(intval($_GET['report_id'] ?? 0));
if (!$report) {
    exit(json_encode(['error' => 'That report no longer exists.']));
}
$report_id = intval($report['br_id']);
$client_id = intval($report['br_client_id']);

enforceClientAccess();

// The same contacts as for a quote - the primary, billing, technical and important ones with an email address
$sql_contacts = mysqli_query(
    $mysqli,
    "SELECT contact_billing, contact_email, contact_id, contact_photo, contact_important, contact_name,
        contact_primary, contact_technical, contact_title
    FROM contacts
    WHERE contact_client_id = $client_id
    AND contact_archived_at IS NULL
    AND contact_email IS NOT NULL
    AND contact_email != ''
    " . documentContactFilterSql('quote') . "
    ORDER BY contact_primary DESC, contact_billing DESC, contact_technical DESC, contact_name ASC"
);
$contact_count = mysqli_num_rows($sql_contacts);

// Which boxes open ticked: the primary contact, as for a quote
$default_contact_ids = [];
$sql_defaults = mysqli_query(
    $mysqli,
    "SELECT contact_id FROM contacts
    WHERE contact_client_id = $client_id
    AND contact_archived_at IS NULL
    AND contact_email IS NOT NULL
    AND contact_email != ''
    " . documentDefaultContactFilterSql('quote')
);
while ($row = mysqli_fetch_assoc($sql_defaults)) {
    $default_contact_ids[] = intval($row['contact_id']);
}

$weeks = array_sum(array_map('count', $report['data']['months']));

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-paper-plane me-2"></i>Send Report <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($report['br_start']) ?> &ndash; <?= escapeHtml($report['br_end']) ?> &middot; <?= escapeHtml($report['client_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="report_id" value="<?= $report_id ?>">

    <div class="modal-body">

        <?php if ($contact_count == 0) { ?>

            <p class="text-body-secondary mb-0">
                This client has no primary, billing, technical or important contact with an email
                address, so there is nobody to send the report to. Flag a contact with one of those
                roles, or download the PDF and send it yourself.
            </p>

        <?php } else { ?>

            <!-- What goes out -->
            <div class="d-flex align-items-center gap-3 p-3 mb-3 rounded bg-body-tertiary">
                <i class="fas fa-fw fa-file-pdf fa-2x text-danger"></i>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold"><?= escapeHtml(forkBackupReportTitle($report)) ?></div>
                    <div class="small text-body-secondary">An email with the report attached as a PDF, written in the client's language.</div>
                </div>
                <div class="text-end small text-body-secondary"><?= $weeks ?> week<?= $weeks === 1 ? '' : 's' ?> checked</div>
            </div>

            <label class="form-label">Send to <strong class="text-danger">*</strong></label>

            <div class="list-group mb-3">

                <?php

                while ($row = mysqli_fetch_assoc($sql_contacts)) {
                    $contact_id = intval($row['contact_id']);
                    $contact_name = escapeHtml($row['contact_name']);
                    $contact_email = escapeHtml($row['contact_email']);
                    $contact_title = escapeHtml($row['contact_title']);

                    $contact_checked = in_array($contact_id, $default_contact_ids, true);

                    ?>

                    <label class="list-group-item d-flex align-items-center gap-2" for="reportEmailContact<?= $contact_id ?>">
                        <input type="checkbox" class="form-check-input flex-shrink-0 m-0" name="contacts[]"
                            id="reportEmailContact<?= $contact_id ?>" value="<?= $contact_id ?>"
                            <?php if ($contact_checked) { echo "checked"; } ?>>
                        <?= contactAvatar($client_id, $contact_id, $row['contact_name'], $row['contact_photo'], 'sm') ?>
                        <span class="min-w-0">
                            <span class="d-flex flex-wrap align-items-center column-gap-1">
                                <strong><?= $contact_name ?></strong>
                                <?= contactFlagPills($row, false) ?>
                                <?php if ($contact_title) { ?><span class="text-body-secondary small"><?= $contact_title ?></span><?php } ?>
                            </span>
                            <span class="d-block small text-body-secondary text-truncate"><?= $contact_email ?></span>
                        </span>
                    </label>

                    <?php

                }

                ?>

            </div>

            <label class="form-label">Message <span class="text-body-secondary fw-normal small">in the email, above the summary</span></label>
            <div class="input-group mb-3">
                <span class="input-group-text align-items-start pt-2"><i class="fas fa-fw fa-comment"></i></span>
                <textarea class="form-control" name="note" rows="3" maxlength="2000" placeholder="Optional - a word of your own"></textarea>
            </div>

            <p class="text-body-secondary small mb-0">
                <i class="fa fa-fw fa-info-circle me-1"></i>The report is attached as a PDF. Nothing else changes: the report is not marked as sent.
            </p>

        <?php } ?>

    </div>
    <div class="modal-footer">
        <?php if ($contact_count > 0) { ?>
            <button type="submit" name="email_backup_report" value="1" class="btn btn-primary text-bold">
                <i class="fa fa-fw fa-paper-plane me-2"></i>Send
            </button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
            <i class="fa fa-fw fa-times me-2"></i>Cancel
        </button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
