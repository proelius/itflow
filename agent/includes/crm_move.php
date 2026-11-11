<?php

/*
 * Moving a deal between stages (agent/crm.php, agent/crm_deal.php): a form.crm-move with a select of the
 * stages submits when the stage changes; a stage of the lost kind first asks why, in a window. Dropping a
 * card on a column of the pipeline does the same (window.crmSubmitMove).
 */

?>
<div class="modal fade" id="crmLostModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Why was this deal lost?</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <label class="form-label">Reason</label>
                <select class="form-select mb-3" id="crmLostCategory">
                    <option value="">-</option>
                    <?= crmOptions(forkCrmLostCategories(), '') ?>
                </select>
                <label class="form-label">Details (optional)</label>
                <input type="text" class="form-control" id="crmLostReason" maxlength="255">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="crmLostConfirm">Mark as lost</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var modalEl = document.getElementById('crmLostModal');
    var pending = null;
    var confirmed = false;
    function restore() {
        if (pending && !confirmed) {
            pending.select.value = pending.select.getAttribute('data-current');
        }
        pending = null;
        confirmed = false;
    }
    modalEl.addEventListener('hidden.bs.modal', restore);
    document.getElementById('crmLostConfirm').addEventListener('click', function () {
        if (!pending) { return; }
        confirmed = true;
        pending.form.elements['lost_category'].value = document.getElementById('crmLostCategory').value;
        pending.form.elements['lost_reason'].value = document.getElementById('crmLostReason').value;
        pending.form.submit();
    });
    window.crmSubmitMove = function (form, select) {
        var kind = select.options[select.selectedIndex].getAttribute('data-kind');
        if (select.value === select.getAttribute('data-current')) { return; }
        if (kind === 'lost') {
            pending = {form: form, select: select};
            confirmed = false;
            document.getElementById('crmLostCategory').value = '';
            document.getElementById('crmLostReason').value = '';
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            return;
        }
        form.submit();
    };
    document.querySelectorAll('form.crm-move select').forEach(function (select) {
        select.addEventListener('change', function () { window.crmSubmitMove(select.form, select); });
    });
})();
</script>
