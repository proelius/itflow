<?php

/*
 * Swiss QR-bill (QR-facture) - the payment part and receipt at the foot of an
 * invoice's PDF, with a QR-IBAN and a QR reference (QRR), per the SIX
 * "Swiss Implementation Guidelines QR-bill" 2.3: payload "SPC" 0200, structured
 * addresses only (the combined address type was withdrawn in November 2025).
 *
 * Shown on an invoice that is still owed, in CHF or EUR, when a QR-IBAN is set
 * in the invoice settings (settings.config_invoice_qr_iban) and the company is
 * in Switzerland or Liechtenstein. Nothing changes for anyone else.
 *
 * The QR reference is the invoice's number, digits only, padded with zeros to
 * 26 digits and a modulo-10 check digit (27 in all) - so a payment the bank
 * reports with that reference points at one invoice.
 */

/** The slip is one A6-landscape-and-a-bit band: 105 mm high, the whole width of an A4 page. */
const QR_BILL_HEIGHT = 105;

/** The QR-IBAN as saved - upper case, no spaces. */
function qrBillIban($iban) {
    return strtoupper(preg_replace('/\s+/', '', (string) $iban));
}

/** Whether $iban is a well-formed Swiss or Liechtenstein IBAN (21 characters, check digits right). */
function qrBillIbanValid($iban) {
    $iban = qrBillIban($iban);
    if (!preg_match('/^(CH|LI)\d{2}[0-9A-Z]{17}$/', $iban)) {
        return false;
    }
    $moved = substr($iban, 4) . substr($iban, 0, 4);
    $digits = '';
    foreach (str_split($moved) as $char) {
        $digits .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
    }
    $rest = 0;
    foreach (str_split($digits, 7) as $part) {
        $rest = intval($rest . $part) % 97;
    }
    return $rest === 1;
}

/** Whether it is a QR-IBAN - its institution identifier (characters 5 to 9) is 30000 to 31999. */
function qrBillIsQrIban($iban) {
    $iban = qrBillIban($iban);
    return qrBillIbanValid($iban) && intval(substr($iban, 4, 5)) >= 30000 && intval(substr($iban, 4, 5)) <= 31999;
}

/** The IBAN in groups of four, for reading. */
function qrBillIbanSpaced($iban) {
    return trim(chunk_split(qrBillIban($iban), 4, ' '));
}

/** The modulo-10 (recursive) check digit of a run of digits - the last digit of a QR reference. */
function qrBillCheckDigit($digits) {
    $table = [0, 9, 4, 6, 8, 2, 7, 1, 3, 5];
    $carry = 0;
    foreach (str_split((string) $digits) as $digit) {
        $carry = $table[($carry + intval($digit)) % 10];
    }
    return (10 - $carry) % 10;
}

/** The 27-digit QR reference for an invoice number - or '' when its digits are none or too many. */
function qrBillReference($number) {
    $digits = preg_replace('/\D+/', '', (string) $number);
    if ($digits === '' || strlen($digits) > 26) {
        return '';
    }
    $digits = str_pad($digits, 26, '0', STR_PAD_LEFT);
    return $digits . qrBillCheckDigit($digits);
}

/** A QR reference in groups - "21 00000 00003 13947 14300 09017". */
function qrBillReferenceSpaced($reference) {
    $reference = (string) $reference;
    return trim(substr($reference, 0, 2) . ' ' . trim(chunk_split(substr($reference, 2), 5, ' ')));
}

/** Text as the QR code may carry it - the Latin characters banks accept, one line, at most $max long. */
function qrBillText($text, $max) {
    $text = strtr((string) $text, ["\u{2019}" => "'", "\u{2018}" => "'", "\u{201C}" => '"', "\u{201D}" => '"', "\u{2013}" => '-', "\u{2014}" => '-', "\u{00A0}" => ' ', "\u{202F}" => ' ']);
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = preg_replace('/[^\x{20}-\x{7E}\x{A1}-\x{17F}\x{20AC}]/u', '', $text);
    return mb_substr(trim($text), 0, $max);
}

