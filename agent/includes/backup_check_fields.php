<?php

/*
 * The fields of a backup check (functions/fork_backup.php), shared by the page of a client's check
 * (agent/backup_check.php) and the window of the list of the week (agent/modals/backup/backup_check.php).
 * Expects $client_id, $week, $servers, $check, $previous, $can_edit, $tickets, $statuses, $parts, $user.
 */

require_once __DIR__ . '/backup_common.php'; // the window does not load it by itself

?>
<div class="row g-3 mb-3">
    <div class="col-md-4"><label class="form-label">Technician</label><input type="text" class="form-control" value="<?= escapeHtml($user['user_name'] ?? $session_name) ?>" disabled></div>
    <div class="col-md-8"><label class="form-label">Ticket <span class="text-secondary">(optional)</span></label>
        <select class="form-select" name="ticket_id" <?= $can_edit ? '' : 'disabled' ?>><option value="0">No ticket</option><?= backupOptions($tickets, $check['bk_ticket_id'] ?? 0) ?></select></div>
</div>
<?php if (!empty($service) && !$service['autonomous'] && !intval($check['bk_ticket_id'] ?? 0)) { ?><div class="alert alert-warning py-2 small mb-3"><i class="fas fa-ticket-alt me-2"></i>The technician may not act without the client's approval: if something fails, a ticket is opened and linked to this check when it is saved.</div><?php } ?>
<?php if (!$check) { ?><div class="text-secondary small mb-3">The statuses start at OK: when everything is OK the line is green by itself; change what is not OK, then choose the colour and say why in the comment. The total space of the volumes is the last check's.</div><?php } ?>

<?php
// One tab for each server when there are several (a dot in the tab says its colour); a single server has no tabs
$tabbed = count($servers) > 1;
if ($tabbed) { ?>
    <ul class="nav nav-pills nav-fill flex-nowrap overflow-auto mb-3" role="tablist">
        <?php $first = true; foreach ($servers as $server_id => $server) { ?>
            <li class="nav-item"><a class="nav-link<?= $first ? ' active' : '' ?>" data-bs-toggle="pill" href="#bkServer<?= intval($server_id) ?>"><i class="fas fa-fw fa-server me-2 d-none d-lg-inline"></i><?= escapeHtml($server['name']) ?><span class="bk-dot badge rounded-pill bg-success ms-2">&nbsp;</span></a></li>
        <?php $first = false; } ?>
    </ul>
    <div class="tab-content">
<?php }
$first = true;
foreach ($servers as $server_id => $server) {
    $line = $check['lines'][$server_id] ?? null;
    $old = $previous[$server_id] ?? [];
    $value = fn($key, $default = '') => $line ? (string) ($line['bl_' . $key] ?? '') : (string) ($old['bl_' . $key] ?? $default);
    // The volumes: the free space is entered each time, the total is the last one's to start from
    $source = $line ?: $old;
    $legacy = trim(implode(' ; ', array_filter([trim((string) ($source['bl_disk1'] ?? '')), trim((string) ($source['bl_disk2'] ?? ''))])));
    $has_volumes = trim(implode('', [$source['bl_vol1_free'] ?? '', $source['bl_vol1_total'] ?? '', $source['bl_vol2_free'] ?? '', $source['bl_vol2_total'] ?? ''])) !== '';
    $name = "line[$server_id]"; ?>
    <div class="bk-line<?= $tabbed ? ' tab-pane' . ($first ? ' show active' : '') : '' ?>" <?= $tabbed ? 'id="bkServer' . intval($server_id) . '"' : '' ?> data-state="<?= escapeHtml($line['bl_state'] ?? '') ?>">
        <?php if (!$tabbed) { ?>
            <div class="fw-semibold mb-3"><i class="fas fa-server me-2"></i><?= escapeHtml($server['name']) ?></div>
        <?php } ?>
        <?php if ($server['os'] !== '') { ?><div class="text-secondary small mb-3"><?= escapeHtml($server['os']) ?></div><?php } ?>

        <div class="itflow-form-section">Volumes</div>
        <div class="row g-3 mb-2">
            <?php foreach ([1 => ['349 Go', '424 Go'], 2 => ['2.60 To', '3.45 To']] as $n => [$free_example, $total_example]) { ?>
                <div class="col-12 col-md-6">
                    <label class="form-label">Volume <?= $n ?></label>
                    <div class="input-group">
                        <select class="form-select flex-grow-0" style="width: 5rem;" name="<?= $name ?>[vol<?= $n ?>_letter]" aria-label="Volume <?= $n ?> - letter" <?= $can_edit ? '' : 'disabled' ?>><?= backupOptions(['' => '-'] + array_combine(range('A', 'Z'), range('A', 'Z')), $value("vol{$n}_letter")) ?></select>
                        <span class="input-group-text">Free</span>
                        <input type="text" class="form-control" name="<?= $name ?>[vol<?= $n ?>_free]" maxlength="30" placeholder="<?= $free_example ?>" aria-label="Volume <?= $n ?> - free space" value="<?= $line ? escapeHtml($value("vol{$n}_free")) : '' ?>" <?= $can_edit ? '' : 'disabled' ?>>
                        <span class="input-group-text">Total</span>
                        <input type="text" class="form-control" name="<?= $name ?>[vol<?= $n ?>_total]" maxlength="30" placeholder="<?= $total_example ?>" aria-label="Volume <?= $n ?> - total space" value="<?= escapeHtml($value("vol{$n}_total")) ?>" <?= $can_edit ? '' : 'disabled' ?>>
                    </div>
                </div>
            <?php } ?>
            <?php if ($legacy !== '' && !$has_volumes) { ?><div class="col-12 small text-secondary">Entered as text before: <?= escapeHtml($legacy) ?></div><?php } ?>
        </div>

        <div class="itflow-form-section mt-4">Backups</div>
        <div class="row g-3 mb-2">
            <?php foreach ($parts as $key => $label) { ?>
                <div class="col-6 col-md-3"><label class="form-label"><?= $label ?></label><select class="form-select bk-status" name="<?= $name ?>[<?= $key ?>]" <?= $can_edit ? '' : 'disabled' ?>><?= backupOptions($statuses, $line ? $line['bl_' . $key] : 'ok') ?></select></div>
            <?php } ?>
        </div>

        <div class="bk-result">
            <div class="itflow-form-section mt-4">Result</div>
            <div class="row g-3 mb-2">
                <div class="col-md-3 bk-state-box"><label class="form-label">Colour</label>
                    <select class="form-select bk-state" name="<?= $name ?>[state]" <?= $can_edit ? '' : 'disabled' ?>><?= backupOptions(forkBackupStates(), $line['bl_state'] ?? 'green') ?></select></div>
                <div class="col-12">
                    <label class="form-label">Comment <span class="text-secondary bk-need">(what happened - needed when it is not green)</span></label>
                    <textarea class="form-control" name="<?= $name ?>[comment]" rows="2" maxlength="2000" <?= $can_edit ? '' : 'disabled' ?>><?= escapeHtml($line['bl_comment'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="bk-second-box row g-3">
                <div class="col-md-4">
                    <label class="form-label">Second check</label>
                    <select class="form-select bk-second" name="<?= $name ?>[second]" <?= $can_edit ? '' : 'disabled' ?>><?= backupOptions(['pending' => 'Not done yet', 'ok' => 'OK', 'fail' => 'Failed'], in_array($line['bl_second'] ?? '', ['ok', 'fail'], true) ? $line['bl_second'] : 'pending') ?></select>
                </div>
                <div class="col-12">
                    <label class="form-label">Second check comment <span class="text-secondary">(optional - what the second check showed)</span></label>
                    <textarea class="form-control" name="<?= $name ?>[second_comment]" rows="2" maxlength="2000" <?= $can_edit ? '' : 'disabled' ?>><?= escapeHtml($line['bl_second_comment'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>
<?php $first = false; } ?>
<?php if ($tabbed) { ?></div><?php } ?>

<script>
document.querySelectorAll('.bk-line').forEach(function (box) {
    var statuses = box.querySelectorAll('.bk-status'), second = box.querySelector('.bk-second'), secondBox = box.querySelector('.bk-second-box'),
        state = box.querySelector('.bk-state'), stateBox = box.querySelector('.bk-state-box'), comment = box.querySelector('textarea'), greenOption = null, greenAt = 0, touched = box.dataset.state !== '',
        tab = box.id ? document.querySelector('a[href="#' + box.id + '"]') : null, dot = tab ? tab.querySelector('.bk-dot') : null;
    function refresh() {
        var failed = Array.prototype.some.call(statuses, function (s) { return s.value === 'fail'; });
        secondBox.style.display = failed ? '' : 'none';
        stateBox.style.display = failed ? '' : 'none'; // everything OK: green, nothing to choose
        // a failure cannot be green: the option is taken out of the list, and put back when everything is OK again
        var green = Array.prototype.find.call(state.options, function (o) { return o.value === 'green'; });
        if (failed && green) { greenAt = green.index; greenOption = green; state.removeChild(green); }
        if (!failed && !green && greenOption) { state.insertBefore(greenOption, state.options[greenAt] || null); }
        if (!failed) { state.value = 'green'; }
        if (!touched) { state.value = !failed ? 'green' : (second.value === 'ok' ? 'yellow' : 'red'); }
        if (failed && state.value === 'green') { state.value = second.value === 'ok' ? 'yellow' : 'red'; }
        comment.required = state.value !== 'green';
        if (dot) { dot.className = 'bk-dot badge rounded-pill ms-2 ' + ({ green: 'bg-success', yellow: 'bg-warning', red: 'bg-danger' }[state.value] || 'bg-secondary'); }
    }
    state.addEventListener('change', function () { touched = true; refresh(); });
    statuses.forEach(function (s) { s.addEventListener('change', refresh); });
    second.addEventListener('change', refresh);
    refresh();
});
// A comment that is missing on a server in another tab: show that tab, so the message can be read
document.querySelectorAll('.bk-line').forEach(function (box) {
    var form = box.closest('form');
    if (!form || form.dataset.bkInvalid) { return; }
    form.dataset.bkInvalid = '1';
    form.addEventListener('invalid', function (e) {
        var pane = e.target.closest('.tab-pane'), link = pane ? document.querySelector('a[href="#' + pane.id + '"]') : null;
        if (link && !pane.classList.contains('active')) {
            bootstrap.Tab.getOrCreateInstance(link).show();
            setTimeout(function () { e.target.focus(); }, 50);
        }
    }, true);
});
</script>
