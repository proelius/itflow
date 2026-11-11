<?php

/*
 * The fields of a deal, for making one (agent/crm.php) and changing one (agent/crm_deal.php).
 * Needs $deal (the deal, or null for a new one), $client_choices, $user_choices and, for
 * a deal with a client, $quote_choices.
 */

$d = $deal ?? [];
$v = function ($key, $default = '') use ($d) {
    return escapeHtml((string) ($d[$key] ?? $default));
};
?>
<div class="row">
    <div class="col-md-5 mb-3">
        <label class="form-label">Title <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="title" maxlength="200" value="<?= $v('deal_title') ?>" placeholder="e.g. Managed IT services, 2027" required>
    </div>
    <div class="col-md-2 mb-3">
        <label class="form-label">Amount (<?= escapeHtml($session_company_currency) ?>)</label>
        <input type="text" class="form-control" name="amount" inputmode="decimal" value="<?= isset($d['deal_amount']) ? escapeHtml(number_format((float) $d['deal_amount'], 2, '.', '')) : '' ?>" placeholder="0.00">
    </div>
    <div class="col-md-2 mb-3">
        <label class="form-label">A month</label>
        <input type="text" class="form-control" name="monthly" inputmode="decimal" value="<?= !empty($d['deal_monthly']) ? escapeHtml(number_format((float) $d['deal_monthly'], 2, '.', '')) : '' ?>" placeholder="recurring">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Probability (%)</label>
        <input type="number" class="form-control" name="probability" min="0" max="100" value="<?= $v('deal_probability', '') ?>" placeholder="from the stage">
    </div>
</div>
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Client or lead</label>
        <select class="form-select" name="client_id">
            <option value="0">- Not a client yet (a prospect) -</option>
            <?= crmOptions($client_choices, $d['deal_client_id'] ?? ($default_client ?? 0)) ?>
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Prospect, if not a client yet</label>
        <input type="text" class="form-control" name="prospect" maxlength="200" value="<?= $v('deal_prospect') ?>" placeholder="Company name">
    </div>
</div>
<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Contact</label>
        <input type="text" class="form-control" name="contact" maxlength="200" value="<?= $v('deal_contact') ?>">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">E-mail</label>
        <input type="email" class="form-control" name="email" maxlength="200" value="<?= $v('deal_email') ?>">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Phone</label>
        <input type="text" class="form-control" name="phone" maxlength="100" value="<?= $v('deal_phone') ?>">
    </div>
</div>
<div class="row">
    <div class="col-md-3 mb-3">
        <label class="form-label">Expected close</label>
        <input type="date" class="form-control" name="expected_close" value="<?= $v('deal_expected_close') ?>">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Source</label>
        <select class="form-select" name="source"><?= crmOptions(forkCrmSources(), $d['deal_source'] ?? '') ?></select>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Owner</label>
        <select class="form-select" name="owner_id">
            <option value="0">-</option>
            <?= crmOptions($user_choices, $d['deal_owner_id'] ?? $session_user_id) ?>
        </select>
    </div>
    <?php if (!$d) { ?>
        <div class="col-md-3 mb-3">
            <label class="form-label">Stage</label>
            <select class="form-select" name="stage_id"><?= crmOptions(array_map(function ($s) { return $s['stage_name']; }, forkCrmStages()), 0) ?></select>
        </div>
    <?php } ?>
</div>
<div class="mb-3">
    <label class="form-label">Notes</label>
    <textarea class="form-control" name="note" rows="3" maxlength="5000"><?= $v('deal_note') ?></textarea>
</div>
