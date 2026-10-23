<?php
/**
 * ITFlow - Template de Devis PDF (Traduit en Français)
 * 
 * Fichier modifié pour générer des devis en français.
 * Utilise le système de traduction comprehensive_translations.php
 */

require_once __DIR__ . '/../lang/fr/comprehensive_translations.php';

// Fonction pour traduire le contenu HTML avant génération PDF
function translateQuoteHTML($html) {
    global $client_translations;
    
    $replacements = [
        'Quote' => trans('Quote'),
        'Quote Number' => trans('Quote Number'),
        'Quote Date' => trans('Quote Date'),
        'Valid Until' => trans('Valid Until'),
        'Accept Quote' => trans('Accept Quote'),
        'Decline Quote' => trans('Decline Quote'),
        'Item' => trans('Item'),
        'Description' => trans('Description'),
        'Quantity' => trans('Quantity'),
        'Price' => trans('Price'),
        'Total' => trans('Total'),
        'Bill To' => trans('Bill To'),
        'From' => trans('From'),
        'Page' => trans('Page'),
        'of' => trans('of'),
    ];
    
    foreach ($replacements as $en => $fr) {
        $html = str_replace($en, $fr, $html);
    }
    
    return $html;
}
