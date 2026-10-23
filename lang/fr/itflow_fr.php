<?php
/**
 * ITFlow - Traduction Française
 * 
 * File: lang/fr/itflow_fr.php
 * Description: Traductions françaises pour ITFlow
 * Created: 2026-10-23
 * Author: Hermes Agent (Jilian) - Proxycore
 */

$itflow_translations = [
    'Accept Quote' => 'Accepter le Devis',
    'Active' => 'Actif',
    'Add' => 'Ajouter',
    'Add New' => 'Ajouter Nouveau',
    'Address' => 'Adresse',
    'Admin' => 'Administration',
    'Agent' => 'Agent',
    'Amount' => 'Montant',
    'April' => 'Avril',
    'Are you sure?' => 'Êtes-vous sûr ?',
    'Asset' => 'Asset',
    'Assets' => 'Assets',
    'Assigned To' => 'Assigné à',
    'August' => 'Août',
    'Back' => 'Retour',
    'Balance Due' => 'Solde Dû',
    'Bill To' => 'Facturer à',
    'Cancel' => 'Annuler',
    'City' => 'Ville',
    'Client' => 'Client',
    'Clients' => 'Clients',
    'Close' => 'Fermer',
    'Closed' => 'Fermé',
    'Comments' => 'Commentaires',
    'Company' => 'Entreprise',
    'Confirm' => 'Confirmer',
    'Contacts' => 'Contacts',
    'Country' => 'Pays',
    'Create' => 'Créer',
    'Dashboard' => 'Tableau de Bord',
    'December' => 'Décembre',
    'Decline Quote' => 'Refuser le Devis',
    'Delete' => 'Supprimer',
    'Description' => 'Description',
    'Document' => 'Document',
    'Documents' => 'Documents',
    'Download' => 'Télécharger',
    'Download PDF' => 'Télécharger PDF',
    'Due Date' => 'Date d\'Échéance',
    'Edit' => 'Modifier',
    'Email' => 'Email',
    'Email Address' => 'Adresse Email',
    'Error' => 'Erreur',
    'Export' => 'Exporter',
    'February' => 'Février',
    'File' => 'Fichier',
    'First Name' => 'Prénom',
    'From' => 'De',
    'High' => 'Haute',
    'History' => 'Historique',
    'Home' => 'Accueil',
    'Import' => 'Importer',
    'Inactive' => 'Inactif',
    'Info' => 'Information',
    'Invoice' => 'Facture',
    'Invoice Date' => 'Date de Facture',
    'Invoice Number' => 'Numéro de Facture',
    'Invoices' => 'Factures',
    'Item' => 'Article',
    'January' => 'Janvier',
    'July' => 'Juillet',
    'June' => 'Juin',
    'Last Name' => 'Nom de Famille',
    'Login' => 'Connexion',
    'Logout' => 'Déconnexion',
    'Low' => 'Basse',
    'March' => 'Mars',
    'May' => 'Mai',
    'Medium' => 'Moyenne',
    'Model' => 'Modèle',
    'Name' => 'Nom',
    'New' => 'Nouveau',
    'Next' => 'Suivant',
    'No' => 'Non',
    'No records found' => 'Aucun enregistrement trouvé',
    'Notes' => 'Notes',
    'November' => 'Novembre',
    'OK' => 'OK',
    'October' => 'Octobre',
    'Open' => 'Ouvert',
    'Page' => 'Page',
    'Paid' => 'Payé',
    'Password' => 'Mot de passe',
    'Pay Invoice' => 'Payer la Facture',
    'Pending' => 'En Attente',
    'Phone' => 'Téléphone',
    'Please fill all required fields' => 'Veuillez remplir tous les champs requis',
    'Postal Code' => 'Code Postal',
    'Previous' => 'Précédent',
    'Price' => 'Prix',
    'Priority' => 'Priorité',
    'Profile' => 'Profil',
    'Purchase Date' => 'Date d\'Achat',
    'Quantity' => 'Quantité',
    'Quote' => 'Devis',
    'Quote Number' => 'Numéro de Devis',
    'Quotes' => 'Devis',
    'Receive' => 'Recevoir',
    'Record deleted successfully' => 'Enregistrement supprimé avec succès',
    'Record saved successfully' => 'Enregistrement enregistré avec succès',
    'Save' => 'Enregistrer',
    'Search' => 'Rechercher',
    'Send' => 'Envoyer',
    'September' => 'Septembre',
    'Serial' => 'Numéro de Série',
    'Settings' => 'Paramètres',
    'Sign In' => 'Se connecter',
    'Size' => 'Taille',
    'Something went wrong' => 'Une erreur s\'est produite',
    'Status' => 'Statut',
    'Subject' => 'Sujet',
    'Submit' => 'Envoyer',
    'Subtotal' => 'Sous-Total',
    'Success' => 'Succès',
    'Tax' => 'Taxe',
    'Thank you for your business' => 'Merci de votre confiance',
    'Ticket' => 'Ticket',
    'Tickets' => 'Tickets',
    'To' => 'À',
    'Total' => 'Total',
    'Type' => 'Type',
    'Unassigned' => 'Non Assigné',
    'Unpaid' => 'Impayé',
    'Update' => 'Mettre à jour',
    'Upload' => 'Téléverser',
    'Uploaded' => 'Téléversé',
    'Urgent' => 'Urgente',
    'Valid Until' => 'Valide Jusqu\'au',
    'View' => 'Voir',
    'Warning' => 'Avertissement',
    'Warranty' => 'Garantie',
    'Yes' => 'Oui',
    'of' => 'sur',
];

/**
 * Fonction de traduction
 * @param string $msgid String à traduire
 * @return string Traduction ou string original si non trouvé
 */
if (!function_exists('__')) {
    function __($msgid) {
        global $itflow_translations;
        return $itflow_translations[$msgid] ?? $msgid;
    }
}

/**
 * Alias pour trans()
 */
if (!function_exists('trans')) {
    function trans($msgid) {
        return __($msgid);
    }
}

/**
 * Fonction pour afficher une string traduite
 * @param string $msgid String à traduire
 */
if (!function_exists('e_trans')) {
    function e_trans($msgid) {
        echo __($msgid);
    }
}

/**
 * Fonction pour traduire le HTML complet (pour PDF)
 * @param string $html HTML à traduire
 * @return string HTML traduit
 */
if (!function_exists('translate_html')) {
    function translate_html($html) {
        global $itflow_translations;
        
        foreach ($itflow_translations as $msgid => $translation) {
            $html = str_replace($msgid, $translation, $html);
        }
        
        return $html;
    }
}
