<?php

/*
 * One CRM deal - its fields, the stage it is in, its ITFlow quote, and its activities (notes,
 * calls, e-mails, meetings and tasks with a due date). For agents with the Sales module; the work
 * is in functions/fork_crm.php, the handlers are agent/post/crm.php.
 */

require_once "includes/inc_all.php";
require_once "includes/crm_common.php";

enforceUserPermission('module_sales');

$deal = forkCrmDeal($_GET['deal_id'] ?? 0);
if (!$deal) {
    flashAlert("That deal is gone.", 'error');
    redirect('crm.php');
}
if (intval($deal['deal_client_id']) > 0) {
    enforceClientAccess(intval($deal['deal_client_id']));
}

$can_edit = lookupUserPermission('module_sales') >= 2;
$can_archive = lookupUserPermission('module_sales') >= 3;
$stages = forkCrmStages();
$activities = forkCrmActivities($deal['deal_id']);
$client_choices = $can_edit ? forkCrmClientChoices() : [];
$user_choices = forkCrmUsers();
$quote_choices = forkCrmClientQuotes($deal['deal_client_id']);
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
            <?php if (intval($deal['deal_quote_id']) > 0) { ?>
                <a class="btn btn-secondary" href="quote.php?quote_id=<?= intval($deal['deal_quote_id']) ?>"><i class="fas fa-comment-dollar me-2"></i>Quote <?= escapeHtml($deal['quote_prefix'] . $deal['quote_number']) ?></a>
            <?php } ?>
        </div>
    </div>
    <div class="card-body border-bottom">
        <div class="row">
            <div class="col-md-3 col-6 mb-2"><div class="text-secondary small">For</div><div class="fw-semibold"><?= escapeHtml(forkCrmDealWho($deal)) ?: '-' ?></div></div>
            <div class="col-md-3 col-6 mb-2"><div class="text-secondary small">Amount</div><div class="fw-semibold"><?= escapeHtml(billingMoney($deal['deal_amount'])) ?></div></div>
            <div class="col-md-3 col-6 mb-2"><div class="text-secondary small">Probability / forecast</div><div class="fw-semibold"><?= intval($deal['deal_probability']) ?> % &middot; <?= escapeHtml(billingMoney(forkCrmWeighted($deal['deal_amount'], $deal['deal_probability']))) ?></div></div>
            <div class="col-md-3 col-6 mb-2"><div class="text-secondary small"><?= $closed ? 'Closed' : 'Expected close' ?></div><div class="fw-semibold"><?= $closed ? escapeHtml(substr((string) $deal['deal_closed_at'], 0, 10)) : crmDueBadge($deal['deal_expected_close']) ?></div></div>
        </div>
        <?php if ($deal['stage_kind'] === 'lost' && (string) $deal['deal_lost_reason'] !== '') { ?>
            <div class="alert alert-danger py-2 mb-0 mt-2"><strong>Lost:</strong> <?= escapeHtml($deal['deal_lost_reason']) ?></div>
        <?php } ?>
        <?php if ($can_edit) { ?>
            <form action="post.php" method="post" class="row g-2 align-items-center mt-2 crm-move">
                <?= crmCsrf() ?>
                <input type="hidden" name="deal_id" value="<?= intval($deal['deal_id']) ?>">
                <input type="hidden" name="lost_reason" value="">
                <input type="hidden" name="move_crm_deal" value="1">
                <div class="col-auto"><label class="col-form-label">Move to</label></div>
                <div class="col-auto">
                    <select class="form-select" name="stage_id" data-current="<?= intval($deal['deal_stage_id']) ?>">
                        <?php foreach ($stages as $option) { ?>
                            <option value="<?= intval($option['stage_id']) ?>" data-kind="<?= escapeHtml($option['stage_kind']) ?>"<?= intval($option['stage_id']) === intval($deal['deal_stage_id']) ? ' selected' : '' ?>><?= escapeHtml($option['stage_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </form>
        <?php } ?>
    </div>

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

<script>
document.querySelectorAll('form.crm-move select').forEach(function (select) {
    select.addEventListener('change', function () {
        var form = select.form;
        var kind = select.options[select.selectedIndex].getAttribute('data-kind');
        if (kind === 'lost') {
            var reason = window.prompt('Why was this deal lost? (optional)');
            if (reason === null) {
                select.value = select.getAttribute('data-current');
                return;
            }
            form.elements['lost_reason'].value = reason;
        }
        form.submit();
    });
});
</script>

<?php require_once "../includes/footer.php";
