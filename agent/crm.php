<?php

/*
 * CRM pipeline - the deals as cards in a column for each stage. Won and lost deals show for the
 * year chosen. For agents with the Sales module; the work is in functions/fork_crm.php, the
 * handlers are agent/post/crm.php.
 */

require_once "includes/inc_all.php";
require_once "includes/crm_common.php";

forkCrmEnforce(1);

$can_edit = forkCrmLevel() >= 2;
// Quotes sent, accepted or declined move their deals before the pipeline is drawn
forkCrmSyncQuotes();
$year = intval($_GET['year'] ?? date('Y'));
$year = $year >= 2000 && $year <= 2100 ? $year : intval(date('Y'));
$owner = intval($_GET['owner'] ?? 0);
$search = forkCrmTextClean($_GET['q'] ?? '', 100);

$stages = forkCrmStages();
$deals = forkCrmDeals(['owner' => $owner, 'search' => $search, 'year' => $year]);
$summary = forkCrmSummarize($deals);
$by_stage = [];
foreach ($deals as $deal) {
    $by_stage[intval($deal['deal_stage_id'])][] = $deal;
}
$user_choices = forkCrmUsers();
$client_choices = $can_edit ? forkCrmClientChoices() : [];
$deal = null;
$default_client = intval($_GET['client_id'] ?? 0);

?>

<?= crmTabs('pipeline') ?>

<div class="row mb-3">
    <div class="col-md-3 col-6"><div class="card card-dark mb-2"><div class="card-body py-2"><div class="text-secondary small">Open deals</div><div class="h5 mb-0"><?= intval($summary['open_count']) ?> &middot; <?= escapeHtml(billingMoney($summary['open_amount'])) ?></div></div></div></div>
    <div class="col-md-3 col-6"><div class="card card-dark mb-2"><div class="card-body py-2"><div class="text-secondary small">Forecast (weighted)</div><div class="h5 mb-0"><?= escapeHtml(billingMoney($summary['weighted'])) ?></div><?php if ($summary['open_monthly'] > 0) { ?><div class="small text-secondary">+ <?= escapeHtml(billingMoney($summary['open_monthly'])) ?> a month open</div><?php } ?></div></div></div>
    <div class="col-md-3 col-6"><div class="card card-dark mb-2"><div class="card-body py-2"><div class="text-secondary small">Won in <?= $year ?></div><div class="h5 mb-0"><?= intval($summary['won_count']) ?> &middot; <?= escapeHtml(billingMoney($summary['won_amount'])) ?></div></div></div></div>
    <div class="col-md-3 col-6"><div class="card card-dark mb-2"><div class="card-body py-2"><div class="text-secondary small">Win rate in <?= $year ?></div><div class="h5 mb-0"><?= crmPercent($summary['win_rate']) ?></div></div></div></div>
