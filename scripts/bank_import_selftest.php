#!/usr/bin/env php
<?php
/**
 * Bank statement import self-test - functions/expense_import.php, functions/qr_bill.php
 * A UBS-style CSV (notes before the header, a transaction over two lines, French column names)
 * is read, its columns worked out, and the QR reference in a deposit found.
 *
 * CLI only - protected from web access
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

$itflow_dir = __DIR__ . '/..';
require_once $itflow_dir . '/functions/expense_import.php';
require_once $itflow_dir . '/functions/income_import.php';
require_once $itflow_dir . '/functions/qr_bill.php';
require_once $itflow_dir . '/functions/bank_import_extra.php';

$passed = 0;
$failed = 0;

function bank_test($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "  ✓ $name\n";
        $passed++;
    } else {
        echo "  ✗ $name\n";
        $failed++;
    }
}

echo "Bank statement import\n";

$csv = <<<CSV
IBAN:;CH44 3199 9123 0008 8901 2;;;;;;;;;;;;
Du:;05.11.2026;;;;;;;;;;;;
Au:;09.11.2026;;;;;;;;;;;;
Évaluation en:;CHF;;;;;;;;;;;;
Nombre de transactions dans cette période:;2;;;;;;;;;;;;
;;;;;;;;;;;;;
Date de transaction;Heure de transaction;Date de comptabilisation;Date de valeur;Devise;Débit;Crédit;Sous-montant;Solde;No de transaction;Description1;Description2;Description3;Notes de bas de page
09.11.2026;;09.11.2026;09.11.2026;CHF;;285.45;;;2026313PH0011234;Credit référence QR;CH4431999123000889750;No de transaction: 2026313PH0011234;
;;;;CHF;;;285.45;;2026313PH0011234;QRR: 21 00000 00000 00000 02026 08362;;"Motif du paiement: Client Exemple; 1201 GENEVE; No de transaction: 6540313TI2976547";
05.11.2026;;05.11.2026;05.11.2026;CHF;;244.78;;;9901808E64820987;"Fournisseur AG;3048 Worblaufen";Credit;"Motif du paiement: RG.10/2026; No de transaction: 9901808E64820987";

CSV;

$path = tempnam(sys_get_temp_dir(), 'bank');
file_put_contents($path, $csv);
$read = expenseImportRead($path, $error);
unlink($path);

bank_test('the file is read', $read !== null && $error === '');
bank_test('the header is the line of column names, not the notes above it', ($read['header'][0] ?? '') === 'Date de transaction' && ($read['header'][6] ?? '') === 'Crédit');
bank_test('a transaction over two lines is one row', count($read['rows']) === 2);
bank_test('the second line adds the QR reference to the first', strpos($read['rows'][0][10], 'QRR: 21 00000 00000 00000 02026 08362') !== false);

$map = expenseImportGuess($read['header'], $read['rows']);
bank_test('date, description, memo, money out, money in and reference columns', [$map['date'], $map['description'], $map['memo'], $map['debit'], $map['credit'], $map['reference']] === [0, 10, 12, 5, 6, 9]);
bank_test('dates are day.month.year', $map['date_format'] === 'd.m.Y');

$lines = expenseImportLines($read['rows'], $map);
bank_test('two lines, money in written negative, no problems', count($lines) === 2 && $lines[0]['amount'] === -285.45 && $lines[1]['amount'] === -244.78 && $lines[0]['problem'] === '' && $lines[1]['problem'] === '');
bank_test('the dates are read', $lines[0]['date'] === '2026-11-09' && $lines[1]['date'] === '2026-11-05');

$references = qrBillReferencesIn($lines[0]['match_text']);
bank_test('the QR reference in the first deposit is found, check digit right', $references === ['21000000000000000020260836' . '2']);
bank_test('the second deposit has none', qrBillReferencesIn($lines[1]['match_text'] . ' ' . $lines[1]['reference']) === []);
bank_test('a reference of ours is found grouped or not', qrBillReferencesIn('QRR ' . qrBillReferenceSpaced(qrBillReference('INV-5'))) === [qrBillReference('INV-5')] && qrBillReferencesIn(qrBillReference('INV-5')) === [qrBillReference('INV-5')]);
bank_test('27 digits with a wrong check digit are not one', qrBillReferencesIn('21000000000000000020260836' . '3') === []);

$open = [
    5 => ['invoice_id' => 5, 'client_id' => 2, 'number' => 5, 'ref' => 'INV-5', 'balance' => 108.10],
    6 => ['invoice_id' => 6, 'client_id' => 2, 'number' => 6, 'ref' => 'INV-6', 'balance' => 50.0],
];
$paid = bankImportNamedInvoices(['description' => 'Credit référence QR | QRR: ' . qrBillReferenceSpaced(qrBillReference(5)), 'reference' => ''], $open, ['INV-']);
bank_test('a deposit with the QR reference of invoice 5 pays invoice 5 only, by QR', $paid[0] === 'qr' && count($paid[1]) === 1 && $paid[1][0]['invoice_id'] === 5);
$paid = bankImportNamedInvoices(['description' => 'Société Éléphant - Motif du paiement: Facture INV-6', 'reference' => ''], $open, ['INV-']);
bank_test('"Facture INV-6" in the memo pays invoice 6, by number', $paid[0] === 'number' && count($paid[1]) === 1 && $paid[1][0]['invoice_id'] === 6);
bank_test('a deposit with another reference and no number pays none', bankImportNamedInvoices(['description' => 'QRR: 21 00000 00000 00000 02026 08362', 'reference' => '9901808E64820987'], $open, ['INV-'])[1] === []);

// A plain statement still reads as before
$plain = "Date,Description,Amount\n2026-10-01,Coffee,4.50\n2026-10-02,Payment received,-100.00\n";
$path = tempnam(sys_get_temp_dir(), 'bank');
file_put_contents($path, $plain);
$read = expenseImportRead($path, $error);
unlink($path);
bank_test('a plain statement reads as before', $read !== null && $read['header'] === ['Date', 'Description', 'Amount'] && count($read['rows']) === 2);

echo "\n=== Summary ===\nTests passed: $passed\nTests failed: $failed\n";
if ($failed > 0) {
    exit(1);
}
echo "\n✓ All tests passed!\n";
