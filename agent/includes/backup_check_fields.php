<?php

/*
 * The fields of a backup check (functions/fork_backup.php), shared by the page of a client's check
 * (agent/backup_check.php) and the window of the list of the week (agent/modals/backup/backup_check.php).
 * Expects $client_id, $week, $servers, $check, $previous, $can_edit, $tickets, $statuses, $parts, $user.
 */

require_once __DIR__ . '/backup_common.php'; // the window does not load it by itself

?>
<div class="row g-2 mb-3">
    <div class="col-md-4"><label class="form-label">Technician</label><input type="text" class="form-control" value="<?= escapeHtml($user['user_name'] ?? $session_name) ?>" disabled></div>
    <div class="col-md-8"><label class="form-label">Ticket <span class="text-secondary">(optional)</span></label>
        <select class="form-select" name="ticket_id" <?= $can_edit ? '' : 'disabled' ?>><option value="0">No ticket</option><?= backupOptions($tickets, $check['bk_ticket_id'] ?? 0) ?></select></div>
</div>
<?php if (!$check) { ?><div class="text-secondary small mb-3">The statuses start at OK: when everything is OK the line is green by itself; change what is not OK, then choose the colour and say why in the comment. The disks are those of the last check.</div><?php } ?>

<?php foreach ($servers as $server_id => $server) {
    $line = $check['lines'][$server_id] ?? null;
    $old = $previous[$server_id] ?? [];
    $value = fn($key, $default = '') => $line ? (string) $line['bl_' . $key] : (string) ($old['bl_' . $key] ?? $default);
    $name = "line[$server_id]"; ?>
    <div class="border rounded p-3 mb-3 bk-line" data-state="<?= escapeHtml($line['bl_state'] ?? '') ?>">
        <div class="fw-semibold mb-2"><i class="fas fa-server me-2"></i><?= escapeHtml($server['name']) ?><?= $server['os'] !== '' ? ' <span class="text-secondary fw-normal">- ' . escapeHtml($server['os']) . '</span>' : '' ?></div>
        <div class="row g-2 mb-2">
            <div class="col-md-3"><label class="form-label">Disk 1</label><input type="text" class="form-control" name="<?= $name ?>[disk1]" maxlength="100" placeholder="C: 349 / 424 Go" value="<?= escapeHtml($value('disk1')) ?>" <?= $can_edit ? '' : 'disabled' ?>></div>
            <div class="col-md-3"><label class="form-label">Disk 2</label><input type="text" class="form-control" name="<?= $name ?>[disk2]" maxlength="100" placeholder="D: 2.60 / 3.45 To" value="<?= escapeHtml($value('disk2')) ?>" <?= $can_edit ? '' : 'disabled' ?>></div>
        </div>
        <div class="row g-2 mb-2">
            <?php foreach ($parts as $key => $label) { ?>
                <div class="col-6 col-md-3"><label class="form-label"><?= $label ?></label><select class="form-select bk-status" name="<?= $name ?>[<?= $key ?>]" <?= $can_edit ? '' : 'disabled' ?>><?= backupOptions($statuses, $line ? $line['bl_' . $key] : 'ok') ?></select></div>
            <?php } ?>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-md-3 bk-second-box"><label class="form-label">Second check</label>
                <select class="form-select bk-second" name="<?= $name ?>[second]" <?= $can_edit ? '' : 'disabled' ?>><?= backupOptions(['pending' => 'Not done yet', 'ok' => 'OK', 'fail' => 'Failed'], in_array($line['bl_second'] ?? '', ['ok', 'fail'], true) ? $line['bl_second'] : 'pending') ?></select></div>
            <div class="col-md-3 bk-state-box"><label class="form-label">Colour</label>
                <select class="form-select bk-state" name="<?= $name ?>[state]" <?= $can_edit ? '' : 'disabled' ?>><?= backupOptions(forkBackupStates(), $line['bl_state'] ?? 'green') ?></select></div>
        </div>
        <label class="form-label">Comment <span class="text-secondary bk-need">(what happened - needed when it is not green)</span></label>
        <textarea class="form-control" name="<?= $name ?>[comment]" rows="2" maxlength="2000" <?= $can_edit ? '' : 'disabled' ?>><?= escapeHtml($line['bl_comment'] ?? '') ?></textarea>
    </div>
<?php } ?>

<script>
document.querySelectorAll('.bk-line').forEach(function (box) {
    var statuses = box.querySelectorAll('.bk-status'), second = box.querySelector('.bk-second'), secondBox = box.querySelector('.bk-second-box'),
        state = box.querySelector('.bk-state'), stateBox = box.querySelector('.bk-state-box'), comment = box.querySelector('textarea'), touched = box.dataset.state !== '';
    function refresh() {
        var failed = Array.prototype.some.call(statuses, function (s) { return s.value === 'fail'; });
        secondBox.style.display = failed ? '' : 'none';
        stateBox.style.display = failed ? '' : 'none'; // everything OK: green, nothing to choose
        if (!failed) { state.value = 'green'; }
        Array.prototype.forEach.call(state.options, function (o) { if (o.value === 'green') { o.disabled = failed; } }); // a failure cannot be green
        if (!touched) { state.value = !failed ? 'green' : (second.value === 'ok' ? 'yellow' : 'red'); }
        if (failed && state.value === 'green') { state.value = second.value === 'ok' ? 'yellow' : 'red'; }
        comment.required = state.value !== 'green';
    }
    state.addEventListener('change', function () { touched = true; refresh(); });
    statuses.forEach(function (s) { s.addEventListener('change', refresh); });
    second.addEventListener('change', refresh);
    refresh();
});
</script>
