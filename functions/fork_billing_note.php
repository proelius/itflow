<?php

/*
 * Billing note templates - ready-made notes to put under an invoice, a quote or a recurring invoice.
 *
 * An admin writes them in Admin > Templates > Billing Notes, each for quotes, for invoices or for both.
 * On a document the Notes box stays free text; the picker beside it (js/billing_note_picker.js) puts
 * a template into it, after what is there already, and the document keeps its own copy - changing,
 * archiving or deleting a template later changes no document.
 *
 * The fork's own table, made when first used (CREATE TABLE IF NOT EXISTS) - nothing in ITFlow's
 * tables or database updates. The pure functions take plain values and no database
 * (scripts/fork_billing_note_selftest.php).
 */

const FORK_BILLING_NOTE_NAME_MAX = 120;
const FORK_BILLING_NOTE_BODY_MAX = 5000;

/** Where a template is offered: key => label. */
function forkBillingNoteScopes() {
    return ['both' => 'Quotes and invoices', 'invoice' => 'Invoices only', 'quote' => 'Quotes only'];
}

/** The table, made if it is not there yet. */
function forkBillingNoteEnsure() {
    global $mysqli;
    static $ready = false;
    if ($ready) {
        return;
    }
    $ready = true;
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `fork_billing_notes` (
        `fork_note_id` int(11) NOT NULL AUTO_INCREMENT,
        `fork_note_name` varchar(" . FORK_BILLING_NOTE_NAME_MAX . ") NOT NULL,
        `fork_note_body` text NOT NULL,
        `fork_note_scope` varchar(10) NOT NULL DEFAULT 'both',
        `fork_note_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `fork_note_updated_at` datetime DEFAULT NULL,
        `fork_note_archived_at` datetime DEFAULT NULL,
        PRIMARY KEY (`fork_note_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

/*
 * A template from the form: [name, body, scope, error]. Line breaks are kept; the name is one line.
 * The error says what to correct, '' when there is none.
 */
function forkBillingNoteClean(array $in) {
    $name = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($in['name'] ?? ''))));
    $body = trim(str_replace(["\r\n", "\r"], "\n", strip_tags((string) ($in['body'] ?? ''))));
    $scope = (string) ($in['scope'] ?? 'both');
    if (!isset(forkBillingNoteScopes()[$scope])) {
        $scope = 'both';
    }
    $error = '';
    if ($name === '') {
        $error = 'Give the note a name - it is what is picked from.';
    } elseif (mb_strlen($name) > FORK_BILLING_NOTE_NAME_MAX) {
        $error = 'The name can be ' . FORK_BILLING_NOTE_NAME_MAX . ' characters at most.';
    } elseif ($body === '') {
        $error = 'Write the note.';
    } elseif (mb_strlen($body) > FORK_BILLING_NOTE_BODY_MAX) {
        $error = 'The note can be ' . number_format(FORK_BILLING_NOTE_BODY_MAX) . ' characters at most.';
    }
    return [$name, $body, $scope, $error];
}

/** A template put into a Notes box: it opens an empty one, and follows what is there after a blank line. */
function forkBillingNoteInsert($existing, $body) {
    $existing = rtrim(str_replace(["\r\n", "\r"], "\n", (string) $existing));
    $body = trim((string) $body);
    return $existing === '' ? $body : $existing . "\n\n" . $body;
}

/** A line of what a template says, for lists. */
function forkBillingNotePreview($body, $length = 110) {
    $text = trim(preg_replace('/\s+/u', ' ', (string) $body));
    return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
}

/** Whether a template for $scope is offered on a document of $kind ('invoice' or 'quote'). */
function forkBillingNoteOffered($scope, $kind) {
    return $scope === 'both' || $scope === $kind;
}

/** One template - or null. */
function forkBillingNoteRow($id) {
    global $mysqli;
    forkBillingNoteEnsure();
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM fork_billing_notes WHERE fork_note_id = " . intval($id))) ?: null;
}