</div>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-handshake me-2"></i>Pipeline</h3>
        <?php if ($can_edit) { ?>
            <div class="card-tools">
                <a class="btn btn-secondary" href="crm_export.php?year=<?= $year ?>&amp;owner=<?= $owner ?>&amp;q=<?= urlencode($search) ?>"><i class="fas fa-file-csv me-2"></i>Export</a>
                <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#newDeal"><i class="fas fa-plus me-2"></i>New deal</button>
            </div>
        <?php } ?>
    </div>
    <?php if ($can_edit) { ?>
        <div class="collapse<?= $default_client ? ' show' : '' ?>" id="newDeal">
            <form action="post.php" method="post" class="card-body border-bottom">
                <?= crmCsrf() ?>
                <?php require "includes/crm_deal_form.php"; ?>
                <button type="submit" name="add_crm_deal" value="1" class="btn btn-primary"><i class="fas fa-check me-2"></i>Make the deal</button>
            </form>
        </div>
    <?php } ?>
    <div class="card-body border-bottom py-2">
        <form class="row g-2 align-items-center" method="get">
            <div class="col-md-4"><input type="search" class="form-control" name="q" value="<?= escapeHtml($search) ?>" placeholder="Search a deal, a client, a contact"></div>
            <div class="col-md-3">
                <select class="form-select" name="owner" onchange="this.form.submit()">
                    <option value="0">All owners</option>
                    <?= crmOptions($user_choices, $owner) ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="year" onchange="this.form.submit()">
                    <?php for ($y = intval(date('Y')) + 1; $y >= intval(date('Y')) - 5; $y--) { ?>
                        <option value="<?= $y ?>"<?= $y === $year ? ' selected' : '' ?>><?= $y ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-secondary" type="submit"><i class="fas fa-search me-2"></i>Filter</button></div>
        </form>
    </div>
    <div class="card-body" style="overflow-x:auto">
        <div class="d-flex gap-3 align-items-start" style="min-width: max-content">
            <?php foreach ($stages as $stage) {
                $list = $by_stage[intval($stage['stage_id'])] ?? [];
                $stage_sum = forkCrmSummarize($list);
                $total = $stage['stage_kind'] === 'won' ? $stage_sum['won_amount'] : ($stage['stage_kind'] === 'lost' ? $stage_sum['lost_amount'] : $stage_sum['open_amount']);
                $head = ['open' => 'bg-secondary', 'won' => 'bg-success', 'lost' => 'bg-danger'][$stage['stage_kind']];
                ?>
                <div style="width: 270px" class="crm-col" data-stage-id="<?= intval($stage['stage_id']) ?>">
                    <div class="rounded-top px-2 py-1 text-white <?= $head ?>">
                        <div class="fw-semibold"><?= escapeHtml($stage['stage_name']) ?> <span class="badge bg-light text-dark"><?= count($list) ?></span></div>
                        <div class="small"><?= escapeHtml(billingMoney($total)) ?></div>
                    </div>
                    <div class="border border-top-0 rounded-bottom p-2" style="min-height: 80px">
                        <?php foreach ($list as $card) { ?>
                            <div class="card mb-2 crm-card"<?= $can_edit ? ' draggable="true"' : '' ?> data-deal-id="<?= intval($card['deal_id']) ?>">
                                <div class="card-body p-2">
                                    <a class="fw-semibold" href="crm_deal.php?deal_id=<?= intval($card['deal_id']) ?>"><?= escapeHtml($card['deal_title']) ?></a>
                                    <div class="small text-secondary"><?= escapeHtml(forkCrmDealWho($card)) ?></div>
                                    <div class="d-flex justify-content-between mt-1">
                                        <span><?= escapeHtml(billingMoney($card['deal_amount'])) ?><?= floatval($card['deal_monthly']) > 0 ? ' <span class="small text-secondary">+ ' . escapeHtml(billingMoney($card['deal_monthly'])) . '/mo</span>' : '' ?></span>
                                        <span class="text-secondary"><?= intval($card['deal_probability']) ?> %</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-1">
                                        <?= $card['stage_kind'] === 'open' ? crmDueBadge($card['deal_expected_close']) : '<span class="small text-secondary">' . escapeHtml(substr((string) $card['deal_closed_at'], 0, 10)) . '</span>' ?>
                                        <span class="small text-secondary"><?= $card['stage_kind'] === 'open' && !empty($card['deal_stage_at']) ? escapeHtml(forkCrmDurationWords(time() - strtotime($card['deal_stage_at']))) . ' here &middot; ' : '' ?><?= escapeHtml((string) $card['owner_name']) ?></span>
                                    </div>
                                    <?php if ($can_edit) { ?>
                                        <form action="post.php" method="post" class="mt-2 crm-move">
                                            <?= crmCsrf() ?>
                                            <input type="hidden" name="deal_id" value="<?= intval($card['deal_id']) ?>">
                                            <input type="hidden" name="lost_reason" value="">
                                            <input type="hidden" name="lost_category" value="">
                                            <input type="hidden" name="move_crm_deal" value="1">
                                            <select class="form-select form-select-sm crm-stage-select" name="stage_id" data-current="<?= intval($card['deal_stage_id']) ?>" aria-label="Move to a stage">
                                                <?php foreach ($stages as $option) { ?>
                                                    <option value="<?= intval($option['stage_id']) ?>" data-kind="<?= escapeHtml($option['stage_kind']) ?>"<?= intval($option['stage_id']) === intval($card['deal_stage_id']) ? ' selected' : '' ?>><?= escapeHtml($option['stage_name']) ?></option>
                                                <?php } ?>
                                            </select>
                                        </form>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<?php require "includes/crm_move.php"; ?>
<script>
// Drag a card to another column: the same as choosing the stage in its list
(function () {
    var dragged = null;
    document.querySelectorAll('.crm-card[draggable="true"]').forEach(function (card) {
        card.addEventListener('dragstart', function (event) {
            dragged = card;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', card.getAttribute('data-deal-id'));
            card.style.opacity = '0.5';
        });
        card.addEventListener('dragend', function () { card.style.opacity = ''; });
    });
    document.querySelectorAll('.crm-col').forEach(function (col) {
        col.addEventListener('dragover', function (event) { if (dragged) { event.preventDefault(); col.style.outline = '2px dashed #0d6efd'; } });
        col.addEventListener('dragleave', function () { col.style.outline = ''; });
        col.addEventListener('drop', function (event) {
            event.preventDefault();
            col.style.outline = '';
            if (!dragged) { return; }
            var select = dragged.querySelector('select.crm-stage-select');
            if (select) {
                select.value = col.getAttribute('data-stage-id');
                window.crmSubmitMove(select.form, select);
            }
            dragged = null;
        });
    });
})();
</script>

<?php require_once "../includes/footer.php";
