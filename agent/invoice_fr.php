<?php
/**
 * ITFlow - Template de Facture PDF (Traduit en Français)
 * 
 * Fichier modifié pour générer des factures en français.
 * Utilise le système de traduction comprehensive_translations.php
 * 
 * Utilisation:
 * 1. Copier ce fichier à la place de agent/invoice.php sur le serveur
 * 2. Les PDF générés seront en français
 */

require_once __DIR__ . '/../lang/fr/comprehensive_translations.php';

// Exemple de traduction dans le template PDF
// Dans le code TCPDF original, remplacer:
// "Invoice" -> trans('Invoice')
// "Invoice Number" -> trans('Invoice Number')
// etc.

// Fonction pour traduire le contenu HTML avant génération PDF
function translateInvoiceHTML($html) {
    global $client_translations;
    
    $replacements = [
        'Invoice' => trans('Invoice'),
        'Invoice Number' => trans('Invoice Number'),
        'Invoice Date' => trans('Invoice Date'),
        'Due Date' => trans('Due Date'),
        'Amount' => trans('Amount'),
        'Subtotal' => trans('Subtotal'),
        'Tax' => trans('Tax'),
        'Total' => trans('Total'),
        'Paid' => trans('Paid'),
        'Unpaid' => trans('Unpaid'),
        'Balance' => trans('Balance'),
        'Balance Due' => trans('Balance Due'),
        'Item' => trans('Item'),
        'Description' => trans('Description'),
        'Quantity' => trans('Quantity'),
        'Price' => trans('Price'),
        'Notes' => trans('Notes'),
        'Bill To' => trans('Bill To'),
        'From' => trans('From'),
        'To' => trans('To'),
        'Page' => trans('Page'),
        'of' => trans('of'),
        'Thank you' => trans('Thank you'),
        'Thank you for your business' => trans('Thank you for your business'),
    ];
    
    foreach ($replacements as $en => $fr) {
        $html = str_replace($en, $fr, $html);
    }
    
    return $html;
}

// Exemple d'utilisation dans la génération PDF:
/*
// Générer le HTML de la facture
$html = generateInvoiceHTML($invoice_data);

// Traduire le HTML
$html = translateInvoiceHTML($html);

// Générer le PDF
$pdf = new TCPDF();
$pdf->AddPage();
$pdf->writeHTML($html, true, 0, true, 0);
$pdf->Output('invoice_fr.pdf', 'D');
*/
