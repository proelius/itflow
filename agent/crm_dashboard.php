<?php

/*
 * CRM dashboard - the pipeline by stage, the forecast by month of expected close, what was won and
 * lost in a year, by source and by owner, and why deals were lost. For agents with the Sales
 * module; the figures come from functions/fork_crm.php.
 */

require_once "includes/inc_all.php";
require_once "includes/crm_common.php";

forkCrmEnforce(1);

$year = intval($_GET['year'] ?? date('Y'));
$year = $year >= 2000 && $year <= 2100 ? $year : intval(date('Y'));
$stages = forkCrmStages();
$deals = forkCrmDeals(['year' => $year]);
$summary = forkCrmSummarize($deals);
$forecast = forkCrmForecast($deals);
$by_source = forkCrmGroupSummary($deals, 'deal_source');
$by_owner = forkCrmGroupSummary($deals, 'owner_name');
$by_stage = [];
$lost_reasons = [];
foreach ($deals as $deal) {
    $by_stage[intval($deal['deal_stage_id'])][] = $deal;
    if ($deal['stage_kind'] === 'lost') {
        $reason = forkCrmLostCategories()[$deal['deal_lost_category']] ?? '(no reason given)';
        $lost_reasons[$reason] = ($lost_reasons[$reason] ?? 0) + 1;
    }
}
arsort($lost_reasons);
$open_orders = [];
foreach ($stages as $stage) {
    if ($stage['stage_kind'] === 'open') {
        $open_orders[intval($stage['stage_id'])] = intval($stage['stage_order']);
    }
}
$funnel = forkCrmFunnel($open_orders, forkCrmDealsReached($deals, $stages));
$cycle = forkCrmAverageCycle($deals);
$won_by_month = forkCrmWonByMonth($deals);
$max_won_month = 0.0;
foreach ($won_by_month as $month) {
    $max_won_month = max($max_won_month, $month['amount']);
}
$max_stage = 0.0;
foreach ($by_stage as $list) {
    $max_stage = max($max_stage, forkCrmSummarize($list)['open_amount']);
}
$max_forecast = 0.0;
foreach ($forecast as $month) {
    $max_forecast = max($max_forecast, $month['amount']);
}
$bar = function ($value, $max, $class = 'bg-primary') {
    $width = $max > 0 ? max(2, round($value * 100 / $max)) : 0;
    return '<div class="progress" style="height: 14px"><div class="progress-bar ' . $class . '" style="width: ' . $width . '%"></div></div>';
};

?>

<?= crmTabs('dashboard') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="fas fa-fw fa-chart-line me-2"></i>CRM dashboard</h4>
    <form method="get">
        <select class="form-select" name="year" onchange="this.form.submit()">
            <?php for ($y = intval(date('Y')) + 1; $y >= intval(date('Y')) - 5; $y--) { ?>
                <option value="<?= $y ?>"<?= $y === $year ? ' selected' : '' ?>><?= $y ?></option>
            <?php } ?>
        </select>
    </form>
</div>

<div class="row mb-3">
    <div class="col-md-2 col-6"><div class="card card-dark mb-2"><div class="card-body py-2"><div class="text-secondary small">Open</div><div class="h5 mb-0"><?= intval($summary['open_count']) ?></div><div class="small"><?= escapeHtml(billingMoney($summary['open_amount'])) ?></div></div></div></div>
    <div class="col-md-2 col-6"><div class="card card-dark mb-2"><div class="card-body py-2"><div class="text-secondary small">Forecast</div><div class="h5 mb-0"><?= escapeHtml(billingMoney($summary['weighted'])) ?></div></div></div></div>
    <div class="col-md-2 col-6"><div class="card card-dark mb-2"><div class="card-body py-2"><div class="text-secondary small">Won <?= $year ?></div><div class="h5 mb-0"><?= intval($summary['won_count']) ?></div><div class="small"><?= escapeHtml(billingMoney($summary['won_amount'])) ?></div></div></div></div>
    <div class="col-md-2 col-6"><div class="card card-dark mb-2"><div class="card-body py-2"><div class="text-secondary small">Lost <?= $year ?></div><div class="h5 mb-0"><?= intval($summary['lost_count']) ?></div><div class="small"><?= escapeHtml(billingMoney($summary['lost_amount'])) ?></div></div></div></div>
    <div class="col-md-2 col-6"><div class="card card-dark mb-2"><div class="card-body py-2"><div class="text-secondary small">Win rate</div><div class="h5 mb-0"><?= crmPercent($summary['win_rate']) ?></div></div></div></div>
    <div class="col-md-2 col-6"><div class="card card-dark mb-2"><div class="card-body py-2"><div class="text-secondary small">Average deal won</div><div class="h5 mb-0"><?= $summary['average_won'] === null ? '-' : escapeHtml(billingMoney($summary['average_won'])) ?></div></div></div></div>
