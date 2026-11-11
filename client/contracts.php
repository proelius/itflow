<?php
/*
 * Client Portal
 * Contracts - the agreements with us: the ones waiting for a signature, the
 * ones in force, or all of them, searchable, 20 a page, each opening here
 * to read, sign, or download as a PDF.
 *
 * SCOPING: every query is keyed to $session_client_id from the session
 * (portalContractScopeSql()), and a draft shows only once it is sent to sign.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

portalContractsGate();

$per_page = 20;
$tabs = [
    'waiting' => ['label' => t('To sign'), 'icon' => 'fa-file-signature', 'where' => portalContractWaitingSql()],
    'active' => ['label' => t('In force'), 'icon' => 'fa-check-circle', 'where' => "contract_status = 'Active'"],
    'all' => ['label' => t('All'), 'icon' => 'fa-list', 'where' => '1 = 1'],
];
$q = trim((string) ($_GET['q'] ?? ''));
$where = portalContractScopeSql();
if ($q !== '') {
    $q_sql = escapeSql($q);
    $where .= " AND contract_name LIKE '%$q_sql%'";
}

$count_sql = [];
foreach ($tabs as $key => $t) {
    $count_sql[] = "COALESCE(SUM({$t['where']}), 0) AS `$key`";
}
$counts = array_map('intval', mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " FROM contracts WHERE $where")));

// The tab with something to sign when there is one, else the ones in force, else all
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : ($counts['waiting'] ? 'waiting' : ($counts['active'] ? 'active' : 'all'));
$total = $counts[$tab];
$pages = max(1, (int) ceil($total / $per_page));
$page = min(max(1, intval($_GET['page'] ?? 1)), $pages);
$offset = ($page - 1) * $per_page;

$contracts = mysqli_query($mysqli, "SELECT * FROM contracts WHERE $where AND {$tabs[$tab]['where']}
    ORDER BY contract_status = 'Active' DESC, contract_end_date IS NULL, contract_end_date ASC, contract_name ASC
    LIMIT $per_page OFFSET $offset");

/** This page's address with some of its settings changed. */
function contractsUrl(array $changes) {
    global $tab, $q;
    $params = array_merge(['tab' => $tab, 'q' => $q !== '' ? $q : null], $changes);
    $params = array_filter($params, function ($v) { return $v !== null && $v !== ''; });
    return 'contracts.php' . ($params ? '?' . http_build_query($params) : '');
}
?>

<div class="itflow-portal-page-head">
    <div>
        <h1><?= t('Contracts') ?></h1>
        <p><?= $counts['waiting'] ? t('{n} waiting for your signature', ['{n}' => $counts['waiting']]) : t('Your agreements with us') ?></p>
    </div>
    <form method="get" class="d-flex" role="search">
        <input type="hidden" name="tab" value="<?= escapeHtml($tab) ?>">
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-search"></i></span>
            <input type="search" class="form-control" name="q" value="<?= escapeHtml($q) ?>" placeholder="<?= t('Search contracts') ?>" aria-label="<?= t('Search contracts') ?>" maxlength="200">
        </div>
    </form>
</div>

<nav class="itflow-portal-tabs" aria-label="<?= t('Contracts') ?>">
    <?php foreach ($tabs as $key => $t) { ?>
        <a class="<?= $key === $tab ? 'active' : '' ?><?= $key === 'waiting' && $counts['waiting'] ? ' itflow-portal-tab-alert' : '' ?>" href="<?= escapeHtml(contractsUrl(['tab' => $key, 'page' => null])) ?>">
            <i class="fas <?= $t['icon'] ?>"></i><?= escapeHtml($t['label']) ?>
            <span class="badge rounded-pill"><?= $counts[$key] ?></span>
        </a>
    <?php } ?>
</nav>