/** A street address in one line as [street, building number] - "Route de Chancy 59c" is ['Route de Chancy', '59c']. */
function qrBillSplitStreet($address) {
    $address = trim(preg_replace('/\s+/u', ' ', str_replace(["\r", "\n"], ' ', (string) $address)));
    if (preg_match('/^(.*?)[\s,]+(\d+[0-9A-Za-z\-\/]{0,9})$/u', $address, $m) && trim($m[1]) !== '') {
        return [trim($m[1], " ,"), $m[2]];
    }
    return [$address, ''];
}

/** A country's ISO 3166 alpha-2 code, upper case, from the name ITFlow stores - '' when it isn't one it knows. */
function qrBillCountryCode($country) {
    static $codes = null;
    if ($codes === null) {
        $country_iso2_array = [];
        require __DIR__ . '/../includes/settings_localization_array.php';
        $codes = $country_iso2_array;
    }
    $country = trim((string) $country);
    if (preg_match('/^[A-Za-z]{2}$/', $country)) {
        return strtoupper($country);
    }
    return strtoupper((string) ($codes[$country] ?? ''));
}

/** The seven address fields of the payload: type, name, street, number, postcode, town, country - all empty when there is no address. */
function qrBillAddressFields($name, $address, $zip, $town, $country) {
    $name = qrBillText($name, 70);
    $town = qrBillText($town, 35);
    $zip = qrBillText($zip, 16);
    $code = qrBillCountryCode($country);
    if ($name === '' || $town === '' || $code === '') {
        return ['', '', '', '', '', '', ''];
    }
    [$street, $number] = qrBillSplitStreet($address);
    return ['S', $name, qrBillText($street, 70), qrBillText($number, 16), $zip, $town, $code];
}

/**
 * What the QR code holds - one line each, in the order of the guidelines.
 * $bill: iban, creditor [name, address, zip, town, country], amount (float or null), currency,
 * debtor [name, address, zip, town, country] or null, reference, message.
 */
function qrBillPayload(array $bill) {
    $creditor = $bill['creditor'];
    $debtor = $bill['debtor'] ?? null;
    $lines = ['SPC', '0200', '1', qrBillIban($bill['iban'])];
    $lines = array_merge($lines, qrBillAddressFields($creditor[0], $creditor[1], $creditor[2], $creditor[3], $creditor[4]));
    $lines = array_merge($lines, ['', '', '', '', '', '', '']);
    $lines[] = $bill['amount'] !== null ? number_format((float) $bill['amount'], 2, '.', '') : '';
    $lines[] = $bill['currency'];
    $lines = array_merge($lines, $debtor ? qrBillAddressFields($debtor[0], $debtor[1], $debtor[2], $debtor[3], $debtor[4]) : ['', '', '', '', '', '', '']);
    $lines[] = 'QRR';
    $lines[] = $bill['reference'];
    $lines[] = qrBillText($bill['message'] ?? '', 140);
    $lines[] = 'EPD';
    return implode("\n", $lines);
}

/** The QR-IBAN saved in the invoice settings - '' when there is none, or before the column exists (database 3.6.3). */
function qrBillSettingsIban() {
    global $mysqli;
    static $iban = null;
    if ($iban === null) {
        $iban = '';
        $column = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'config_invoice_qr_iban' LIMIT 1");
        if ($column && mysqli_num_rows($column) > 0) {
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_invoice_qr_iban FROM settings WHERE company_id = 1")) ?: [];
            $iban = qrBillIban($row['config_invoice_qr_iban'] ?? '');
        }
    }
    return $iban;
}

/**
 * The QR-bill for an invoice - the data the slip is drawn from - or null when it has none:
 * no valid QR-IBAN saved, a currency other than CHF or EUR, a company outside Switzerland
 * and Liechtenstein, or nothing left to pay.
 */
