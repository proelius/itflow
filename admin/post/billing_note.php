<?php

/*
 * Billing note templates (Admin > Templates > Billing Notes) - ready-made notes to put under an invoice, a quote or a
 * recurring invoice. The work is in functions/fork_billing_note.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_billing_note'])) {

    validateCSRFToken();

    [$name, $body, $scope, $error] = forkBillingNoteClean($_POST);
    if ($error !== '') {
        flashAlert($error, 'error');
        redirect();
    }

    $note_id = forkBillingNoteCreate($name, $body, $scope);

    logAudit("Billing Note", "Create", "$session_name created billing note " . escapeSql($name), 0, $note_id);

    flashAlert("Billing note <strong>" . escapeHtml($name) . "</strong> created");

    redirect();

}

if (isset($_POST['edit_billing_note'])) {

    validateCSRFToken();

    $note_id = intval($_POST['note_id'] ?? 0);
    $row = forkBillingNoteRow($note_id);
    if (!$row) {
        flashAlert("That billing note is gone", 'error');
        redirect();
    }

    [$name, $body, $scope, $error] = forkBillingNoteClean($_POST);
    if ($error !== '') {
        flashAlert($error, 'error');
        redirect();
    }

    forkBillingNoteUpdate($note_id, $name, $body, $scope);

    logAudit("Billing Note", "Edit", "$session_name edited billing note " . escapeSql($name), 0, $note_id);

    flashAlert("Billing note <strong>" . escapeHtml($name) . "</strong> saved - documents that used it keep the text they were given");

    redirect();

}

if (isset($_GET['archive_billing_note']) || isset($_GET['restore_billing_note'])) {

    validateCSRFToken();

    $archive = isset($_GET['archive_billing_note']);
    $note_id = intval($archive ? $_GET['archive_billing_note'] : $_GET['restore_billing_note']);
    $row = forkBillingNoteRow($note_id);
    if (!$row) {
        redirect();
    }

    forkBillingNoteArchive($note_id, $archive);

    logAudit("Billing Note", $archive ? "Archive" : "Restore", "$session_name " . ($archive ? 'archived' : 'restored') . " billing note " . escapeSql($row['fork_note_name']), 0, $note_id);

    flashAlert("Billing note <strong>" . escapeHtml($row['fork_note_name']) . "</strong> " . ($archive ? 'archived - it is no longer offered on documents' : 'restored'), $archive ? 'error' : 'success');

    redirect();

}

if (isset($_GET['delete_billing_note'])) {

    validateCSRFToken();

    $note_id = intval($_GET['delete_billing_note']);
    $row = forkBillingNoteRow($note_id);
    if (!$row) {
        redirect();
    }

    forkBillingNoteDelete($note_id);

    logAudit("Billing Note", "Delete", "$session_name deleted billing note " . escapeSql($row['fork_note_name']), 0, $note_id);

    flashAlert("Billing note <strong>" . escapeHtml($row['fork_note_name']) . "</strong> deleted - documents that used it keep the text they were given", 'error');

    redirect();

}
