<?php
/**
 * ITFlow - Intégration de la traduction française
 * 
 * File: lang/fr/itflow_integration.php
 * Description: Charge et configure la traduction française
 */

// Charger les traductions
require_once __DIR__ . '/itflow_fr.php';

// Vérifier si la langue française est activée
if (isset($session_company_locale) && strpos($session_company_locale, 'fr') !== false) {
    $language_active = 'fr';
} else {
    $language_active = 'en';  // Défaut
}

// Définir la langue pour PHP
if ($language_active === 'fr') {
    setlocale(LC_ALL, 'fr_FR.UTF-8');
    date_default_timezone_set('Europe/Zurich');
}