function qrBillFor(array $row, array $company, array $bill_to, $currency, $balance, $number) {
    $iban = qrBillSettingsIban();
    if ($iban === '' || !qrBillIsQrIban($iban) || !in_array($currency, ['CHF', 'EUR'], true) || $balance <= 0 || $balance > 999999999.99) {
        return null;
    }
    if (!in_array(qrBillCountryCode($company['company_country'] ?? ''), ['CH', 'LI'], true)) {
        return null;
    }
    $reference = qrBillReference($row['invoice_number'] ?? '');
    if ($reference === '') {
        return null;
    }
    $creditor = [$company['company_name'] ?? '', $company['company_address'] ?? '', $company['company_zip'] ?? '', $company['company_city'] ?? '', $company['company_country'] ?? ''];
    if (qrBillAddressFields(...$creditor)[0] !== 'S') {
        return null;
    }
    $debtor = [$bill_to['client_name'] ?? '', $bill_to['location_address'] ?? '', $bill_to['location_zip'] ?? '', $bill_to['location_city'] ?? '', $bill_to['location_country'] ?? ''];
    return [
        'iban' => $iban,
        'creditor' => $creditor,
        'debtor' => qrBillAddressFields(...$debtor)[0] === 'S' ? $debtor : null,
        'amount' => round($balance, 2),
        'currency' => $currency,
        'reference' => $reference,
        'message' => t('Invoice {number}', ['{number}' => $number]),
    ];
}

/** A line of corner marks around an empty field - where the payer writes. */
function qrBillCorners($pdf, $x, $y, $w, $h) {
    $len = 3;
    $pdf->SetLineStyle(['width' => 0.2, 'color' => [0, 0, 0], 'dash' => 0]);
    foreach ([[$x, $y, 1, 1], [$x + $w, $y, -1, 1], [$x, $y + $h, 1, -1], [$x + $w, $y + $h, -1, -1]] as [$cx, $cy, $dx, $dy]) {
        $pdf->Line($cx, $cy, $cx + $dx * $len, $cy);
        $pdf->Line($cx, $cy, $cx, $cy + $dy * $len);
    }
}

/** The Swiss cross over the middle of the QR code - 7 mm square, centred at ($cx, $cy). */
function qrBillCross($pdf, $cx, $cy) {
    $u = 7 / 19.8;
    $x0 = $cx - 3.5;
    $y0 = $cy - 3.5;
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect($x0, $y0, 7, 7, 'F');
    $pdf->SetFillColor(0, 0, 0);
    $pdf->Rect($x0 + 0.7 * $u, $y0 + 0.7 * $u, 18.4 * $u, 18.4 * $u, 'F');
    $pdf->SetFillColor(255, 255, 255);
    $cross = [8.3, 4, 11.3, 4, 11.3, 8.3, 15.6, 8.3, 15.6, 11.3, 11.3, 11.3, 11.3, 15.6, 8.3, 15.6, 8.3, 11.3, 4, 11.3, 4, 8.3, 8.3, 8.3];
    $points = [];
    foreach ($cross as $i => $value) {
        $points[] = ($i % 2 === 0 ? $x0 : $y0) + $value * $u;
    }
    $pdf->Polygon($points, 'F');
}

/**
 * Draw the payment part and receipt on the foot of the current page of an A4 $pdf
 * (the caller has made room: 105 mm, with page breaks off).
 */
