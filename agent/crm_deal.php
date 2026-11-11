<?php

/*
 * One CRM deal - its fields, the stage it is in, its ITFlow quote, and its activities (notes,
 * calls, e-mails, meetings and tasks with a due date). For agents with the Sales module; the work
 * is in functions/fork_crm.php, the handlers are agent/post/crm.php.
 */

require_once "includes/inc_all.php";
require_once "includes/crm_common.php";

forkCrmEnforce(1);

forkCrmSyncQuotes();
$deal = forkCrmDeal($_GET['deal_id'] ?? 0);
if (!$deal) {
    flashAlert("That deal is gone.", 'error');
    redirect('crm.php');
}
if (intval($deal['deal_client_id']) > 0) {
    enforceClientAccess(intval($deal['deal_client_id']));
}

$can_edit = forkCrmLevel() >= 2;
$can_archive = forkCrmLevel() >= 3;
$stages = forkCrmStages();
$activities = forkCrmActivities($deal['deal_id']);
$client_choices = $can_edit ? forkCrmClientChoices() : [];
$user_choices = forkCrmUsers();
$quotes = forkCrmDealQuotes($deal['deal_id']);
$linked_ids = array_map('intval', array_column($quotes, 'quote_id'));
$quote_choices = array_diff_key(forkCrmClientQuotes($deal['deal_client_id']), array_flip($linked_ids));
$quotes_total = forkCrmQuotesTotal($quotes, $session_company_currency);
$quotes_monthly = forkCrmQuotesTotal($quotes, $session_company_currency, 'quote_monthly_amount');
$contacts = forkCrmDealContacts($deal['deal_id']);
$linked_contact_ids = array_map('intval', array_column($contacts, 'contact_id'));
$contact_choices = array_diff_key(forkCrmClientContacts($deal['deal_client_id']), array_flip($linked_contact_ids));
$history = forkCrmHistory($deal['deal_id']);
$stage_times = forkCrmDealStageTimes($deal);
$all_stages = forkCrmStages();
$email_targets = [];
if ((string) $deal['deal_email'] !== '') {
    $email_targets['deal'] = trim((string) $deal['deal_contact'] . ' <' . $deal['deal_email'] . '>');
}
foreach ($contacts as $contact) {
    if ((string) $contact['contact_email'] !== '') {
        $email_targets[(string) $contact['contact_id']] = trim($contact['contact_name'] . ' <' . $contact['contact_email'] . '>');
    }
}
$has_client = intval($deal['deal_client_id']) > 0;
$is_lead = $has_client && intval($deal['client_lead']) === 1;
$can_client = lookupUserPermission('module_client') >= 2;
$prospect_name = trim((string) $deal['deal_prospect']);
$same_name = !$has_client && $prospect_name !== '' ? forkCrmClientByName($prospect_name) : null;
$same_has_address = $same_name ? forkCrmClientHasAddress($same_name['client_id']) : false;
$has_address = $is_lead ? forkCrmClientHasAddress($deal['deal_client_id']) : false;
$today = date('Y-m-d');
$closed = $deal['stage_kind'] !== 'open';
$kind_badge = ['open' => 'bg-secondary', 'won' => 'bg-success', 'lost' => 'bg-danger'][$deal['stage_kind']];

?>

