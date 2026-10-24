<?php
/**
 * ITFlow - French Email Templates
 * 
 * Translations for emails sent to clients
 */

$email_templates = [
    "Nouveau Ticket - {client}: {subject}" => "Nouveau Ticket - {client}: {subject}",
    "Ticket Scheduled" => "Ticket Planifié",
    "Statut" => "Statut",
    "Portal" => "Portail",
    "A new ticket has been created" => "Un nouveau ticket a été créé",
    "Ticket Details" => "Détails du Ticket",
    "Thank you for contacting us" => "Merci de nous avoir contactés",
    "Your ticket has been updated" => "Votre ticket a été mis à jour",
    "Ticket Mettre à jour" => "Mise à Jour du Ticket",
    "Nouveau Invoice" => "Nouvelle Facture",
    "Invoice Ready" => "Facture Prête",
    "You have a new invoice" => "Vous avez une nouvelle facture",
    "Invoice Payment Reminder" => "Rappel de Paiement de Facture",
    "Invoice Paid" => "Facture Payée",
    "Your invoice has been paid" => "Votre facture a été payée",
    "Nouveau Quote" => "Nouveau Devis",
    "Quote Ready" => "Devis Prêt",
    "You have a new quote" => "Vous avez un nouveau devis",
    "Quote Accepted" => "Devis Accepté",
    "Quote Declined" => "Devis Refusé",
    "Hello" => "Bonjour",
    "Dear" => "Cher/Chère",
    "Best regards" => "Cordialement",
    "Sincerely" => "Sincèrement",
    "Thank you" => "Merci",
    "Please contact us" => "Veuillez nous contacter",
    "If you have any questions" => "Si vous avez des questions",
    "Click here" => "Cliquez ici",
    "Voir in portal" => "Voir dans le portail",
    "Login to your account" => "Connectez-vous à votre compte",
    "This email was sent from" => "Cet email a été envoyé depuis",
    "If you did not request this" => "Si vous n'avez pas demandé cela",
    "Unsubscribe" => "Se désabonner",
];

// Helper to translate email content
function translate_email($text) {
    global $email_templates;
    
    // Replace variables like {client}, {subject}, etc.
    foreach ($email_templates as $eng => $fr) {
        $text = str_replace($eng, $fr, $text);
    }
    
    return $text;
}