function qrBillDraw($pdf, array $bill) {
    $top = $pdf->getPageHeight() - QR_BILL_HEIGHT;
    $amount_text = $bill['amount'] !== null ? number_format($bill['amount'], 2, '.', ' ') : '';
    $creditor = $bill['creditor'];
    [$street, $number] = qrBillSplitStreet($creditor[1]);
    $creditor_lines = array_filter([qrBillIbanSpaced($bill['iban']), qrBillText($creditor[0], 70), trim(qrBillText($street, 70) . ' ' . qrBillText($number, 16)), trim(qrBillText($creditor[2], 16) . ' ' . qrBillText($creditor[3], 35))]);
    $debtor_lines = [];
    if ($bill['debtor']) {
        $d = $bill['debtor'];
        [$d_street, $d_number] = qrBillSplitStreet($d[1]);
        $debtor_lines = array_filter([qrBillText($d[0], 70), trim(qrBillText($d_street, 70) . ' ' . qrBillText($d_number, 16)), trim(qrBillText($d[2], 16) . ' ' . qrBillText($d[3], 35))]);
    }
    $reference = qrBillReferenceSpaced($bill['reference']);
    $info = qrBillText($bill['message'], 140);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetDrawColor(0, 0, 0);

    // The lines to cut along, with scissors
    $pdf->SetLineStyle(['width' => 0.2, 'color' => [0, 0, 0], 'dash' => '1.5,1.5']);
    $pdf->Line(0, $top, $pdf->getPageWidth(), $top);
    $pdf->Line(62, $top, 62, $pdf->getPageHeight());
    $pdf->SetLineStyle(['width' => 0.2, 'color' => [0, 0, 0], 'dash' => 0]);
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Text(8, $top - 3.6, "\u{2702}");
    $pdf->StartTransform();
    $pdf->Rotate(-90, 62, $top + 14);
    $pdf->Text(62 - 1.2, $top + 14 - 1.6, "\u{2702}");
    $pdf->StopTransform();

    $block = function ($x, $y, $w, $heading, array $lines, $heading_pt, $value_pt) use ($pdf) {
        $pdf->SetFont('helvetica', 'B', $heading_pt);
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, $heading_pt * 0.45, $heading, 0, 2, 'L');
        $pdf->SetFont('helvetica', '', $value_pt);
        foreach ($lines as $line) {
            $pdf->SetX($x);
            $pdf->Cell($w, $value_pt * 0.42, $line, 0, 2, 'L');
        }
        return $pdf->GetY() + 2.6;
    };

    // The receipt - 62 mm
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Text(5, $top + 5, t('Receipt{qr_bill}', ['{qr_bill}' => ''])  /* the slip's title is "Récépissé" in French, not the "Reçu" of a payment receipt - the key differs for that */);
    $y = $block(5, $top + 12, 52, t('Account / Payable to'), $creditor_lines, 6, 8);
    $y = $block(5, $y, 52, t('Reference'), [$reference], 6, 8);
    if ($debtor_lines) {
        $block(5, $y, 52, t('Payable by'), $debtor_lines, 6, 8);
    } else {
        $block(5, $y, 52, t('Payable by (name/address)'), [], 6, 8);
        qrBillCorners($pdf, 5, $y + 5, 52, 20);
    }
    $pdf->SetFont('helvetica', 'B', 6);
    $pdf->Text(5, $top + 87, t('Currency'));
    $pdf->Text(23, $top + 87, t('Amount'));
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Text(5, $top + 91, $bill['currency']);
    if ($amount_text !== '') {
        $pdf->Text(23, $top + 91, $amount_text);
    } else {
        qrBillCorners($pdf, 23, $top + 85, 30, 10);
    }
    $pdf->SetFont('helvetica', 'B', 6);
    $pdf->SetXY(5, $top + 97);
    $pdf->Cell(52, 3, t('Acceptance point'), 0, 0, 'R');

    // The payment part - 148 mm
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Text(67, $top + 5, t('Payment part'));
    $pdf->write2DBarcode(qrBillPayload($bill), 'QRCODE,M', 67, $top + 17, 46, 46, ['border' => false, 'padding' => 0, 'fgcolor' => [0, 0, 0], 'bgcolor' => false], 'N');
    qrBillCross($pdf, 67 + 23, $top + 17 + 23);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Text(67, $top + 68, t('Currency'));
    $pdf->Text(90, $top + 68, t('Amount'));
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Text(67, $top + 73, $bill['currency']);
    if ($amount_text !== '') {
        $pdf->Text(90, $top + 73, $amount_text);
    } else {
        qrBillCorners($pdf, 90, $top + 67, 40, 15);
    }
    $y = $block(118, $top + 5, 87, t('Account / Payable to'), $creditor_lines, 8, 10);
    $y = $block(118, $y, 87, t('Reference'), [$reference], 8, 10);
    $y = $block(118, $y, 87, t('Additional information'), [$info], 8, 10);
    if ($debtor_lines) {
        $block(118, $y, 87, t('Payable by'), $debtor_lines, 8, 10);
    } else {
        $block(118, $y, 87, t('Payable by (name/address)'), [], 8, 10);
        qrBillCorners($pdf, 118, $y + 6, 65, 25);
    }
}
