#!/usr/bin/env php
<?php
/**
 * Fork billing note templates self-test - functions/fork_billing_note.php
 * The pure functions, and the wiring of the admin page, the handler, the picker and the three document pages.
 *
 * CLI only - protected from web access
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

$itflow_dir = __DIR__ . '/..';
require_once $itflow_dir . '/functions/fork_billing_note.php';

$passed = 0;
$failed = 0;

function note_test($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "  ✓ $name\n";
        $passed++;
    } else {
        echo "  ✗ $name\n";
        $failed++;
    }
}

$read = fn($f) => file_get_contents(__DIR__ . '/../' . $f);

echo "A note from the form\n";
[$n, $b, $s, $e] = forkBillingNoteClean(['name' => '  Bank   details ', 'body' => "Line 1\r\nLine 2\r\n", 'scope' => 'invoice']);
note_test('name tidied, body kept with its lines, scope kept', $n === 'Bank details' && $b === "Line 1\nLine 2" && $s === 'invoice' && $e === '');
[, , $s, $e] = forkBillingNoteClean(['name' => 'A', 'body' => 'B', 'scope' => 'everything']);
note_test('an unknown scope becomes both', $s === 'both' && $e === '');
[, , $s] = forkBillingNoteClean(['name' => 'A', 'body' => 'B']);
note_test('no scope is both', $s === 'both');
[, , , $e] = forkBillingNoteClean(['name' => '   ', 'body' => 'B']);
note_test('a name is needed', $e !== '');
[, , , $e] = forkBillingNoteClean(['name' => 'A', 'body' => " \n ", 'scope' => 'both']);
note_test('a note is needed', $e !== '');
[, , , $e] = forkBillingNoteClean(['name' => str_repeat('a', FORK_BILLING_NOTE_NAME_MAX + 1), 'body' => 'B']);
note_test('a name that is too long is refused', $e !== '');
[, , , $e] = forkBillingNoteClean(['name' => 'A', 'body' => str_repeat('a', FORK_BILLING_NOTE_BODY_MAX + 1)]);
note_test('a note that is too long is refused', $e !== '');
[, $b] = forkBillingNoteClean(['name' => 'A', 'body' => 'Pay <b>now</b><script>x</script>']);
note_test('tags are taken out', strpos($b, '<') === false);
[$n, $b] = forkBillingNoteClean(['name' => 'Été', 'body' => 'Merci de votre confiance — à bientôt']);
note_test('accents are kept', $n === 'Été' && $b === 'Merci de votre confiance — à bientôt');

echo "\nPutting a note into the Notes box\n";
note_test('an empty box takes it as it is', forkBillingNoteInsert('', 'Pay in 30 days.') === 'Pay in 30 days.');
note_test('a box with only spaces takes it as it is', forkBillingNoteInsert("  \n ", 'Pay in 30 days.') === 'Pay in 30 days.');
note_test('what is there stays, the note follows after a blank line', forkBillingNoteInsert('Thanks.', 'Pay in 30 days.') === "Thanks.\n\nPay in 30 days.");
note_test('trailing blank lines are not doubled', forkBillingNoteInsert("Thanks.\n\n\n", 'Pay in 30 days.') === "Thanks.\n\nPay in 30 days.");
note_test('line breaks of Windows are made plain', forkBillingNoteInsert("A\r\nB", 'C') === "A\nB\n\nC");

echo "\nWhere it is offered, and the preview\n";
note_test('both is offered on a quote and on an invoice', forkBillingNoteOffered('both', 'quote') && forkBillingNoteOffered('both', 'invoice'));
note_test('invoices only is not offered on a quote', !forkBillingNoteOffered('invoice', 'quote') && forkBillingNoteOffered('invoice', 'invoice'));
note_test('quotes only is not offered on an invoice', !forkBillingNoteOffered('quote', 'invoice') && forkBillingNoteOffered('quote', 'quote'));
note_test('a short text is its own preview, on one line', forkBillingNotePreview("A\n\nB") === 'A B');
note_test('a long text is cut with an ellipsis', mb_strlen(forkBillingNotePreview(str_repeat('word ', 80))) <= 110 && str_ends_with(forkBillingNotePreview(str_repeat('word ', 80)), '…'));

echo "\nIn the fork's own table only\n";
$db = $read('db.sql');
note_test('fork_billing_notes is not in db.sql', strpos($db, 'fork_billing_notes') === false);
$in_updates = false;
foreach (glob($itflow_dir . '/admin/database_updates/*.php') as $file) {
    if (strpos(file_get_contents($file), 'fork_billing_notes') !== false) {
        $in_updates = true;
    }
}
note_test('fork_billing_notes is not in ITFlow\'s database updates', !$in_updates);
note_test('the table is made when first used', str_contains($read('functions/fork_billing_note.php'), 'CREATE TABLE IF NOT EXISTS `fork_billing_notes`'));
note_test('the note column of an invoice or a quote is not changed', !str_contains($read('functions/fork_billing_note.php'), 'ALTER TABLE'));
note_test('functions.php loads the functions', str_contains($read('functions.php'), "functions/fork_billing_note.php"));

echo "\nThe admin page and its handler\n";
$post = $read('admin/post/billing_note.php');
note_test('each action checks the CSRF token', substr_count($post, 'validateCSRFToken();') === 4);
note_test('each action is written to the audit log', substr_count($post, 'logAudit(') === 4);
note_test('the page is in the TEMPLATES section of the Admin menu, with the accounting module', (function () use ($read) {
    $nav = $read('admin/includes/side_nav.php');
    $at = strpos($nav, '/admin/billing_notes.php');
    $templates = strpos($nav, 'TEMPLATES');
    $management = strpos($nav, 'MANAGEMENT');
    return $at !== false && $templates !== false && $at > $templates && $at < $management && strrpos(substr($nav, 0, $at), 'config_module_enable_accounting') > $templates;
})());
note_test('the page lists them by name, a page at a time', str_contains($read('admin/billing_notes.php'), 'forkBillingNoteList($q, $show_archived, $record_from, $record_to)'));
note_test('deleting asks first', str_contains($read('admin/billing_notes.php'), 'data-confirm-title="Delete this billing note?"'));

echo "\nThe picker on the documents\n";
$paper = $read('includes/billing_paper.php');
note_test('the Notes box of the paper has the picker, only where it is edited', preg_match("/notes_edit.*?forkBillingNotesFor\(.*?billing_note_picker\.php/s", $paper) === 1);
note_test('a recurring invoice is offered the invoice notes', str_contains($paper, "\$paper_c === 'quote' ? 'quote' : 'invoice'"));
$picker = $read('includes/billing_note_picker.php');
note_test('the note text and the search text are escaped into the page', str_contains($picker, 'data-note-body="<?= escapeHtml(') && str_contains($picker, 'data-note-search-text="<?= escapeHtml('));
$js = $read('js/billing_note_picker.js');
note_test('picking puts the note after what is there and saves through the box\'s own blur save', str_contains($js, "existing + '\\n\\n' + body") && str_contains($js, "new Event('blur')"));
foreach (['agent/invoice.php', 'agent/quote.php', 'agent/recurring_invoice.php'] as $page) {
    note_test("$page loads the picker script after the inline notes one", preg_match("#inline_notes\.js.*?billing_note_picker\.js#s", $read($page)) === 1);
}

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
