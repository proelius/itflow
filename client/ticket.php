<?php
/*
 * Client Portal
 * A ticket - where it stands (received, in progress, resolved, closed), the
 * conversation oldest first with the client's own messages on the right,
 * approvals waiting on them, the details down the side, and what they can do
 * next: reply with files, mark it resolved, close or reopen it, rate it.
 *
 * SCOPING: the ticket is read for the session's client, and only if it is the
 * contact's own unless they see the company's (portalTicketScopeSql()), the
 * same rule verifyContactTicketAccess() holds the handlers to.
 *
 * No Content-Security-Policy here: the editor needs inline styles.
 */

require_once "includes/inc_all.php";

//Initialize the HTML Purifier to prevent XSS
require "../libs/htmlpurifier/HTMLPurifier.standalone.php";

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('Cache.DefinitionImpl', null); // Disable cache by setting a non-existent directory or an invalid one
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

$ticket_id = intval($_GET['id'] ?? 0);
$ticket = $ticket_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT " . portalTicketColumnsSql() . ", tickets.ticket_details, tickets.ticket_feedback,
        tickets.ticket_assigned_to, tickets.ticket_asset_id, user_name, user_avatar, category_name, contact_name, contact_photo, asset_name, asset_type
    FROM tickets
    LEFT JOIN users ON ticket_assigned_to = user_id
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    LEFT JOIN categories ON ticket_category = category_id
    LEFT JOIN contacts ON contact_id = tickets.ticket_contact_id
    LEFT JOIN assets ON asset_id = tickets.ticket_asset_id AND asset_client_id = tickets.ticket_client_id
    WHERE tickets.ticket_id = $ticket_id AND " . portalTicketScopeSql() . " LIMIT 1")) : null;

if (!$ticket) {
    ?>
    <a class="itflow-portal-back" href="tickets.php"><i class="fas fa-chevron-left"></i><?= t('Tickets') ?></a>
    <section class="itflow-portal-panel">
        <?= emptyStateHtml(['title' => t('That ticket isn\'t here'), 'text' => t('It may belong to someone else at your company, or the link is out of date.'), 'icon' => 'fa-life-ring', 'filtered' => false,
            'actions' => [['label' => t('Your tickets'), 'href' => 'tickets.php', 'icon' => 'fa-list']]]) ?>
    </section>
    <?php
    require_once "includes/footer.php";
    exit;
}

$state = portalTicketState($ticket);
$number = $ticket['ticket_prefix'] . $ticket['ticket_number'];
$resolved = !empty($ticket['ticket_resolved_at']);
$closed = !empty($ticket['ticket_closed_at']);
$csrf = $_SESSION['csrf_token'];

// Tasks - how far along, and the ones waiting on a client's approval
$tasks = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS total, COALESCE(SUM(task_completed_at IS NOT NULL), 0) AS done FROM tasks WHERE task_ticket_id = $ticket_id"));
$task_count = intval($tasks['total']);
$task_done = intval($tasks['done']);
$approvals = [];
$sql = mysqli_query($mysqli, "SELECT task_id, task_name, approval_id, approval_type, approval_url_key FROM tasks
    JOIN task_approvals ON task_id = approval_task_id
    WHERE task_ticket_id = $ticket_id AND task_completed_at IS NULL AND approval_scope = 'client' AND approval_status = 'pending' ORDER BY task_order ASC, task_id ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $type = (string) $row['approval_type'];
    $row['can_approve'] = $type === 'any' || $session_contact_primary
        || ($type === 'technical' && $session_contact_is_technical_contact) || ($type === 'billing' && $session_contact_is_billing_contact);
    $approvals[] = $row;
}

// Attachments, by the reply they came with (0 for the ticket itself) - never an internal note's
$attachments = [];
$sql = mysqli_query($mysqli, "SELECT ticket_attachment_id, ticket_attachment_name, ticket_attachment_reply_id FROM ticket_attachments
    LEFT JOIN ticket_replies ON ticket_reply_id = ticket_attachment_reply_id
    WHERE ticket_attachment_ticket_id = $ticket_id
    AND (ticket_attachment_reply_id IS NULL OR (ticket_reply_type != 'Internal' AND ticket_reply_archived_at IS NULL))
    ORDER BY ticket_attachment_id ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $attachments[intval($row['ticket_attachment_reply_id'])][] = $row;
}

