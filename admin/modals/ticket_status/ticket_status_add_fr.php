<?php
require_once '../../includes/modal_header.php';
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-info-circle me-2"></i>Nonuveau Ticket Statut</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <div class="mb-3">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-info-circle"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Ticket Statut name" maxlength="200" required autofocus>
            </div>
        </div>

        <div class="mb-3">
            <label>Color <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-paint-brush"></i></span>
                <input type="color" class="form-control form-control-color" name="color" required>
            </div>
        </div>


        <div class="mb-3">
            <label>SLA</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-stopwatch"></i></span>
                <select class="form-select select2" name="pauses_sla">
                    <option value="0">Resolution clock keeps running</option>
                    <option value="1">Pause the resolution clock</option>
                </select>
            </div>
            <small class="text-muted">Tickets sitting in a paused status never warn or breach on resolution. Heure already spent is kept and the deadline moves out when the ticket comes back.</small>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_ticket_status" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Créer</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Annuler</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
