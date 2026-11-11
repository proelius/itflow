#!/usr/bin/env php
<?php
/**
 * Swiss QR-bill self-test - functions/qr_bill.php
 * IBAN and QR-IBAN checks, the QR reference and its check digit, the payload.
 *
 * CLI only - protected from web access
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

$itflow_dir = __DIR__ . '/..';
require_once $itflow_dir . '/functions/i18n.php';
require_once $itflow_dir . '/functions/qr_bill.php';

$passed = 0;
$failed = 0;

function qr_test($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "  ✓ $name\n";
        $passed++;
    } else {
        echo "  ✗ $name\n";
        $failed++;
    }
}

echo "Swiss QR-bill\n";

// The examples of the SIX guidelines
qr_test('QR-IBAN CH44 3199 9123 0008 8901 2 is valid and a QR-IBAN', qrBillIbanValid('CH44 3199 9123 0008 8901 2') && qrBillIsQrIban('CH44 3199 9123 0008 8901 2'));
qr_test('a regular IBAN is valid but not a QR-IBAN', qrBillIbanValid('CH93 0076 2011 6238 5295 7') && !qrBillIsQrIban('CH93 0076 2011 6238 5295 7'));
qr_test('wrong IBAN check digits are refused', !qrBillIbanValid('CH45 3199 9123 0008 8901 2'));
qr_test('a foreign IBAN is refused', !qrBillIbanValid('DE89 3704 0044 0532 0130 00'));
qr_test('check digit of 21000000000313947143000901 is 7', qrBillCheckDigit('21000000000313947143000901') === 7);
qr_test('reference in groups', qrBillReferenceSpaced('210000000003139471430009017') === '21 00000 00003 13947 14300 09017');

$reference = qrBillReference('INV-000123');
qr_test('reference of INV-000123 has 27 digits and ends 0123 + check digit', strlen($reference) === 27 && substr($reference, 22, 4) === '0123' && qrBillCheckDigit(substr($reference, 0, 26)) === intval($reference[26]));
qr_test('a number with no digits has no reference', qrBillReference('INV-') === '');
qr_test('a number of more than 26 digits has no reference', qrBillReference(str_repeat('1', 27)) === '');

qr_test('street and building number are split', qrBillSplitStreet('Route de Chancy 59c') === ['Route de Chancy', '59c']);
qr_test('an address with no number stays whole', qrBillSplitStreet('Place du Molard') === ['Place du Molard', '']);
qr_test('Switzerland is CH', qrBillCountryCode('Switzerland') === 'CH');
qr_test('text outside the allowed characters is cleaned', qrBillText("A\u{2019}b\n\u{1F600}c", 70) === "A'b c");

$bill = [
    'iban' => 'CH44 3199 9123 0008 8901 2',
    'creditor' => ['TEST SARL', 'Route de Chancy 59c', '1213', 'Petit-Lancy', 'Switzerland'],
    'debtor' => ['Société Éléphant', 'Rue de Genève 10', '1202', 'Genève', 'Switzerland'],
    'amount' => 1503.4, 'currency' => 'CHF', 'reference' => $reference, 'message' => 'Invoice INV-000123',
];
$lines = explode("\n", qrBillPayload($bill));
qr_test('the payload has 31 lines', count($lines) === 31);
qr_test('header SPC / 0200 / 1 and the IBAN without spaces', array_slice($lines, 0, 4) === ['SPC', '0200', '1', 'CH4431999123000889012']);
qr_test('creditor is a structured address', array_slice($lines, 4, 7) === ['S', 'TEST SARL', 'Route de Chancy', '59c', '1213', 'Petit-Lancy', 'CH']);
qr_test('amount, currency, QRR and trailer', $lines[18] === '1503.40' && $lines[19] === 'CHF' && $lines[27] === 'QRR' && $lines[28] === $reference && $lines[30] === 'EPD');
$bill['debtor'] = null;
$bill['amount'] = null;
$lines = explode("\n", qrBillPayload($bill));
qr_test('no debtor and no amount leave their fields empty', $lines[18] === '' && $lines[20] === '' && $lines[26] === '' && count($lines) === 31);

echo "\n=== Summary ===\nTests passed: $passed\nTests failed: $failed\n";
if ($failed > 0) {
    exit(1);
}
echo "\n✓ All tests passed!\n";