// The conversation, oldest first
$replies = mysqli_query($mysqli, "SELECT ticket_reply_id, ticket_reply, ticket_reply_type, ticket_reply_by, ticket_reply_created_at,
        contact_name, contact_photo, user_id, user_name, user_avatar
    FROM ticket_replies
    LEFT JOIN users ON ticket_reply_type != 'Client' AND ticket_reply_by = user_id
    LEFT JOIN contacts ON ticket_reply_type = 'Client' AND ticket_reply_by = contact_id
    WHERE ticket_reply_ticket_id = $ticket_id AND ticket_reply_archived_at IS NULL AND ticket_reply_type != 'Internal'
    ORDER BY ticket_reply_id ASC");

/** One message in the conversation. */
function portalMessageHtml($name, $avatar_url, $when, $body_html, array $files, $mine, $agent) {
    $html = '<div class="itflow-portal-msg' . ($mine ? ' itflow-portal-msg-mine' : '') . '">'
        . portalAvatarHtml($name, $avatar_url, 'lg')
        . '<div class="itflow-portal-msg-body">'
        . '<div class="itflow-portal-msg-head"><strong>' . escapeHtml($mine ? t('You') : $name) . '</strong>'
        . ($agent ? '<span>' . escapeHtml($GLOBALS['session_company_name']) . '</span>' : '')
        . '<span title="' . escapeHtml($when) . '">' . portalDateTime($when) . '</span></div>'
        . '<div class="itflow-portal-msg-bubble prettyContent">' . $body_html;
    if ($files) {
        $html .= '<div class="itflow-portal-files">';
        foreach ($files as $file) {
            $id = intval($file['ticket_attachment_id']);
            $html .= '<a class="itflow-portal-file-chip" href="ticket_attachment.php?attachment_id=' . $id . '&amp;action=view" target="_blank" rel="noopener" title="' . escapeHtml(t('Open')) . '">'
                . '<i class="fas fa-paperclip"></i><span class="text-truncate">' . escapeHtml($file['ticket_attachment_name']) . '</span></a>'
                . '<a class="itflow-portal-file-chip" href="ticket_attachment.php?attachment_id=' . $id . '" title="' . escapeHtml(t('Download {name}', ['{name}' => $file['ticket_attachment_name']])) . '" aria-label="' . escapeHtml(t('Download')) . '"><i class="fas fa-download"></i></a>';
        }
        $html .= '</div>';
    }
    return $html . '</div></div></div>';
}

// Where it has got to: received, in progress, resolved, closed
$steps = ['Received', 'In progress', 'Resolved', 'Closed'];
$at = $closed ? 3 : ($resolved ? 2 : ($state['key'] === 'new' ? 0 : 1));

$requester = (string) ($ticket['contact_name'] ?? '');
$requester_photo = $ticket['contact_photo'] ? "/uploads/clients/$session_client_id/" . $ticket['contact_photo'] : '';
$agent_name = (string) ($ticket['user_name'] ?? '');
?>

<a class="itflow-portal-back" href="tickets.php"><i class="fas fa-chevron-left"></i><?= t('Tickets') ?></a>

<div class="itflow-portal-page-head">
    <div class="min-w-0">
        <h1><?= escapeHtml($ticket['ticket_subject']) ?></h1>
        <p>
            <?php if ($requester === '') {
                echo t('{number} · opened {when}', ['{number}' => escapeHtml($number), '{when}' => portalDateTime($ticket['ticket_created_at'])]);
            } elseif (intval($ticket['ticket_contact_id']) === $session_contact_id) {
                echo t('{number} · opened {when} by you', ['{number}' => escapeHtml($number), '{when}' => portalDateTime($ticket['ticket_created_at'])]);
            } else {
                echo t('{number} · opened {when} by {name}', ['{number}' => escapeHtml($number), '{when}' => portalDateTime($ticket['ticket_created_at']), '{name}' => escapeHtml($requester)]);
            } ?>
        </p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <?= portalTicketStateHtml($ticket) ?>
        <?php if (!$resolved && !$closed && $task_count === $task_done) { ?>
            <a href="post.php?resolve_ticket=<?= $ticket_id ?>&csrf_token=<?= $csrf ?>" class="btn btn-outline-success confirm-link" data-confirm-title="<?= t('Is it fixed?') ?>" data-confirm-text="<?= t('We will mark it resolved - you can reopen it if it comes back.') ?>" data-confirm-button="<?= t('Yes, it is fixed') ?>" data-confirm-icon="question"><i class="fas fa-check me-2"></i><?= t('It\'s fixed') ?></a>
        <?php } ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">

        <section class="itflow-portal-panel itflow-portal-panel-body mb-4">
            <ol class="itflow-portal-steps">
                <?php foreach ($steps as $i => $step) { ?>
                    <li class="<?= $i < $at ? 'done' : ($i === $at ? 'current' : '') ?>"><?= $i === 1 && $state['key'] === 'you' ? t('Waiting on you') : ($i === 1 && $state['key'] === 'hold' ? escapeHtml(i18n_portal_label($state['label'])) : i18n_portal_label($step)) ?></li>
                <?php } ?>
            </ol>
        </section>

        <?php if ($approvals) { ?>
            <section class="itflow-portal-panel mb-4">
                <div class="itflow-portal-panel-head">
                    <h2><i class="fas fa-clipboard-check"></i><?= t('Waiting for your approval') ?></h2>
                </div>
                <ul class="itflow-portal-list">
                    <?php foreach ($approvals as $approval) { ?>
                        <li class="itflow-portal-todo">
                            <span class="itflow-portal-todo-icon"><i class="fas fa-clipboard-check"></i></span>
                            <span class="itflow-portal-todo-main">
                                <div><?= escapeHtml($approval['task_name']) ?></div>
                                <div><?php if ($approval['can_approve']) {
                                    echo t('We need your go-ahead before we do this');
                                } elseif ($approval['approval_type'] === 'technical') {
                                    echo t('Your technical contact needs to approve this');
                                } elseif ($approval['approval_type'] === 'billing') {
                                    echo t('Your billing contact needs to approve this');
                                } else {
                                    echo t('Your {type} contact needs to approve this', ['{type}' => escapeHtml($approval['approval_type'])]);
                                } ?></div>
                            </span>
                            <?php if ($approval['can_approve']) { ?>
                                <a class="btn btn-sm btn-success confirm-link" href="post.php?approve_ticket_task=<?= intval($approval['task_id']) ?>&approval_id=<?= intval($approval['approval_id']) ?>&approval_url_key=<?= escapeHtml(urlencode($approval['approval_url_key'])) ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-check me-1"></i><?= t('Approve') ?></a>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>

        <div class="itflow-portal-thread mb-4">
            <?= portalMessageHtml($requester ?: t('Request'), $requester_photo, $ticket['ticket_created_at'], $purifier->purify($ticket['ticket_details']),
                $attachments[0] ?? [], intval($ticket['ticket_contact_id']) === $session_contact_id, false) ?>

            <?php while ($reply = mysqli_fetch_assoc($replies)) {
                $client_reply = $reply['ticket_reply_type'] === 'Client';
                $event = $client_reply ? portalTicketEventText($reply['ticket_reply']) : '';
                if ($event !== '') { ?>
                    <div class="itflow-portal-event"><span><?= escapeHtml($event) ?> · <?= portalDateTime($reply['ticket_reply_created_at']) ?></span></div>
                    <?php continue;
                }
                if ($client_reply) {
                    $name = (string) ($reply['contact_name'] ?: t('You'));
                    $photo = $reply['contact_photo'] ? "/uploads/clients/$session_client_id/" . $reply['contact_photo'] : '';
                    $mine = intval($reply['ticket_reply_by']) === $session_contact_id;
                } else {
                    $name = (string) ($reply['user_name'] ?: $session_company_name);
                    $photo = $reply['user_avatar'] ? '/uploads/users/' . intval($reply['user_id']) . '/' . $reply['user_avatar'] : '';
                    $mine = false;
                }
                echo portalMessageHtml($name, $photo, $reply['ticket_reply_created_at'], $purifier->purify($reply['ticket_reply']),
                    $attachments[intval($reply['ticket_reply_id'])] ?? [], $mine, !$client_reply);
            } ?>
        </div>

        <?php if (!$resolved && !$closed) { ?>

            <section class="itflow-portal-panel" id="reply">
                <div class="itflow-portal-panel-head">
                    <h2><i class="fas fa-reply"></i><?= $state['key'] === 'you' ? t('Reply - we are waiting on you') : t('Add a reply') ?></h2>
                </div>
                <form class="itflow-portal-panel-body" action="post.php" enctype="multipart/form-data" method="post" data-portal-once>
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
                    <div class="mb-3">
                        <textarea class="form-control tinymce" name="comment" placeholder="<?= t('Write your reply') ?>" data-required></textarea>
                        <div class="invalid-feedback d-block" data-editor-required-note hidden><?= t('Write a reply first.') ?></div>
                    </div>
                    <div class="itflow-portal-drop mb-3" data-portal-drop>
                        <input type="file" name="file[]" multiple id="replyFiles" accept=".jpg, .jpeg, .gif, .png, .webp, .pdf, .txt, .md, .doc, .docx, .odt, .csv, .xls, .xlsx, .ods, .pptx, .odp, .zip, .tar, .gz, .xml, .msg, .json, .wav, .mp3, .ogg, .mov, .mp4, .av1, .ovpn">
                        <i class="fas fa-paperclip me-2"></i><?= t('Drop files here, or {link} - screenshots, documents, error logs', ['{link}' => '<label for="replyFiles">' . t('choose files') . '</label>']) ?>

                        <div class="itflow-portal-files" data-portal-files hidden></div>
                    </div>
                    <button type="submit" class="btn btn-primary" name="add_ticket_comment"><i class="fas fa-paper-plane me-2"></i><?= t('Send reply') ?></button>
                </form>
            </section>

        <?php } elseif (!$closed) { ?>

            <section class="itflow-portal-panel">
                <?= emptyStateHtml(['title' => t('We think this is fixed'), 'text' => t('Close it if everything is working - or reopen it if it is not, and tell us what is still wrong.'), 'icon' => 'fa-check', 'filtered' => false]) ?>
                <div class="d-flex flex-wrap justify-content-center gap-2 pb-4 px-3">
                    <a href="post.php?close_ticket=<?= $ticket_id ?>&csrf_token=<?= $csrf ?>" class="btn btn-success confirm-link" data-confirm-title="<?= t('Close this ticket?') ?>" data-confirm-text="<?= t('Glad it is sorted. You can raise a new ticket any time.') ?>" data-confirm-button="<?= t('Close it') ?>" data-confirm-icon="question"><i class="fas fa-check-circle me-2"></i><?= t('Yes, close it') ?></a>
                    <a href="post.php?reopen_ticket=<?= $ticket_id ?>&csrf_token=<?= $csrf ?>" class="btn btn-outline-secondary"><i class="fas fa-redo me-2"></i><?= t('No, reopen it') ?></a>
                </div>
            </section>

        <?php } elseif (empty($ticket['ticket_feedback'])) { ?>

            <section class="itflow-portal-panel">
                <?= emptyStateHtml(['title' => t('How did we do?'), 'text' => t('One click tells us how this one went.'), 'icon' => 'fa-star', 'filtered' => false]) ?>
                <form action="post.php" method="post" class="d-flex flex-wrap justify-content-center gap-2 pb-4 px-3">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
                    <button type="submit" class="btn btn-outline-success" name="add_ticket_feedback" value="Good"><i class="far fa-smile me-2"></i><?= t('Good') ?></button>
                    <button type="submit" class="btn btn-outline-danger" name="add_ticket_feedback" value="Bad"><i class="far fa-frown me-2"></i><?= t('Not good') ?></button>
                </form>
            </section>

        <?php } else { ?>

            <div class="itflow-portal-event"><span><?= $ticket['ticket_feedback'] === 'Good' ? t('Closed and rated good - thank you for the feedback.') : t('Closed and rated not good - thank you for the feedback.') ?></span></div>
            <div class="text-center mt-3">
                <a class="btn btn-outline-primary" href="ticket_add.php"><i class="fas fa-plus me-2"></i><?= t('Raise a new ticket') ?></a>
            </div>

        <?php } ?>

    </div>
    <div class="col-lg-4">
        <section class="itflow-portal-panel">
            <div class="itflow-portal-panel-head">
                <h2><i class="fas fa-info-circle"></i><?= t('Details') ?></h2>
            </div>
            <dl class="itflow-portal-facts itflow-portal-panel-body py-1">
                <div><dt><?= t('Ticket') ?></dt><dd><?= escapeHtml($number) ?></dd></div>
                <div><dt><?= t('Status') ?></dt><dd><?= escapeHtml(i18n_portal_label($state['label'])) ?></dd></div>
                <div><dt><?= t('Priority') ?></dt><dd><?= escapeHtml(i18n_ticket_priority_label($ticket['ticket_priority'])) ?></dd></div>
                <?php if (!empty($ticket['category_name'])) { ?>
                    <div><dt><?= t('Category') ?></dt><dd><?= escapeHtml($ticket['category_name']) ?></dd></div>
                <?php } ?>
                <?php if ($requester !== '') { ?>
                    <div><dt><?= t('Raised by') ?></dt><dd><?= escapeHtml($requester) ?></dd></div>
                <?php } ?>
                <?php if ($agent_name !== '' && !$closed) { ?>
                    <div>
                        <dt><?= t('Working on it') ?></dt>
                        <dd class="d-flex align-items-center justify-content-end gap-2">
                            <?= portalAvatarHtml($agent_name, $ticket['user_avatar'] ? '/uploads/users/' . intval($ticket['ticket_assigned_to']) . '/' . $ticket['user_avatar'] : '', 'sm') ?>
                            <?= escapeHtml($agent_name) ?>
                        </dd>
                    </div>
                <?php } ?>
                <?php if (!empty($ticket['asset_name'])) { ?>
                    <div><dt><?= t('Device') ?></dt><dd><i class="fas fa-<?= getAssetIcon($ticket['asset_type']) ?> me-1 text-body-secondary"></i><?= escapeHtml($ticket['asset_name']) ?></dd></div>
                <?php } ?>
                <?php if ($task_count && !$closed) { ?>
                    <div><dt><?= t('Tasks') ?></dt><dd><?= t('{done} of {total} done', ['{done}' => $task_done, '{total}' => $task_count]) ?></dd></div>
                <?php } ?>
                <div><dt><?= t('Opened') ?></dt><dd><?= portalDateTime($ticket['ticket_created_at']) ?></dd></div>
                <?php if ($closed) { ?>
                    <div><dt><?= t('Closed') ?></dt><dd><?= portalDateTime($ticket['ticket_closed_at']) ?></dd></div>
                <?php } elseif ($resolved) { ?>
                    <div><dt><?= t('Resolved') ?></dt><dd><?= portalDateTime($ticket['ticket_resolved_at']) ?></dd></div>
                <?php } elseif (!empty($ticket['ticket_updated_at'])) { ?>
                    <div><dt><?= t('Last update') ?></dt><dd><?= portalDateTime($ticket['ticket_updated_at']) ?></dd></div>
                <?php } ?>
            </dl>
        </section>

        <?php
        // What the request asked, and the answers - the ones the client is asked, not the agents' own
        $request_answers = array_values(array_filter(ticketFillInsStored('ticket', $ticket_id), function ($answer) {
            return $answer['client'];
        }));
        if ($request_answers) { ?>
            <section class="itflow-portal-panel mt-4">
                <div class="itflow-portal-panel-head">
                    <h2><i class="fas fa-clipboard-list"></i><?= t('Request details') ?></h2>
                </div>
                <dl class="itflow-portal-facts itflow-portal-panel-body py-1">
                    <?php foreach ($request_answers as $answer) {
                        $answer_text = ticketFillInText($answer); ?>
                        <div><dt><?= escapeHtml($answer['name']) ?></dt><dd><?= $answer_text !== '' ? escapeHtml($answer_text) : '<span class="text-body-secondary">' . t('Not given') . '</span>' ?></dd></div>
                    <?php } ?>
                </dl>
            </section>
        <?php } ?>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>
