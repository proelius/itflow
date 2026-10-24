<?php
/**
 * ITFlow - Traductions Complètes Portail Client & PDF
 * 
 * Ce fichier contient TOUTES les traductions pour le portail client
 * et les PDF (factures, devis, relevés).
 * 
 * Utilisation:
 * 1. Pour le portail client HTML: utiliser trans('String')
 * 2. Pour les PDF: inclure ce fichier et utiliser les traductions directes
 */

// Traductions pour le portail client
$client_translations = [
    'Home' => 'Accueil',
    'Tickets' => 'Tickets',
    'Finance' => 'Finance',
    'Factures' => 'Factures',
    'Recurring Factures' => 'Factures Récurrentes',
    'Devis' => 'Devis',
    'Account Statement' => 'Relevé de Compte',
    'Enregistrerd Payments' => 'Paiements Enregistrés',
    'Documents' => 'Documents',
    'Contacts' => 'Contacts',
    'Domains' => 'Domaines',
    'Certificates' => 'Certificats',
    'Actifs' => 'Actifs',
    'Activity' => 'Activité',
    'Profile' => 'Profil',
    'Logout' => 'Déconnexion',
    'Sign Out' => 'Se Déconnecter',
    'Client Portal' => 'Portail Client',
    'Tableau de bord' => 'Tableau de Bord',
    'Invoice' => 'Facture',
    'Invoice Number' => 'Numéro de Facture',
    'Invoice Date' => 'Date de Facture',
    'Due Date' => 'Date d'Échéance',
    'Amount' => 'Montant',
    'Subtotal' => 'Sous-Total',
    'Tax' => 'Taxe',
    'Total' => 'Total',
    'Paid' => 'Payé',
    'Unpaid' => 'Impayé',
    'Partial' => 'Partiel',
    'Balance' => 'Solde',
    'Balance Due' => 'Solde Dû',
    'Pay Invoice' => 'Payer la Facture',
    'Télécharger PDF' => 'Télécharger PDF',
    'Voir Invoice' => 'Voir la Facture',
    'Invoice Total' => 'Total Facture',
    'Item' => 'Article',
    'Description' => 'Description',
    'Quantity' => 'Quantité',
    'Price' => 'Prix',
    'Notes' => 'Notes',
    'Bill To' => 'Facturer à',
    'Ship To' => 'Livrer à',
    'From' => 'De',
    'To' => 'À',
    'Page' => 'Page',
    'of' => 'sur',
    'Generated' => 'Généré',
    'Generated on' => 'Généré le',
    'Thank you' => 'Merci',
    'Thank you for your business' => 'Merci de votre confiance',
    'Thank you for contacting us' => 'Merci de nous avoir contactés',
    'Quote' => 'Devis',
    'Quote Number' => 'Numéro de Devis',
    'Quote Date' => 'Date de Devis',
    'Valid Until' => 'Valide Jusqu'au',
    'Accept Quote' => 'Accepter le Devis',
    'Decline Quote' => 'Refuser le Devis',
    'Voir Quote' => 'Voir le Devis',
    'Nouveau Ticket' => 'Nouveau Ticket',
    'Créer Ticket' => 'Créer un Ticket',
    'Ticket Subject' => 'Sujet du Ticket',
    'Priority' => 'Priorité',
    'Statut' => 'Statut',
    'Ouvert' => 'Ouvert',
    'Fermerd' => 'Fermé',
    'En Pause' => 'En Attente',
    'Assigned' => 'Assigné',
    'Unassigned' => 'Non Assigné',
    'Voir Ticket' => 'Voir le Ticket',
    'Ajouter Ticket' => 'Ajouter un Ticket',
    'Ticket Number' => 'Numéro de Ticket',
    'Créerd' => 'Créé',
    'Mettre à jourd' => 'Mis à Jour',
    'Attachments' => 'Pièces Jointes',
    'Ajouter Attachment' => 'Ajouter une Pièce Jointe',
    'Reply' => 'Répondre',
    'Fermer Ticket' => 'Fermer le Ticket',
    'All tickets' => 'Tous les tickets',
    'No tickets found' => 'Aucun ticket trouvé',
    'Document' => 'Document',
    'Téléverser Document' => 'Téléverser un Document',
    'Télécharger' => 'Télécharger',
    'Supprimer' => 'Supprimer',
    'Modifier' => 'Modifier',
    'Enregistrer' => 'Enregistrer',
    'Annuler' => 'Annuler',
    'Contact' => 'Contact',
    'Ajouter Contact' => 'Ajouter un Contact',
    'Modifier Contact' => 'Modifier le Contact',
    'Name' => 'Nom',
    'Email' => 'Email',
    'Phone' => 'Téléphone',
    'Primary' => 'Principal',
    'Technical' => 'Technique',
    'Billing' => 'Facturation',
    'Asset' => 'Asset',
    'Asset Name' => 'Nom de l'Asset',
    'Asset Type' => 'Type d'Asset',
    'Serial Number' => 'Numéro de Série',
    'Purchase Date' => 'Date d'Achat',
    'Warranty' => 'Garantie',
    'Certificate' => 'Certificat',
    'Certificate Name' => 'Nom du Certificat',
    'Expires' => 'Expire',
    'Actif' => 'Actif',
    'Inactif' => 'Inactif',
    'Archived' => 'Archivé',
    'Domain' => 'Domaine',
    'Domain Name' => 'Nom de Domaine',
    'Registrar' => 'Registaire',
    'Expiration Date' => 'Date d'Expiration',
    'Login' => 'Connexion',
    'Email Ajouterress' => 'Adresse Email',
    'Password' => 'Mot de passe',
    'Sign In' => 'Se connecter',
    'Forgot Password?' => 'Mot de passe oublié ?',
    'Reset Password' => 'Réinitialiser le mot de passe',
    'Enter your email' => 'Entrez votre email',
    'Send Reset Link' => 'Envoyer le lien de réinitialisation',
    'Two-Factor Authentication' => 'Authentification à Deux Facteurs',
    'Enable 2FA' => 'Actifr 2FA',
    'Disable 2FA' => 'Désactiver 2FA',
    'Welcome' => 'Bienvenue',
    'Hello' => 'Bonjour',
    'Loading...' => 'Chargement...',
    'Please wait' => 'Veuillez patienter',
    'Are you sure?' => 'Êtes-vous sûr ?',
    'Yes' => 'Oui',
    'No' => 'Non',
    'Succès' => 'Succès',
    'Erreur' => 'Erreur',
    'Avertissement' => 'Avertissement',
    'Info' => 'Information',
    'Alert' => 'Alerte',
    'Rechercher' => 'Rechercher',
    'Filtrer' => 'Filtrer',
    'Sort' => 'Trier',
    'Show' => 'Afficher',
    'Hide' => 'Masquer',
    'Voir' => 'Voir',
    'Ajouter' => 'Ajouter',
    'Remove' => 'Retirer',
    'Fermer' => 'Fermer',
    'Back' => 'Retour',
    'Next' => 'Suivant',
    'Previous' => 'Précédent',
    'First' => 'Premier',
    'Last' => 'Dernier',
];

// Alias pour compatibilité
$translations = $client_translations;

// Fonction de traduction
if (!function_exists('trans')) {
    function trans($key) {
        global $client_translations;
        return $client_translations[$key] ?? $key;
    }
}

if (!function_exists('__')) {
    function __($key) {
        return trans($key);
    }
}

// Fonction pour traduire le contenu PDF
if (!function_exists('translate_pdf')) {
    function translate_pdf($html) {
        global $client_translations;
        
        foreach ($client_translations as $eng => $fr) {
            $html = str_replace($eng, $fr, $html);
        }
        
        return $html;
    }
}
