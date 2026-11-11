<?php

/*
 * Bank statement import - what this fork adds to functions/expense_import.php and
 * functions/income_import.php, kept here so those files change by a line or two and an
 * update of ITFlow merges without trouble. Each hook there is one call into this file.
 *
 * A UBS-style CSV: notes before the header (an IBAN, "Du:" and "Au:" dates), a transaction
 * written over two lines (the second with the QR reference), French column names.
 * A deposit with the QR reference of an open invoice (functions/qr_bill.php) is matched to it.
 */

/**
 * Where the data starts when the notes above the header have dates in them: the first line after the
 * first with most of its cells filled in and no date among them. $first_data as it was found otherwise.
 */
function bankImportFindData(array $rows, $first_data) {
    if ($first_data > 0 && count(array_filter($rows[$first_data - 1], 'strlen')) < 3) {
        foreach (array_slice($rows, 0, 40, true) as $i => $cells) {
            $filled = array_filter($cells, 'strlen');
            if (count($filled) < 4 || count(array_filter($filled, 'expenseImportLooksLikeDate')) > 0) {
                continue;
            }
            foreach (array_slice($rows, $i + 1, 40, true) as $j => $next) {
                if (array_filter($next, 'expenseImportLooksLikeDate')) {
                    return $j;
                }
            }
        }
    }
    return $first_data;
}

/**
 * A transaction written over two lines - the second with no date and a cell the same as the first's (its
 * transaction number) - as one: what the second adds is put after what the first has.
 */
function bankImportJoinLines(array $data) {
    $joined = [];
    foreach ($data as $cells) {
        $last = count($joined) - 1;
        if ($last >= 0 && !array_filter($cells, 'expenseImportLooksLikeDate') && array_filter($joined[$last], 'expenseImportLooksLikeDate')) {
            $shared = false;
            foreach ($cells as $c => $cell) {
                if (strlen($cell) >= 6 && $cell === $joined[$last][$c]) {
                    $shared = true;
                    break;
                }
            }
            if ($shared) {
                foreach ($cells as $c => $cell) {
                    if ($cell === '' || $cell === $joined[$last][$c]) {
                        continue;
                    }
                    $joined[$last][$c] = $joined[$last][$c] === '' ? $cell : (preg_match('/[a-z]/i', $cell) ? $joined[$last][$c] . ' | ' . $cell : $joined[$last][$c]);
                }
                continue;
            }
        }
        $joined[] = $cells;
    }
    return $joined;
}

/** The header names the guess knows, with the French ones a Swiss bank uses added - field => pattern. */
function bankImportColumnPatterns(array $patterns) {
    $more = [
        'date' => 'date de (transaction|comptabilisation|valeur)',
        'description' => 'description ?1|libell(e|é)',
        'memo' => 'description ?3',
        'amount' => 'montant',
        'debit' => 'd(e|é)bit',
        'credit' => 'cr(e|é)dit',
        'reference' => 'r(e|é)f(e|é)rence|no de transaction|num(e|é)ro de transaction',
    ];
    foreach ($more as $field => $alternatives) {
        // The pattern is /^(...)$/i - the alternatives go in as a group of their own
        $patterns[$field] = preg_replace('~/i$~', '', $patterns[$field]) . '|^(' . $alternatives . ')$/i';
    }
    return $patterns;
}

/**
 * The invoices a deposit names - [how ('qr' or 'number'), invoices]. By the QR reference of an invoice
 * (qrBillReference() of its number) in its text, else by an invoice number in it - "Facture INV-6", with
 * the memo of the line in the text too, where a bank puts the payer's own words. $open as
 * incomeImportOpenInvoices() lists them, $prefixes the invoice prefixes there are.
 */
function bankImportNamedInvoices(array $line, array $open, array $prefixes) {
    $text = trim($line['description'] . ' ' . $line['reference']);
    $named = [];
    foreach (qrBillReferencesIn($text) as $reference) {
        foreach ($open as $invoice) {
            if (qrBillReference($invoice['number']) === $reference) {
                $named[] = $invoice;
            }
        }
    }
    if ($named) {
        return ['qr', $named];
    }
    foreach (incomeImportInvoiceNumbers($text, array_merge($prefixes, ['facture', 'factures'])) as $number) {
        foreach ($open as $invoice) {
            if ($invoice['number'] === $number) {
                $named[] = $invoice;
            }
        }
    }
    return ['number', $named];
}
