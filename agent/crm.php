<?php

/*
 * CRM pipeline - the deals as cards in a column for each stage. Won and lost deals show for the
 * year chosen. For agents with the Sales module; the work is in functions/fork_crm.php, the
 * handlers are agent/post/crm.php.
 */

require_once "includes/inc_all.php";
require_once "includes/crm_common.php";

enforceUserPermission('module_sales');

$can_edit = lookupUserPermission('module_sales') >= 2;
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
    <div class="col-md-3 col-6"><div class="card card-dark mb-2"><div class="card-body py-2"><div class="text-secondary small">Forecast (weighted)</div><div class="h5 mb-0"><?= escapeHtml(billingMoney($summary['weighted'])) ?></div></div></div></div>
    <div class="col-md-3 col-6"><div class="card card-dark mb-2"><div class="card-body py-2"><div class="text-secondary small">Won in <?= $year ?></div><div class="h5 mb-0"><?= intval($summary['won_count']) ?> &middot; <?= escapeHtml(billingMoney($summary['won_amount'])) ?></div></div></div></div>
    <div class="col-md-3 col-6"><div class="card card-dark mb-2"><div class="card-body py-2"><div class="text-secondary small">Win rate in <?= $year ?></div><div class="h5 mb-0"><?= crmPercent($summary['win_rate']) ?></div></div></div></div>
</div>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-handshake me-2"></i>Pipeline</h3>
        <?php if ($can_edit) { ?>
            <div class="card-tools">
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
                <div style="width: 270px">
                    <div class="rounded-top px-2 py-1 text-white <?= $head ?>">
                        <div class="fw-semibold"><?= escapeHtml($stage['stage_name']) ?> <span class="badge bg-light text-dark"><?= count($list) ?></span></div>
                        <div class="small"><?= escapeHtml(billingMoney($total)) ?></div>
                    </div>
                    <div class="border border-top-0 rounded-bottom p-2" style="min-height: 80px">
                        <?php foreach ($list as $card) { ?>
                            <div class="card mb-2">
                                <div class="card-body p-2">
                                    <a class="fw-semibold" href="crm_deal.php?deal_id=<?= intval($card['deal_id']) ?>"><?= escapeHtml($card['deal_title']) ?></a>
                                    <div class="small text-secondary"><?= escapeHtml(forkCrmDealWho($card)) ?></div>
                                    <div class="d-flex justify-content-between mt-1">
                                        <span><?= escapeHtml(billingMoney($card['deal_amount'])) ?></span>
                                        <span class="text-secondary"><?= intval($card['deal_probability']) ?> %</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-1">
                                        <?= $card['stage_kind'] === 'open' ? crmDueBadge($card['deal_expected_close']) : '<span class="small text-secondary">' . escapeHtml(substr((string) $card['deal_closed_at'], 0, 10)) . '</span>' ?>
                                        <span class="small text-secondary"><?= escapeHtml((string) $card['owner_name']) ?></span>
                                    </div>
                                    <?php if ($can_edit) { ?>
                                        <form action="post.php" method="post" class="mt-2 crm-move">
                                            <?= crmCsrf() ?>
                                            <input type="hidden" name="deal_id" value="<?= intval($card['deal_id']) ?>">
                                            <input type="hidden" name="lost_reason" value="">
                                            <input type="hidden" name="move_crm_deal" value="1">
                                            <select class="form-select form-select-sm" name="stage_id" data-current="<?= intval($card['deal_stage_id']) ?>" aria-label="Move to a stage">
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
