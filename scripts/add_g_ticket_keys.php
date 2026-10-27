<?php
// Add new keys to lang files for G-ticket

$en = require 'lang/en.php';
$fr = require 'lang/fr.php';

// New English keys
$new_en = [
    'New' => 'New',
    'Open' => 'Open',
    'On Hold' => 'On Hold',
    'Resolved' => 'Resolved',
    'Closed' => 'Closed',
    'Low' => 'Low',
    'Medium' => 'Medium',
    'High' => 'High',
    'Critical' => 'Critical',
    'Ticket' => 'Ticket',
    'State:' => 'State:',
    'Priority:' => 'Priority:',
    'Assigned to:' => 'Assigned to:',
    'Please {link} or reply to the ticket via email to respond' => 'Please {link} or reply to the ticket via email to respond',
    'log in' => 'log in',
    'Your ticket has been resolved' => 'Your ticket has been resolved',
    'Reopen ticket' => 'Reopen ticket',
    'Close ticket' => 'Close ticket',
    'Ticket closed. Please rate your ticket' => 'Ticket closed. Please rate your ticket',
    'Good' => 'Good',
    'Bad' => 'Bad',
    'Rated {feedback} -- Thanks for your feedback!' => 'Rated {feedback} -- Thanks for your feedback!',
    'Task Approval' => 'Task Approval',
    'You have been asked to approve the following task' => 'You have been asked to approve the following task',
    'Approve task' => 'Approve task',
    'Approve this task?' => 'Approve this task?',
    'Yes, approve' => 'Yes, approve',
    'Not expecting this? Get in touch using the details below.' => 'Not expecting this? Get in touch using the details below.',
    'Approved' => 'Approved',
    'nothing further is needed.' => 'nothing further is needed.',
    'this task was not approved.' => 'this task was not approved.',
    'Ticket details' => 'Ticket details',
    'Ticket reopened' => 'Ticket reopened',
    'Feedback recorded - thank you' => 'Feedback recorded - thank you',
    'Cannot find/approve that task' => 'Cannot find/approve that task',
    'Task Approved' => 'Task Approved',
];

// New French keys with escaped apostrophes (will be converted to typographic later)
$new_fr = [
    'New' => 'Nouveau',
    'Open' => 'Ouvert',
    'On Hold' => 'En attente',
    'Resolved' => 'Résolu',
    'Closed' => 'Fermé',
    'Low' => 'Faible',
    'Medium' => 'Moyenne',
    'High' => 'Haute',
    'Critical' => 'Critique',
    'Ticket' => 'Ticket',
    'State:' => 'État :',
    'Priority:' => 'Priorité :',
    'Assigned to:' => 'Assigné à :',
    'Please {link} or reply to the ticket via email to respond' => 'Pour répondre, veuillez {link} ou répondre à ce ticket par e-mail.',
    'log in' => 'vous connecter',
    'Your ticket has been resolved' => 'Votre ticket a été résolu',
    'Reopen ticket' => 'Rouvrir le ticket',
    'Close ticket' => 'Fermer le ticket',
    'Ticket closed. Please rate your ticket' => 'Ticket fermé. Merci d\'évaluer votre ticket',
    'Good' => 'Bien',
    'Bad' => 'Mauvais',
    'Rated {feedback} -- Thanks for your feedback!' => 'Évaluation : {feedback} -- Merci pour votre avis !',
    'Task Approval' => 'Approbation de tâche',
    'You have been asked to approve the following task' => 'Votre approbation est demandée pour la tâche suivante',
    'Approve task' => 'Approuver la tâche',
    'Approve this task?' => 'Approuver cette tâche ?',
    'Yes, approve' => 'Oui, approuver',
    'Not expecting this? Get in touch using the details below.' => 'Vous n\'attendiez pas ce message ? Contactez-nous avec les coordonnées ci-dessous.',
    'Approved' => 'Approuvé',
    'nothing further is needed.' => 'aucune autre action n\'est nécessaire.',
    'this task was not approved.' => 'cette tâche n\'a pas été approuvée.',
    'Ticket details' => 'Détails du ticket',
    'Ticket reopened' => 'Ticket rouvert',
    'Feedback recorded - thank you' => 'Avis enregistré, merci',
    'Cannot find/approve that task' => 'Tâche introuvable ou impossible à approuver',
    'Task Approved' => 'Tâche approuvée',
];

// Merge
$en = array_merge($en, $new_en);
$fr = array_merge($fr, $new_fr);

// Write en.php (var_export keeps straight apostrophes)
file_put_contents('lang/en.php', "<?php\nreturn " . var_export($en, true) . ";\n");

// Write fr.php - convert escaped apostrophes to typographic after var_export
$fr_content = "<?php\nreturn " . var_export($fr, true) . ";\n";
// Only convert \\' in values (after =>), not in keys
$fr_content = preg_replace("/(=> '.*?)\\\\'(.*?)'/s", "$1'" . chr(0xE2) . chr(0x80) . chr(0x99) . "$2'", $fr_content);

file_put_contents('lang/fr.php', $fr_content);

echo "Done: " . count($en) . " keys in en.php, " . count($fr) . " keys in fr.php\n";