/** The templates for the admin list: [rows, number of rows], $q the search, $show_archived, then the page ($from, $count). */
function forkBillingNoteList($q, $show_archived, $from, $count) {
    global $mysqli;
    forkBillingNoteEnsure();
    $where = "(fork_note_name LIKE '%" . escapeSql($q) . "%' OR fork_note_body LIKE '%" . escapeSql($q) . "%') AND fork_note_archived_at IS " . ($show_archived ? 'NOT NULL' : 'NULL');
    $total = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM fork_billing_notes WHERE $where"))[0] ?? 0);
    $rows = mysqli_fetch_all(mysqli_query($mysqli, "SELECT * FROM fork_billing_notes WHERE $where ORDER BY fork_note_name ASC, fork_note_id ASC LIMIT " . intval($from) . ", " . intval($count)), MYSQLI_ASSOC);
    return [$rows, $total];
}

/** How many are current and how many archived, for the tabs - the search applied. [current, archived] */
function forkBillingNoteCounts($q) {
    global $mysqli;
    forkBillingNoteEnsure();
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(fork_note_archived_at IS NULL), 0), COALESCE(SUM(fork_note_archived_at IS NOT NULL), 0)
        FROM fork_billing_notes WHERE (fork_note_name LIKE '%" . escapeSql($q) . "%' OR fork_note_body LIKE '%" . escapeSql($q) . "%')"));
    return [intval($row[0]), intval($row[1])];
}

/** The templates offered on a document of $kind ('invoice' or 'quote'): id, name, body, preview - by name. */
function forkBillingNotesFor($kind) {
    global $mysqli;
    forkBillingNoteEnsure();
    $kind = $kind === 'quote' ? 'quote' : 'invoice';
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT fork_note_id, fork_note_name, fork_note_body, fork_note_scope FROM fork_billing_notes
        WHERE fork_note_archived_at IS NULL AND fork_note_scope IN ('both', '$kind') ORDER BY fork_note_name ASC, fork_note_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = ['id' => intval($row['fork_note_id']), 'name' => $row['fork_note_name'], 'body' => $row['fork_note_body'], 'preview' => forkBillingNotePreview($row['fork_note_body'])];
    }
    return $rows;
}

/** Make a template. Its id. */
function forkBillingNoteCreate($name, $body, $scope) {
    global $mysqli;
    forkBillingNoteEnsure();
    mysqli_query($mysqli, "INSERT INTO fork_billing_notes SET fork_note_name = '" . escapeSql($name) . "', fork_note_body = '" . escapeSql($body) . "', fork_note_scope = '" . escapeSql($scope) . "'");
    return intval(mysqli_insert_id($mysqli));
}

/** Change a template. */
function forkBillingNoteUpdate($id, $name, $body, $scope) {
    global $mysqli;
    forkBillingNoteEnsure();
    mysqli_query($mysqli, "UPDATE fork_billing_notes SET fork_note_name = '" . escapeSql($name) . "', fork_note_body = '" . escapeSql($body) . "', fork_note_scope = '" . escapeSql($scope) . "',
        fork_note_updated_at = NOW() WHERE fork_note_id = " . intval($id));
}

/** Archive (not offered on documents) or restore a template. */
function forkBillingNoteArchive($id, $archive) {
    global $mysqli;
    forkBillingNoteEnsure();
    mysqli_query($mysqli, "UPDATE fork_billing_notes SET fork_note_archived_at = " . ($archive ? 'NOW()' : 'NULL') . " WHERE fork_note_id = " . intval($id));
}

/** Delete a template - the documents keep the text they were given. */
function forkBillingNoteDelete($id) {
    global $mysqli;
    forkBillingNoteEnsure();
    mysqli_query($mysqli, "DELETE FROM fork_billing_notes WHERE fork_note_id = " . intval($id));
}
