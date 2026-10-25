<?php
/**
 * i18n_exceptions.php
 * 
 * Liste des exceptions pour l'i18n.
 * Ces textes sont assemblés dynamiquement et ne doivent pas être traduits.
 * 
 * Usage: Inclus par i18n_extract_texts.php pour exclure ces textes.
 */

// Stop if accessed via web (scripts/ is web-accessible)
if (PHP_SAPI !== 'cli') {
    exit;
}

return [
    // Textes assemblés dynamiquement (ne pas traduire)
    'Client Email' => 'Text assembled at display time (login.php line 736)',
];
