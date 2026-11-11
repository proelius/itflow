<?php

/*
 * CRM stages - the columns of the pipeline: rename them, set what share of its deals is expected
 * to be won, and what kind each is (open, won, lost). For agents with full access to the Sales
 * module; the handlers are agent/post/crm.php.
 */

require_once "includes/inc_all.php";
require_once "includes/crm_common.php";

forkCrmEnforce(3);

$stages = forkCrmStages();
$counts = forkCrmStageCounts();

?>

<?= crmTabs('stages') ?>

<div class="card card-dark">
    <div class="card-header py-2"><h3 class="card-title mt-2"><i class="fas fa-fw fa-sliders-h me-2"></i>Pipeline stages</h3></div>
    <div class="card-body text-secondary border-bottom">
        A deal moved to an <strong>open</strong> stage takes its probability; to a <strong>won</strong> stage, 100 %; to a <strong>lost</strong> stage, 0 %.
        When a deal enters an open stage that has a <strong>follow-up delay</strong>, a task is made for its owner that many days later; on the day, the owner is told in ITFlow and by email (0 = no follow-up). <strong>When a quote...</strong> ties a stage to the quotes linked to a deal: the stage a quote sent brings the deal to (if it is further on than where the deal is), the stage an accepted quote puts it in (won), and where it goes when every quote is declined (lost) - the pipeline checks this each time it is opened, and each night. There is always at least one stage of each kind. A stage with deals in it cannot be deleted.
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="text-dark"><tr><th>Order</th><th>Name</th><th>Kind</th><th>Probability %</th><th>Follow up after (days)</th><th>When a quote...</th><th>Deals</th><th></th></tr></thead>
            <tbody>
            <?php $position = 0; foreach ($stages as $stage) { $position++; $form = 'stage-' . intval($stage['stage_id']); ?>
                <tr>
                    <td class="text-nowrap">
                        <form id="<?= $form ?>" action="post.php" method="post">
                            <?= crmCsrf() ?>
                            <input type="hidden" name="stage_id" value="<?= intval($stage['stage_id']) ?>">
                        </form>
                        <button type="submit" form="<?= $form ?>" name="shift_crm_stage" value="-1" class="btn btn-sm btn-secondary" title="Up"<?= $position === 1 ? ' disabled' : '' ?>><i class="fas fa-arrow-up"></i></button>
                        <button type="submit" form="<?= $form ?>" name="shift_crm_stage" value="1" class="btn btn-sm btn-secondary" title="Down"<?= $position === count($stages) ? ' disabled' : '' ?>><i class="fas fa-arrow-down"></i></button>
                    </td>
                    <td><input type="text" form="<?= $form ?>" class="form-control" name="name" maxlength="100" value="<?= escapeHtml($stage['stage_name']) ?>" required></td>
                    <td><select form="<?= $form ?>" class="form-select" name="kind"><?= crmOptions(forkCrmKinds(), $stage['stage_kind']) ?></select></td>
                    <td style="width: 140px"><input type="number" form="<?= $form ?>" class="form-control" name="probability" min="0" max="100" value="<?= intval($stage['stage_probability']) ?>"></td>
                    <td style="width: 150px"><input type="number" form="<?= $form ?>" class="form-control" name="followup_days" min="0" max="365" value="<?= intval($stage['stage_followup_days']) ?>" title="0 = no follow-up task"></td>
                    <td style="width: 190px"><select form="<?= $form ?>" class="form-select" name="quote_trigger"><?= crmOptions(forkCrmQuoteTriggers(), $stage['stage_quote_trigger']) ?></select></td>
                    <td><?= intval($counts[intval($stage['stage_id'])] ?? 0) ?></td>
                    <td class="text-nowrap">
                        <button type="submit" form="<?= $form ?>" name="edit_crm_stage" value="1" class="btn btn-sm btn-primary"><i class="fas fa-check me-1"></i>Save</button>
                        <button type="submit" form="<?= $form ?>" name="delete_crm_stage" value="1" class="btn btn-sm btn-outline-danger" formnovalidate onclick="return confirm('Delete this stage?');"<?= !empty($counts[intval($stage['stage_id'])]) ? ' disabled' : '' ?>><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="card-body border-top">
        <h5>Add a stage</h5>
        <form action="post.php" method="post" class="row g-2">
            <?= crmCsrf() ?>
            <div class="col-md-3"><input type="text" class="form-control" name="name" maxlength="100" placeholder="Name" required></div>
            <div class="col-md-2"><select class="form-select" name="kind"><?= crmOptions(forkCrmKinds(), 'open') ?></select></div>
            <div class="col-md-2"><input type="number" class="form-control" name="probability" min="0" max="100" value="50" title="Probability %"></div>
            <div class="col-md-2"><input type="number" class="form-control" name="followup_days" min="0" max="365" value="0" title="Follow up after (days), 0 = none"></div>
            <div class="col-md-2"><select class="form-select" name="quote_trigger" title="When a quote..."><?= crmOptions(forkCrmQuoteTriggers(), '') ?></select></div>
            <div class="col-md-1"><button type="submit" name="add_crm_stage" value="1" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add</button></div>
        </form>
    </div>
</div>

<div class="card card-dark">
    <div class="card-header py-2"><h3 class="card-title mt-2"><i class="fas fa-fw fa-cog me-2"></i>Settings</h3></div>
    <form action="post.php" method="post" class="card-body">
        <?= crmCsrf() ?>
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="own_only" id="ownOnly" value="1"<?= forkCrmSetting('own_only') === '1' ? ' checked' : '' ?>>
            <label class="form-check-label" for="ownOnly">Agents see only the deals that are theirs or nobody's (admins see all)</label>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="digest" id="digest" value="1"<?= forkCrmSetting('digest') === '1' ? ' checked' : '' ?>>
            <label class="form-check-label" for="digest">Email each CRM agent a digest every Monday (their tasks, their open deals, the dormant ones)</label>
        </div>
        <div class="row g-2 align-items-center mb-3">
            <div class="col-auto"><label class="col-form-label">A deal is dormant after</label></div>
            <div class="col-auto"><input type="number" class="form-control" name="stale_days" min="1" max="365" value="<?= intval(forkCrmSetting('stale_days')) ?>" style="width: 100px"></div>
            <div class="col-auto"><span class="col-form-label">days with no activity and no task to do - its owner is told once</span></div>
        </div>
        <button type="submit" name="save_crm_settings" value="1" class="btn btn-primary"><i class="fas fa-check me-2"></i>Save the settings</button>
    </form>
</div>

<?php require_once "../includes/footer.php";