<section class="itflow-portal-panel">
    <?php if (!$total) { ?>
        <?php if ($q !== '') { ?>
            <?= emptyStateHtml(['thing' => 'contracts', 'filtered' => true, 'match_title' => t('No contracts match'), 'match_text' => t('Nothing here has &ldquo;{search}&rdquo; in its name.', ['{search}' => escapeHtml($q)]), 'clear_url' => contractsUrl(['q' => null, 'page' => null])]) ?>
        <?php } elseif ($tab === 'waiting') { ?>
            <?= emptyStateHtml(['title' => t('Nothing to sign'), 'text' => t('When we send you a contract to sign, it waits here.'), 'icon' => 'fa-check', 'filtered' => false]) ?>
        <?php } else { ?>
            <?= emptyStateHtml(['title' => t('No contracts here'), 'text' => '', 'icon' => 'fa-file-contract', 'filtered' => false]) ?>
        <?php } ?>
    <?php } else { ?>
        <ul class="itflow-portal-list">
            <?php while ($row = mysqli_fetch_assoc($contracts)) {
                $round = portalContractRound(intval($row['contract_id']));
                $state = portalContractState($row, $round);
                $hours_left = $row['contract_status'] === 'Active' ? (contractHoursLeftFor([intval($row['contract_id'])])[intval($row['contract_id'])] ?? null) : null; ?>
                <li>
                    <a class="itflow-portal-ticket" href="contract.php?id=<?= intval($row['contract_id']) ?>">
                        <span class="itflow-portal-ticket-main">
                            <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($row['contract_name']) ?><span class="fw-normal text-body-secondary"> · <?= escapeHtml(portalContractTypeLabel($row['contract_type'])) ?></span></span>
                            <span class="itflow-portal-ticket-meta">
                                <span><?= $row['contract_start_date'] ? t('{term} from {date}', ['{term}' => escapeHtml(portalContractTermLabel($row['contract_term_months'])), '{date}' => escapeHtml(i18n_date('M j, Y', strtotime($row['contract_start_date'])))]) : escapeHtml(portalContractTermLabel($row['contract_term_months'])) ?></span>
                                <?php if ($row['contract_end_date'] && in_array($row['contract_status'], ['Active', 'Draft'], true)) {
                                    $end_text = escapeHtml(i18n_date('M j, Y', intval($row['contract_auto_renew']) ? strtotime('+1 day', strtotime($row['contract_end_date'])) : strtotime($row['contract_end_date']))); ?>
                                    <span><?= intval($row['contract_auto_renew']) ? t('Renews {date}', ['{date}' => $end_text]) : t('Ends {date}', ['{date}' => $end_text]) ?></span>
                                <?php } ?>
                                <?php if ($hours_left) { ?>
                                    <span><i class="fas fa-hourglass-half me-1"></i><?= t('{hours} prepaid left', ['{hours}' => escapeHtml(contractHoursText($hours_left['left']))]) ?></span>
                                <?php } ?>
                            </span>
                        </span>
                        <span class="itflow-portal-ticket-side">
                            <?= portalStateBadge($state) ?>
                        </span>
                    </a>
                </li>
            <?php } ?>
        </ul>
        <?php if ($pages > 1) { ?>
            <div class="itflow-portal-pager">
                <span><?= t('{from}-{to} of {total}', ['{from}' => $offset + 1, '{to}' => min($offset + $per_page, $total), '{total}' => $total]) ?></span>
                <div class="btn-group">
                    <a class="btn btn-sm btn-outline-secondary<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= escapeHtml(contractsUrl(['page' => $page - 1 > 1 ? $page - 1 : null])) ?>" aria-label="<?= t('Previous page') ?>"><i class="fas fa-chevron-left"></i></a>
                    <a class="btn btn-sm btn-outline-secondary<?= $page >= $pages ? ' disabled' : '' ?>" href="<?= escapeHtml(contractsUrl(['page' => $page + 1])) ?>" aria-label="<?= t('Next page') ?>"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
        <?php } ?>
    <?php } ?>
</section>

<?php
// The prepaid hours reports sent to the client (functions/fork_hours_report.php), by the quarter
$hours_reports = contractHoursReady() ? forkHoursReportsSent($session_client_id) : [];
if ($hours_reports) { ?>
    <section class="itflow-portal-panel mt-4" data-portal-hours-reports>
        <div class="itflow-portal-panel-head">
            <h2><i class="fas fa-hourglass-half"></i><?= t('Prepaid hours reports') ?></h2>
        </div>
        <ul class="itflow-portal-list">
            <?php foreach ($hours_reports as $report) { ?>
                <li>
                    <a class="itflow-portal-ticket" href="pdf.php?hours_report=<?= intval($report['report_id']) ?>">
                        <span class="itflow-portal-ticket-main">
                            <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml(forkHoursReportTitle($report['report_start'])) ?></span>
                            <span class="itflow-portal-ticket-meta">
                                <span><?= t('{from} to {to}', ['{from}' => escapeHtml(i18n_date_value($report['report_start'])), '{to}' => escapeHtml(i18n_date_value($report['report_end']))]) ?></span>
                                <span><?= t('Sent {date}', ['{date}' => escapeHtml(i18n_date_value(substr($report['report_sent_at'], 0, 10)))]) ?></span>
                            </span>
                        </span>
                        <span class="itflow-portal-ticket-side"><i class="fas fa-fw fa-file-pdf"></i> <?= t('Download PDF') ?></span>
                    </a>
                </li>
            <?php } ?>
        </ul>
    </section>
<?php }

require_once "includes/footer.php";
