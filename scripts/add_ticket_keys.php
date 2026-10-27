<?php
$fr = require 'lang/fr.php';

// New French keys with typographic apostrophe ' (U+2019)
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

$fr = array_merge($fr, $new_fr);
file_put_contents('lang/fr.php', '<?php' . PHP_EOL . 'return ' . var_export($fr, true) . ';' . PHP_EOL);
echo 'Done: ' . count($fr) . ' keys' . PHP_EOL;