<?= crmTabs('pipeline') ?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-handshake me-2"></i><?= escapeHtml($deal['deal_title']) ?>
            <span class="badge <?= $kind_badge ?> ms-2"><?= escapeHtml($deal['stage_name']) ?></span></h3>
        <div class="card-tools">
            <a class="btn btn-secondary" href="crm.php"><i class="fas fa-arrow-left me-2"></i>Pipeline</a>
            <?php if (intval($deal['deal_client_id']) > 0) { ?>
                <a class="btn btn-secondary" href="client_overview.php?client_id=<?= intval($deal['deal_client_id']) ?>"><i class="fas fa-user me-2"></i>Client</a>
            <?php } ?>
        </div>
    </div>
    <div class="card-body border-bottom">
        <div class="row">
            <div class="col-md-3 col-6 mb-2"><div class="text-secondary small">For</div><div class="fw-semibold"><?= escapeHtml(forkCrmDealWho($deal)) ?: '-' ?></div></div>
            <div class="col-md-3 col-6 mb-2"><div class="text-secondary small">Amount</div><div class="fw-semibold"><?= escapeHtml(billingMoney($deal['deal_amount'])) ?><?= floatval($deal['deal_monthly']) > 0 ? ' <span class="text-secondary small">+ ' . escapeHtml(billingMoney($deal['deal_monthly'])) . ' a month</span>' : '' ?></div></div>
            <div class="col-md-3 col-6 mb-2"><div class="text-secondary small">Probability / forecast</div><div class="fw-semibold"><?= intval($deal['deal_probability']) ?> % &middot; <?= escapeHtml(billingMoney(forkCrmWeighted($deal['deal_amount'], $deal['deal_probability']))) ?></div></div>
            <div class="col-md-3 col-6 mb-2"><div class="text-secondary small"><?= $closed ? 'Closed' : 'Expected close' ?></div><div class="fw-semibold"><?= $closed ? escapeHtml(substr((string) $deal['deal_closed_at'], 0, 10)) : crmDueBadge($deal['deal_expected_close']) ?></div></div>
        </div>
        <?php if ($deal['stage_kind'] === 'lost' && ((string) $deal['deal_lost_reason'] !== '' || (string) $deal['deal_lost_category'] !== '')) { ?>
            <div class="alert alert-danger py-2 mb-0 mt-2"><strong>Lost:</strong> <?= escapeHtml(trim((forkCrmLostCategories()[$deal['deal_lost_category']] ?? '') . ((string) $deal['deal_lost_reason'] !== '' ? ' - ' . $deal['deal_lost_reason'] : ''), ' -')) ?></div>
        <?php } ?>
        <?php if ($can_edit) { ?>
            <form action="post.php" method="post" class="row g-2 align-items-center mt-2 crm-move">
                <?= crmCsrf() ?>
                <input type="hidden" name="deal_id" value="<?= intval($deal['deal_id']) ?>">
                <input type="hidden" name="lost_reason" value="">
                <input type="hidden" name="lost_category" value="">
                <input type="hidden" name="move_crm_deal" value="1">
                <div class="col-auto"><label class="col-form-label">Move to</label></div>
                <div class="col-auto">
                    <select class="form-select crm-stage-select" name="stage_id" data-current="<?= intval($deal['deal_stage_id']) ?>">
                        <?php foreach ($stages as $option) { ?>
                            <option value="<?= intval($option['stage_id']) ?>" data-kind="<?= escapeHtml($option['stage_kind']) ?>"<?= intval($option['stage_id']) === intval($deal['deal_stage_id']) ? ' selected' : '' ?>><?= escapeHtml($option['stage_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </form>
        <?php } ?>
    </div>

    <?php if ($can_edit && !$has_client) { ?>
        <div class="card-body border-bottom" id="convert">
            <?php if ($prospect_name === '') { ?>
                <span class="text-secondary"><i class="fas fa-user-plus me-2"></i>To make this prospect a client, type its name in <em>Prospect</em> below and save.</span>
            <?php } elseif ($can_client) { ?>
                <form action="post.php" method="post">
                    <?= crmCsrf() ?>
                    <input type="hidden" name="deal_id" value="<?= intval($deal['deal_id']) ?>">
                    <input type="hidden" name="onboard" value="">
                    <?php if ($same_name) { ?>
                        <p class="mb-2"><i class="fas fa-info-circle me-2"></i>ITFlow already has <strong><?= escapeHtml($same_name['client_name']) ?></strong> as a <?= intval($same_name['client_lead']) ? 'prospect' : 'client' ?>.
                            <?php if (!$same_has_address) { ?>Its postal address is missing - it is required to make it a client.<?php } ?></p>
                        <?php if (!$same_has_address && intval($same_name['client_lead'])) { ?>
                            <?= crmAddressFields(false) ?>
                        <?php } ?>
                    <?php } else { ?>
                        <p class="mb-2"><i class="fas fa-user-plus me-2"></i><strong><?= escapeHtml($prospect_name) ?></strong> is not in ITFlow's contacts yet. Its postal address is required.</p>
                        <?= crmAddressFields(true) ?>
                    <?php } ?>
                    <button type="submit" name="make_crm_prospect" value="1" class="btn btn-sm btn-primary"><i class="fas fa-user-plus me-2"></i><?= $same_name ? 'Tie the deal to it' : 'Add as a prospect' ?></button>
                    <?php if (!$same_name || intval($same_name['client_lead'])) { ?>
                        <button type="submit" name="make_crm_prospect" value="1" class="btn btn-sm btn-success" onclick="this.form.onboard.value=1"><i class="fas fa-user-check me-2"></i>Make it a client</button>
                    <?php } ?>
                </form>
            <?php } ?>
        </div>
    <?php } elseif ($is_lead && $can_client) { ?>
        <div class="card-body border-bottom" id="convert">
            <?php if ($has_address) { ?>
                <span class="me-3"><i class="fas fa-user-clock me-2"></i><strong><?= escapeHtml($deal['client_name']) ?></strong> is still a prospect in ITFlow.</span>
                <a class="btn btn-sm btn-success" href="<?= escapeHtml(clientOnboardingUrl($deal['deal_client_id'], 'company')) ?>"><i class="fas fa-user-check me-2"></i>Make it a client</a>
            <?php } else { ?>
                <form action="post.php" method="post">
                    <?= crmCsrf() ?>
                    <input type="hidden" name="deal_id" value="<?= intval($deal['deal_id']) ?>">
                    <input type="hidden" name="onboard" value="1">
                    <p class="mb-2"><i class="fas fa-user-clock me-2"></i><strong><?= escapeHtml($deal['client_name']) ?></strong> is still a prospect in ITFlow. Its postal address is required to make it a client.</p>
                    <?= crmAddressFields(true) ?>
                    <button type="submit" name="make_crm_prospect" value="1" class="btn btn-sm btn-success"><i class="fas fa-user-check me-2"></i>Save the address and make it a client</button>
                </form>
            <?php } ?>
        </div>
    <?php } ?>

    <div class="card-body border-bottom">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Quotes</h5>
            <?php if ($quotes) { ?>
                <span class="text-secondary">In play: <strong><?= escapeHtml(billingMoney($quotes_total)) ?></strong><?= $quotes_monthly > 0 ? ' + <strong>' . escapeHtml(billingMoney($quotes_monthly)) . '</strong> a month' : '' ?></span>
            <?php } ?>
        </div>
        <?php if (!$has_client) { ?>
            <p class="text-secondary mb-0">Quotes belong to a client: tie the deal to a client or prospect first (see above, or choose one in the details).</p>
        <?php } else { ?>
            <?php if (!$quotes) { ?><p class="text-secondary mb-2">No quote linked yet.</p><?php } ?>
            <?php foreach ($quotes as $quote) { ?>
                <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                    <div>
                        <a href="quote.php?quote_id=<?= intval($quote['quote_id']) ?>"><?= escapeHtml($quote['quote_prefix'] . $quote['quote_number']) ?></a>
                        <?= (string) $quote['quote_scope'] !== '' ? '&middot; ' . escapeHtml($quote['quote_scope']) : '' ?>
                        <span class="badge bg-secondary ms-1"><?= escapeHtml($quote['quote_status']) ?></span>
                        <span class="text-secondary ms-1"><?= escapeHtml($quote['quote_date']) ?></span>
                    </div>
                    <div class="text-nowrap">
                        <?= escapeHtml(billingMoney($quote['quote_amount'], $quote['quote_currency_code'])) ?>
                        <?php if ($can_edit) { ?>
                            <form action="post.php" method="post" class="d-inline ms-2">
                                <?= crmCsrf() ?>
                                <input type="hidden" name="deal_id" value="<?= intval($deal['deal_id']) ?>">
                                <input type="hidden" name="quote_id" value="<?= intval($quote['quote_id']) ?>">
                                <button type="submit" name="unlink_crm_quote" value="1" class="btn btn-sm btn-outline-danger" title="Take it off the deal"><i class="fas fa-unlink"></i></button>
                            </form>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
            <?php if ($can_edit) { ?>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <?php if ($quote_choices) { ?>
                        <form action="post.php" method="post" class="d-flex gap-2">
                            <?= crmCsrf() ?>
                            <input type="hidden" name="deal_id" value="<?= intval($deal['deal_id']) ?>">
                            <select class="form-select form-select-sm" name="quote_id"><?= crmOptions($quote_choices, 0) ?></select>
                            <button type="submit" name="link_crm_quote" value="1" class="btn btn-sm btn-primary text-nowrap"><i class="fas fa-link me-2"></i>Link</button>
                        </form>
                    <?php } else { ?>
                        <span class="text-secondary small align-self-center">No other quote for this client.</span>
                    <?php } ?>
                    <form action="post.php" method="post">
                        <?= crmCsrf() ?>
                        <input type="hidden" name="deal_id" value="<?= intval($deal['deal_id']) ?>">
                        <button type="submit" name="create_crm_quote" value="1" class="btn btn-sm btn-success"><i class="fas fa-plus me-2"></i>New quote for this deal</button>
                    </form>
                    <a class="btn btn-sm btn-secondary" href="quotes.php?client_id=<?= intval($deal['deal_client_id']) ?>"><i class="fas fa-comment-dollar me-2"></i>Client's quotes</a>
                    <?php if ($quotes) { ?>
                        <form action="post.php" method="post">
                            <?= crmCsrf() ?>
                            <input type="hidden" name="deal_id" value="<?= intval($deal['deal_id']) ?>">
                            <button type="submit" name="amount_from_crm_quotes" value="1" class="btn btn-sm btn-secondary"><i class="fas fa-equals me-2"></i>Set the deal's amount to <?= escapeHtml(billingMoney($quotes_total)) ?><?= $quotes_monthly > 0 ? ' + ' . escapeHtml(billingMoney($quotes_monthly)) . '/mo' : '' ?></button>
                        </form>
                    <?php } ?>
                </div>
            <?php } ?>
        <?php } ?>
    </div>

    <div class="card-body border-bottom">
        <h5 class="mb-2">Contacts on this deal</h5>
        <?php if (!$has_client) { ?>
            <p class="text-secondary mb-0">Contacts are the client's: tie the deal to a client or prospect first.</p>
        <?php } else { ?>
            <?php if (!$contacts) { ?><p class="text-secondary mb-2">No contact put on the deal yet.</p><?php } ?>
            <?php foreach ($contacts as $contact) { ?>
                <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                    <div>
                        <a href="contact.php?client_id=<?= intval($deal['deal_client_id']) ?>&amp;contact_id=<?= intval($contact['contact_id']) ?>"><?= escapeHtml($contact['contact_name']) ?></a>
                        <?= (string) $contact['contact_title'] !== '' ? '<span class="text-secondary">&middot; ' . escapeHtml($contact['contact_title']) . '</span>' : '' ?>
                        <?php if ($contact['dc_role'] !== '') { ?><span class="badge bg-info text-dark ms-1"><?= escapeHtml(forkCrmContactRoles()[$contact['dc_role']] ?? '') ?></span><?php } ?>
                        <div class="small text-secondary">
                            <?= (string) $contact['contact_email'] !== '' ? '<a href="mailto:' . escapeHtml($contact['contact_email']) . '">' . escapeHtml($contact['contact_email']) . '</a>' : '' ?>
                            <?= (string) $contact['contact_phone'] !== '' ? ' &middot; ' . escapeHtml($contact['contact_phone']) : '' ?>
                            <?= (string) $contact['contact_mobile'] !== '' ? ' &middot; ' . escapeHtml($contact['contact_mobile']) : '' ?>
                        </div>
                    </div>
                    <?php if ($can_edit) { ?>
                        <form action="post.php" method="post" class="ms-2">
                            <?= crmCsrf() ?>
                            <input type="hidden" name="deal_id" value="<?= intval($deal['deal_id']) ?>">
                            <input type="hidden" name="contact_id" value="<?= intval($contact['contact_id']) ?>">
                            <button type="submit" name="unlink_crm_contact" value="1" class="btn btn-sm btn-outline-danger" title="Take it off the deal"><i class="fas fa-unlink"></i></button>
                        </form>
                    <?php } ?>
                </div>
            <?php } ?>
            <?php if ($can_edit && $contact_choices) { ?>
                <form action="post.php" method="post" class="d-flex flex-wrap gap-2 mt-2">
                    <?= crmCsrf() ?>
                    <input type="hidden" name="deal_id" value="<?= intval($deal['deal_id']) ?>">
                    <select class="form-select form-select-sm w-auto" name="contact_id"><?= crmOptions($contact_choices, 0) ?></select>
                    <select class="form-select form-select-sm w-auto" name="role"><?= crmOptions(forkCrmContactRoles(), '') ?></select>
                    <button type="submit" name="link_crm_contact" value="1" class="btn btn-sm btn-primary"><i class="fas fa-user-plus me-2"></i>Put on the deal</button>
                </form>
            <?php } ?>
        <?php } ?>
    </div>

    <?php if ($can_edit) { ?>
        <div class="card-body border-bottom">
            <a data-bs-toggle="collapse" href="#crmEmail" class="fw-semibold text-decoration-none"><i class="fas fa-envelope me-2"></i>Send an email from this deal</a>
            <div class="collapse mt-2" id="crmEmail">
                <?php if (!$email_targets) { ?>
                    <p class="text-secondary mb-0">Give the deal an email address, or put a contact with an email on it.</p>
                <?php } else { ?>
                    <form action="post.php" method="post">
                        <?= crmCsrf() ?>
                        <input type="hidden" name="deal_id" value="<?= intval($deal['deal_id']) ?>">
                        <div class="row g-2 mb-2">
                            <div class="col-md-5"><select class="form-select" name="to"><?= crmOptions($email_targets, '') ?></select></div>
                            <div class="col-md-7"><input type="text" class="form-control" name="subject" maxlength="200" placeholder="Subject" required></div>
                        </div>
                        <textarea class="form-control mb-2" name="body" rows="5" maxlength="5000" placeholder="Your message - a blank line makes a new paragraph" required></textarea>
                        <button type="submit" name="send_crm_email" value="1" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane me-2"></i>Send</button>
                        <span class="text-secondary small ms-2">It goes from ITFlow's mailbox, tells them to reply to you, and is kept in the activities.</span>
                    </form>
                <?php } ?>
            </div>
        </div>
    <?php } ?>

    <div class="row g-0">
        <div class="col-lg-6 border-end">
            <div class="card-body">
                <h5 class="mb-3">Details</h5>
                <?php if ($can_edit) { ?>
                    <form action="post.php" method="post" autocomplete="off">
                        <?= crmCsrf() ?>
                        <input type="hidden" name="deal_id" value="<?= intval($deal['deal_id']) ?>">
                        <?php require "includes/crm_deal_form.php"; ?>
                        <button type="submit" name="edit_crm_deal" value="1" class="btn btn-primary"><i class="fas fa-check me-2"></i>Save</button>
                    </form>
                    <?php if ($can_archive) { ?>
                        <form action="post.php" method="post" class="mt-3" onsubmit="return confirm('Archive this deal? It leaves the pipeline.');">
                            <?= crmCsrf() ?>
                            <input type="hidden" name="deal_id" value="<?= intval($deal['deal_id']) ?>">
                            <button type="submit" name="archive_crm_deal" value="1" class="btn btn-outline-danger btn-sm"><i class="fas fa-archive me-2"></i>Archive the deal</button>
                        </form>
                    <?php } ?>
                <?php } else { ?>
                    <dl class="row mb-0">
                        <dt class="col-4">Contact</dt><dd class="col-8"><?= escapeHtml((string) $deal['deal_contact']) ?: '-' ?></dd>
                        <dt class="col-4">E-mail</dt><dd class="col-8"><?= escapeHtml((string) $deal['deal_email']) ?: '-' ?></dd>
                        <dt class="col-4">Phone</dt><dd class="col-8"><?= escapeHtml((string) $deal['deal_phone']) ?: '-' ?></dd>
                        <dt class="col-4">Source</dt><dd class="col-8"><?= escapeHtml(forkCrmSources()[$deal['deal_source']] ?? '-') ?></dd>
                        <dt class="col-4">Owner</dt><dd class="col-8"><?= escapeHtml((string) $deal['owner_name']) ?: '-' ?></dd>
                        <dt class="col-4">Notes</dt><dd class="col-8"><?= nl2br(escapeHtml((string) $deal['deal_note'])) ?></dd>
                    </dl>
                <?php } ?>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card-body">
                <h5 class="mb-3">Activities</h5>
                <?php if ($can_edit) { ?>
                    <form action="post.php" method="post" class="mb-3">
                        <?= crmCsrf() ?>
                        <input type="hidden" name="deal_id" value="<?= intval($deal['deal_id']) ?>">
                        <div class="row g-2 mb-2">
                            <div class="col-md-4">
                                <select class="form-select" name="type"><?= crmOptions(forkCrmActivityTypes(), 'note') ?></select>
                            </div>
                            <div class="col-md-4">
                                <input type="date" class="form-control" name="due" title="Due date - makes it a task">
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" name="assignee" title="Who does the task">
                                    <option value="0">Anyone</option>
                                    <?= crmOptions($user_choices, $session_user_id) ?>
                                </select>
                            </div>
                        </div>
                        <textarea class="form-control mb-2" name="text" rows="2" maxlength="5000" placeholder="What was said or done, or what is to do (a date makes it a task)" required></textarea>
                        <button type="submit" name="add_crm_activity" value="1" class="btn btn-primary btn-sm"><i class="fas fa-plus me-2"></i>Add</button>
                    </form>
                <?php } ?>
                <?php if (!$activities) { ?>
                    <p class="text-secondary mb-0">Nothing yet.</p>
                <?php } ?>
                <?php foreach ($activities as $activity) {
                    $is_task = $activity['activity_due'] !== null;
                    $is_done = $activity['activity_done_at'] !== null;
                    ?>
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <div>
                            <span class="badge bg-secondary"><?= escapeHtml(forkCrmActivityTypes()[$activity['activity_type']] ?? 'Note') ?></span>
                            <?php if ($is_task) { ?>
                                <?= $is_done ? '<span class="badge bg-success">Done</span>' : crmDueBadge($activity['activity_due']) ?>
                            <?php } ?>
                            <div class="<?= $is_task && $is_done ? 'text-decoration-line-through text-secondary' : '' ?>"><?= nl2br(escapeHtml($activity['activity_text'])) ?></div>
                            <div class="small text-secondary"><?= escapeHtml(substr($activity['activity_created_at'], 0, 16)) ?><?= $is_task && (string) $activity['user_name'] !== '' ? ' &middot; for ' . escapeHtml($activity['user_name']) : '' ?></div>
                        </div>
                        <?php if ($can_edit) { ?>
                            <div class="text-nowrap ms-2">
                                <?php if ($is_task) { ?>
                                    <form action="post.php" method="post" class="d-inline">
                                        <?= crmCsrf() ?>
                                        <input type="hidden" name="activity_id" value="<?= intval($activity['activity_id']) ?>">
                                        <?php if ($is_done) { ?><input type="hidden" name="reopen" value="1"><?php } ?>
                                        <button type="submit" name="done_crm_activity" value="1" class="btn btn-sm btn-outline-success" title="<?= $is_done ? 'Open again' : 'Mark done' ?>"><i class="fas fa-<?= $is_done ? 'undo' : 'check' ?>"></i></button>
                                    </form>
                                <?php } ?>
                                <form action="post.php" method="post" class="d-inline" onsubmit="return confirm('Delete this activity?');">
                                    <?= crmCsrf() ?>
                                    <input type="hidden" name="activity_id" value="<?= intval($activity['activity_id']) ?>">
                                    <button type="submit" name="delete_crm_activity" value="1" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<div class="card card-dark">
    <div class="card-header py-2"><h3 class="card-title mt-2"><i class="fas fa-fw fa-history me-2"></i>History</h3></div>
    <div class="card-body">
        <div class="mb-3">
            <span class="text-secondary small d-block mb-1">Time in each stage</span>
            <?php foreach ($stage_times as $stage_id => $seconds) { ?>
                <span class="badge bg-secondary me-1"><?= escapeHtml($all_stages[$stage_id]['stage_name'] ?? '?') ?>: <?= escapeHtml(forkCrmDurationWords($seconds)) ?></span>
            <?php } ?>
        </div>
        <?php foreach ($history as $line) { ?>
            <div class="border-bottom py-1">
                <span class="text-secondary small"><?= escapeHtml(substr($line['history_at'], 0, 16)) ?></span>
                &middot; <?= escapeHtml($line['history_text']) ?>
                <span class="text-secondary small">- <?= (string) $line['user_name'] !== '' ? escapeHtml($line['user_name']) : 'automatic' ?></span>
            </div>
        <?php } ?>
        <?php if (!$history) { ?><span class="text-secondary">Nothing recorded yet.</span><?php } ?>
    </div>
</div>

<?php require "includes/crm_move.php"; ?>

<?php require_once "../includes/footer.php";
