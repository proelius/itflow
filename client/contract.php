<?php
/*
 * Client Portal
 * A contract - the copy sent to sign (or the one signed by both, or the
 * contract as it is today when it was never sent), with where it stands
 * alongside: the signing form while it waits for their signature, then
 * signed, then signed by both. Its term in a few lines, its prepaid hours
 * (what is left, and the tickets they went on - a ticket's subject only
 * when the contact can open it), and the PDF.
 *
 * ?signature=client|agent&round= is a signature's picture for the page -
 * the portal's Content-Security-Policy allows no data: pictures.
 *
 * SCOPING: the contract must be the session's client's and one the portal
 * shows (portalContractScopeSql()); a round must be that contract's.
 */

if (isset($_GET['signature'])) {
    require_once '../config.php';
    require_once '../includes/load_global_settings.php';
    require_once '../functions.php';
    require_once 'includes/check_login.php';
    require_once 'functions.php';
    portalContractsGate();
    $round = contractSignatureRow(intval($_GET['round'] ?? 0));
    $side = $_GET['signature'] === 'agent' ? 'agent' : 'client';
    $ok = $round && intval($round['contract_id']) === intval($_GET['id'] ?? 0)
        && mysqli_num_rows(mysqli_query($mysqli, "SELECT contract_id FROM contracts WHERE contract_id = " . intval($round['contract_id']) . " AND " . portalContractScopeSql()));
    if (!$ok || !preg_match('#^data:image/png;base64,(.+)$#s', (string) $round["contract_signature_{$side}_image"], $m)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: image/png');
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    echo base64_decode($m[1]);
    exit;
}

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

portalContractsGate();

$contract_id = intval($_GET['id'] ?? 0);
$visible = $contract_id && mysqli_num_rows(mysqli_query($mysqli, "SELECT contract_id FROM contracts WHERE contract_id = $contract_id AND " . portalContractScopeSql()));
$contract = $visible ? contractRow($contract_id) : null;
if (!$contract) { ?>
    <a class="itflow-portal-back" href="contracts.php"><i class="fas fa-chevron-left"></i><?= t('Contracts') ?></a>
    <section class="itflow-portal-panel">
        <?= emptyStateHtml(['title' => t('That contract isn\'t here'), 'text' => t('It may have been withdrawn, or the link is not quite right.'), 'icon' => 'fa-file-contract', 'filtered' => false,
            'actions' => [['label' => t('All contracts'), 'href' => 'contracts.php', 'icon' => 'fa-arrow-left']]]) ?>
    </section>
    <?php
    require_once "includes/footer.php";
    exit;
}

$round = contractSignaturesReady() ? portalContractRound($contract_id) : null;
$state = portalContractState($contract, $round);
$signing = contractSignatureState($round);
$company = billingCompany();
$contact_name = (string) ($contact['contact_name'] ?? '');

if ($round && !portalViewingAs()) {
    contractSignatureViewed($round, $contact_name);
}

$contract_paper = [
    'row' => $contract,
    'round' => $round,
    'document' => $round ? (string) $round['contract_signature_document'] : contractDocumentHtml($contract),
    'logo_url' => $company['logo_url'],
    'image_url' => function ($side) use ($contract_id, $round) {
        return 'contract.php?id=' . $contract_id . '&round=' . intval($round['contract_signature_id']) . '&signature=' . $side;
    },
];
$signature_pad = [
    'id' => 'portalSign',
    'name' => $contact_name,
    'title' => (string) ($contact['contact_title'] ?? ''),
    'agree' => t('I have read this contract and agree to it for {client}, and to signing it electronically', ['{client}' => $session_client_name]),
];
// Prepaid hours - shown once there are some, or some each month
$blocks = contractHoursReady() ? contractBlocks($contract_id) : [];
$hours = contractHoursSummary($blocks);
$show_hours = contractHoursReady() && ($blocks || floatval($contract['contract_monthly_hours']) > 0);
$hours_uses = $show_hours ? contractHoursUses($contract_id, 8) : [];
$terms = [
    t('Type') => portalContractTypeLabel($contract['contract_type']),
    t('Term') => $contract['contract_start_date'] ? t('{term} from {date}', ['{term}' => portalContractTermLabel($contract['contract_term_months']), '{date}' => i18n_date('M j, Y', strtotime($contract['contract_start_date']))]) : portalContractTermLabel($contract['contract_term_months']),
    intval($contract['contract_auto_renew']) ? t('Renews') : t('Ends') => $contract['contract_end_date'] ? i18n_date('M j, Y', intval($contract['contract_auto_renew']) ? strtotime('+1 day', strtotime($contract['contract_end_date'])) : strtotime($contract['contract_end_date'])) : '',
    t('Notice') => intval($contract['contract_notice_days']) ? t('{n} days before it ends', ['{n}' => intval($contract['contract_notice_days'])]) : '',
];
?>

<a class="itflow-portal-back d-print-none" href="contracts.php"><i class="fas fa-chevron-left"></i><?= t('Contracts') ?></a>

<div class="itflow-portal-page-head d-print-none">
    <div>
        <h1><?= escapeHtml($contract['contract_name']) ?></h1>
        <p><?= portalStateBadge($state) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="pdf.php?contract=<?= $contract_id ?>"><i class="fas fa-file-pdf me-2"></i><?= t('Download PDF') ?></a>
        <button type="button" class="btn btn-outline-secondary" data-portal-print title="<?= t('Print') ?>"><i class="fas fa-print"></i><span class="visually-hidden"><?= t('Print') ?></span></button>
    </div>
</div>

<?php if ($state['key'] === 'waiting') { ?>
    <a class="btn btn-success w-100 mb-3 d-lg-none d-print-none" href="#sign"><i class="fas fa-file-signature me-2"></i><?= t('Read it through - you sign at the end') ?></a>
<?php } ?>

<div class="row g-4">

    <div class="col-lg-8 itflow-paper-col">
        <?php require $_SERVER['DOCUMENT_ROOT'] . '/includes/contract_paper.php'; ?>
    </div>

    <div class="col-lg-4 d-print-none" id="sign">

        <section class="itflow-portal-panel mb-4" data-contract-sign-panel>
            <div class="itflow-portal-panel-body">
                <?php if ($state['key'] === 'waiting') { ?>
                    <p class="small text-body-secondary mb-3"><?= t('Read it through, then sign - we countersign after, and email you a copy signed by both of us.') ?></p>
                    <form action="post.php" method="post" autocomplete="off" data-portal-once>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="contract_signature_id" value="<?= intval($round['contract_signature_id']) ?>">
                        <input type="hidden" name="document_hash" value="<?= escapeHtml($round['contract_signature_document_hash']) ?>">
                        <?php require $_SERVER['DOCUMENT_ROOT'] . '/includes/contract_signature_pad.php'; ?>
                        <button type="submit" class="btn btn-success btn-lg w-100 mt-3" name="portal_sign_contract"><i class="fas fa-file-signature me-2"></i><?= t('Sign') ?></button>
                    </form>
                <?php } elseif ($state['key'] === 'countersign') { ?>
                    <div class="itflow-portal-outcome">
                        <span class="itflow-portal-outcome-icon is-success" aria-hidden="true"><i class="fas fa-check"></i></span>
                        <div>
                            <div class="fw-semibold"><?= t('Signed - thank you') ?></div>
                            <div class="small text-body-secondary"><?= t('{name} signed it {date}. We\'ll countersign it and email you a copy.', ['{name}' => escapeHtml($round['contract_signature_client_name']), '{date}' => escapeHtml(i18n_date('M j, Y', strtotime($round['contract_signature_client_signed_at'])))]) ?></div>
                        </div>
                    </div>
                <?php } elseif ($signing && $signing['key'] === 'signed') { ?>
                    <div class="itflow-portal-outcome">
                        <span class="itflow-portal-outcome-icon is-success" aria-hidden="true"><i class="fas fa-file-signature"></i></span>
                        <div>
                            <div class="fw-semibold"><?= t('Signed by both') ?></div>
                            <div class="small text-body-secondary"><?= t('Signed by both {date}. The PDF is the signed copy.', ['{date}' => escapeHtml(i18n_date('M j, Y', strtotime(max($round['contract_signature_client_signed_at'], $round['contract_signature_agent_signed_at']))))]) ?></div>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="itflow-portal-outcome">
                        <span class="itflow-portal-outcome-icon" aria-hidden="true"><i class="fas fa-file-contract"></i></span>
                        <div>
                            <div class="fw-semibold"><?= escapeHtml($state['label']) ?></div>
                            <div class="small text-body-secondary"><?= t('The contract as it stands. There is no copy signed here.') ?></div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </section>

        <section class="itflow-portal-panel mb-4">
            <div class="itflow-portal-panel-head">
                <h2><i class="fas fa-calendar-alt"></i><?= t('The term') ?></h2>
            </div>
            <div class="itflow-portal-panel-body">
                <dl class="row small mb-0">
                    <?php foreach (array_filter($terms) as $label => $value) { ?>
                        <dt class="col-5 fw-normal text-body-secondary"><?= escapeHtml($label) ?></dt>
                        <dd class="col-7"><?= escapeHtml($value) ?></dd>
                    <?php } ?>
                </dl>
            </div>
        </section>

        <?php if ($show_hours) {
            $monthly = floatval($contract['contract_monthly_hours']); ?>
            <section class="itflow-portal-panel mb-4" data-portal-contract-hours>
                <div class="itflow-portal-panel-head">
                    <h2><i class="fas fa-hourglass-half"></i><?= t('Prepaid hours') ?></h2>
                </div>
                <div class="itflow-portal-panel-body">
                    <div class="d-flex align-items-baseline justify-content-between gap-2">
                        <span class="fs-4 fw-semibold"><?= escapeHtml(contractHoursText($hours['left'])) ?> <span class="fs-6 fw-normal text-body-secondary"><?= t('left') ?></span></span>
                        <?php if ($hours['bought'] > 0) { ?><span class="small text-body-secondary"><?= t('{used} of {bought} used', ['{used}' => escapeHtml(contractHoursText($hours['used'])), '{bought}' => escapeHtml(contractHoursText($hours['bought']))]) ?></span><?php } ?>
                    </div>
                    <?php if ($hours['bought'] > 0) { ?>
                        <progress class="itflow-hours-bar my-2" value="<?= escapeHtml((string) $hours['used']) ?>" max="<?= escapeHtml((string) $hours['bought']) ?>"><?= t('{used} used', ['{used}' => escapeHtml(contractHoursText($hours['used']))]) ?></progress>
                    <?php } ?>
                    <ul class="list-unstyled small text-body-secondary mb-0">
                        <?php if ($monthly > 0) { ?><li><?= t('{hours} included each month', ['{hours}' => escapeHtml(portalHoursWords($monthly))]) ?></li><?php } ?>
                        <?php if ($hours['next_expiry'] !== '') { ?><li><?= t('{hours} to use by {date}', ['{hours}' => escapeHtml(contractHoursText($hours['next_expiry_hours'])), '{date}' => escapeHtml(i18n_date('M j, Y', strtotime($hours['next_expiry'])))]) ?></li><?php } ?>
                        <li><?= t('Time on your tickets comes out of them; time beyond them is invoiced.') ?></li>
                    </ul>
                    <?php if ($hours_uses) { ?>
                        <div class="small fw-semibold mt-3 mb-1"><?= t('Used on') ?></div>
                        <ul class="list-unstyled small mb-0">
                            <?php foreach ($hours_uses as $use) {
                                $sees = portalSeesAllTickets() || intval($use['ticket_contact_id']) === intval($session_contact_id); ?>
                                <li class="d-flex justify-content-between gap-2 py-1">
                                    <span class="text-truncate">
                                        <?php if ($sees) { ?>
                                            <a href="ticket.php?id=<?= intval($use['ticket_id']) ?>"><?= escapeHtml($use['ticket_subject']) ?></a>
                                        <?php } else { ?>
                                            <span class="text-body-secondary"><?= t('Ticket {number}', ['{number}' => escapeHtml($use['ticket_prefix'] . $use['ticket_number'])]) ?></span>
                                        <?php } ?>
                                    </span>
                                    <span class="text-nowrap fw-semibold"><?= escapeHtml(contractHoursText($use['hours'])) ?></span>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                </div>
            </section>
        <?php } ?>

        <p class="small text-body-secondary px-1">
            <?= !empty($company['phone_display'])
                ? t('Questions about this contract? <a href="{url}">Ask us</a> or call {phone}.', ['{url}' => escapeHtml(portalAskUrl('Question about contract: ' . $contract['contract_name'])), '{phone}' => escapeHtml($company['phone_display'])])
                : t('Questions about this contract? <a href="{url}">Ask us</a>.', ['{url}' => escapeHtml(portalAskUrl('Question about contract: ' . $contract['contract_name']))]) ?>

        </p>

    </div>

</div>

<script src="<?= staticFileUrl('/js/signature_pad.js') ?>"></script>

<?php
require_once "includes/footer.php";