</div>

<div class="row mb-3">
    <div class="col-md-3 col-6"><div class="card card-dark mb-2"><div class="card-body py-2"><div class="text-secondary small">Recurring won <?= $year ?></div><div class="h5 mb-0"><?= escapeHtml(billingMoney($summary['won_monthly'])) ?> <span class="small text-secondary">a month</span></div><div class="small text-secondary"><?= escapeHtml(billingMoney($summary['won_monthly'] * 12)) ?> a year</div></div></div></div>
    <div class="col-md-3 col-6"><div class="card card-dark mb-2"><div class="card-body py-2"><div class="text-secondary small">Recurring in the open deals</div><div class="h5 mb-0"><?= escapeHtml(billingMoney($summary['open_monthly'])) ?> <span class="small text-secondary">a month</span></div></div></div></div>
    <div class="col-md-3 col-6"><div class="card card-dark mb-2"><div class="card-body py-2"><div class="text-secondary small">Sales cycle (made to won)</div><div class="h5 mb-0"><?= $cycle === null ? '-' : escapeHtml(rtrim(rtrim(number_format($cycle, 1, '.', ''), '0'), '.')) . ' days' ?></div></div></div></div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card card-dark">
            <div class="card-header py-2"><h3 class="card-title mt-2">Funnel - how far deals got in <?= $year ?></h3></div>
            <div class="table-responsive"><table class="table mb-0">
                <thead class="text-dark"><tr><th>Stage</th><th>Got this far</th><th></th><th class="text-end">From the one before</th></tr></thead>
                <?php $max_reached = 0; foreach ($funnel as $row) { $max_reached = max($max_reached, $row['reached']); } ?>
                <?php foreach ($funnel as $stage_id => $row) { ?>
                    <tr>
                        <td><?= escapeHtml($stages[$stage_id]['stage_name']) ?></td>
                        <td><?= intval($row['reached']) ?></td>
                        <td style="width: 30%"><?= $bar($row['reached'], $max_reached, 'bg-success') ?></td>
                        <td class="text-end"><?= crmPercent($row['of_previous']) ?></td>
                    </tr>
                <?php } ?>
            </table></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-dark">
            <div class="card-header py-2"><h3 class="card-title mt-2">Won by month in <?= $year ?></h3></div>
            <?php if (!$won_by_month) { ?>
                <div class="card-body text-secondary">Nothing won yet.</div>
            <?php } else { ?>
                <div class="table-responsive"><table class="table mb-0">
                    <?php foreach ($won_by_month as $month => $row) { ?>
                        <tr>
                            <td><?= escapeHtml($month) ?></td>
                            <td><?= intval($row['count']) ?></td>
                            <td style="width: 40%"><?= $bar($row['amount'], $max_won_month, 'bg-success') ?></td>
                            <td class="text-end text-nowrap"><?= escapeHtml(billingMoney($row['amount'])) ?></td>
                        </tr>
                    <?php } ?>
                </table></div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card card-dark">
            <div class="card-header py-2"><h3 class="card-title mt-2">Open deals by stage</h3></div>
            <div class="table-responsive"><table class="table mb-0">
                <?php foreach ($stages as $stage) {
                    if ($stage['stage_kind'] !== 'open') {
                        continue;
                    }
                    $sum = forkCrmSummarize($by_stage[intval($stage['stage_id'])] ?? []); ?>
                    <tr>
                        <td style="width: 28%"><?= escapeHtml($stage['stage_name']) ?></td>
                        <td style="width: 12%"><?= intval($sum['open_count']) ?></td>
                        <td><?= $bar($sum['open_amount'], $max_stage) ?></td>
                        <td class="text-end text-nowrap"><?= escapeHtml(billingMoney($sum['open_amount'])) ?></td>
                    </tr>
                <?php } ?>
            </table></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-dark">
            <div class="card-header py-2"><h3 class="card-title mt-2">Forecast by expected close</h3></div>
            <?php if (!$forecast) { ?>
                <div class="card-body text-secondary">No open deal.</div>
            <?php } else { ?>
                <div class="table-responsive"><table class="table mb-0">
                    <thead class="text-dark"><tr><th>Month</th><th>Deals</th><th></th><th class="text-end">Amount</th><th class="text-end">Weighted</th></tr></thead>
                    <?php foreach ($forecast as $month => $row) { ?>
                        <tr>
                            <td><?= $month === 'none' ? 'No date' : escapeHtml($month) ?></td>
                            <td><?= intval($row['count']) ?></td>
                            <td style="width: 30%"><?= $bar($row['amount'], $max_forecast, 'bg-info') ?></td>
                            <td class="text-end text-nowrap"><?= escapeHtml(billingMoney($row['amount'])) ?></td>
                            <td class="text-end text-nowrap"><?= escapeHtml(billingMoney($row['weighted'])) ?></td>
                        </tr>
                    <?php } ?>
                </table></div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card card-dark">
            <div class="card-header py-2"><h3 class="card-title mt-2">By source</h3></div>
            <div class="table-responsive"><table class="table mb-0">
                <thead class="text-dark"><tr><th>Source</th><th>Open</th><th>Won</th><th>Lost</th><th class="text-end">Won amount</th><th class="text-end">Win rate</th></tr></thead>
                <?php foreach ($by_source as $source => $sum) { ?>
                    <tr>
                        <td><?= escapeHtml(forkCrmSources()[$source] ?? '-') ?></td>
                        <td><?= intval($sum['open_count']) ?></td><td><?= intval($sum['won_count']) ?></td><td><?= intval($sum['lost_count']) ?></td>
                        <td class="text-end"><?= escapeHtml(billingMoney($sum['won_amount'])) ?></td>
                        <td class="text-end"><?= crmPercent($sum['win_rate']) ?></td>
                    </tr>
                <?php } ?>
            </table></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-dark">
            <div class="card-header py-2"><h3 class="card-title mt-2">By owner</h3></div>
            <div class="table-responsive"><table class="table mb-0">
                <thead class="text-dark"><tr><th>Owner</th><th>Open</th><th>Won</th><th>Lost</th><th class="text-end">Won amount</th><th class="text-end">Win rate</th></tr></thead>
                <?php foreach ($by_owner as $owner => $sum) { ?>
                    <tr>
                        <td><?= $owner === '' ? '-' : escapeHtml($owner) ?></td>
                        <td><?= intval($sum['open_count']) ?></td><td><?= intval($sum['won_count']) ?></td><td><?= intval($sum['lost_count']) ?></td>
                        <td class="text-end"><?= escapeHtml(billingMoney($sum['won_amount'])) ?></td>
                        <td class="text-end"><?= crmPercent($sum['win_rate']) ?></td>
                    </tr>
                <?php } ?>
            </table></div>
        </div>
    </div>
</div>

<?php if ($lost_reasons) { ?>
    <div class="card card-dark">
        <div class="card-header py-2"><h3 class="card-title mt-2">Why deals were lost in <?= $year ?></h3></div>
        <ul class="list-group list-group-flush">
            <?php foreach ($lost_reasons as $reason => $count) { ?>
                <li class="list-group-item d-flex justify-content-between"><span><?= escapeHtml($reason) ?></span><span class="badge bg-danger"><?= intval($count) ?></span></li>
            <?php } ?>
        </ul>
    </div>
<?php } ?>

<?php require_once "../includes/footer.php";
