<?php
/*
 * Client Portal
 * Domains & certificates - the company's domain names with who registers
 * them and runs their DNS, website and email, and the web certificates on
 * their sites, each with when it expires or renews.
 * ?tab=certificates opens on the certificates (certificates.php sends here).
 *
 * SCOPING: primary and technical contacts (portalItGate()); keyed to
 * $session_client_id.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

portalItGate();

$tab = ($_GET['tab'] ?? '') === 'certificates' ? 'certificates' : 'domains';

[$columns, $joins] = domainSelectSql();
$domains = mysqli_query($mysqli, "SELECT $columns FROM domains $joins
    WHERE domain_client_id = $session_client_id AND domain_archived_at IS NULL
    ORDER BY domain_expire IS NULL, domain_expire ASC, domain_name ASC");
$certificates = mysqli_query($mysqli, "SELECT certificate_id, certificate_name, certificate_domain, certificate_issued_by, certificate_issued_at, certificate_expire
    FROM certificates WHERE certificate_client_id = $session_client_id AND certificate_archived_at IS NULL
    ORDER BY certificate_expire IS NULL, certificate_expire ASC, certificate_name ASC");
$counts = ['domains' => mysqli_num_rows($domains), 'certificates' => mysqli_num_rows($certificates)];

/** A domain's or certificate's expiry (domainExpiry(), certificateExpiry()) as a portal state - auto-renewing ones aren't a worry. */
function domainsState($expiry) {
    if (!$expiry) {
        return null;
    }
    if ($expiry['state'] === 'expired') {
        return ['label' => t('Expired'), 'icon' => 'fa-calendar-times', 'class' => 'itflow-portal-state-late'];
    }
    $auto = !empty($expiry['auto']) || !empty($expiry['short_lived']);
    if ($expiry['state'] === 'soon') {
        return ['label' => $expiry['text'], 'icon' => $auto ? 'fa-sync-alt' : 'fa-hourglass-half', 'class' => $auto ? 'itflow-portal-state-new' : 'itflow-portal-state-you'];
    }
    return ['label' => $expiry['text'], 'icon' => $auto ? 'fa-sync-alt' : 'fa-calendar-check', 'class' => 'itflow-portal-state-resolved'];
}
?>

<div class="itflow-portal-page-head">
    <div>
        <h1><?= t('Domains & certificates') ?></h1>
        <p><?= t('Your domain names and the certificates that keep your sites secure') ?></p>
    </div>
</div>

<nav class="itflow-portal-tabs" aria-label="Domains and certificates">
    <a class="<?= $tab === 'domains' ? 'active' : '' ?>" href="domains.php"><i class="fas fa-globe"></i><?= t('Domains') ?> <span class="badge rounded-pill"><?= $counts['domains'] ?></span></a>
    <a class="<?= $tab === 'certificates' ? 'active' : '' ?>" href="domains.php?tab=certificates"><i class="fas fa-lock"></i><?= t('Certificates') ?> <span class="badge rounded-pill"><?= $counts['certificates'] ?></span></a>
</nav>

<section class="itflow-portal-panel">
    <?php if ($tab === 'domains') { ?>
        <?php if (!$counts['domains']) { ?>
            <?= portalEmptyState(t('There are no domains on this account yet.')) ?>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th><?= t('Domain Name') ?></th>
                            <th><?= t('Registrar') ?></th>
                            <th><?= t('DNS') ?></th>
                            <th><?= t('Website') ?></th>
                            <th><?= t('Email') ?></th>
                            <th><?= t('Expires') ?></th>
                            <th><?= t('State') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($domains)) {
                            $expiry = domainExpiry($row);
                            $state = domainsState($expiry);
                        ?>
                            <tr>
                                <td><?= escapeHtml($row['domain_name']) ?></td>
                                <td><?= escapeHtml($row['domain_registrar']) ?></td>
                                <td><?= escapeHtml($row['domain_dns_provider'] ?? '-') ?></td>
                                <td><?= escapeHtml($row['domain_website_provider'] ?? '-') ?></td>
                                <td><?= escapeHtml($row['domain_email_provider'] ?? '-') ?></td>
                                <td><?= escapeHtml($row['domain_expire'] ?? '-') ?></td>
                                <td>
                                    <?php if ($state) { ?>
                                        <span class="badge rounded-pill <?= $state['class'] ?>"><i class="fas <?= $state['icon'] ?>"></i> <?= $state['label'] ?></span>
                                    <?php } else { ?>
                                        -
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    <?php } else { ?>
        <?php if (!$counts['certificates']) { ?>
            <?= portalEmptyState(t('There are no certificates on this account yet.')) ?>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th><?= t('Certificate Name') ?></th>
                            <th><?= t('Domain') ?></th>
                            <th><?= t('Issuer') ?></th>
                            <th><?= t('Issued') ?></th>
                            <th><?= t('Expires') ?></th>
                            <th><?= t('State') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($certificates)) {
                            $expiry = certificateExpiry($row);
                            $state = domainsState($expiry);
                        ?>
                            <tr>
                                <td><?= escapeHtml($row['certificate_name']) ?></td>
                                <td><?= escapeHtml($row['certificate_domain']) ?></td>
                                <td><?= escapeHtml($row['certificate_issued_by']) ?></td>
                                <td><?= escapeHtml($row['certificate_issued_at']) ?></td>
                                <td><?= escapeHtml($row['certificate_expire']) ?></td>
                                <td>
                                    <?php if ($state) { ?>
                                        <span class="badge rounded-pill <?= $state['class'] ?>"><i class="fas <?= $state['icon'] ?>"></i> <?= $state['label'] ?></span>
                                    <?php } else { ?>
                                        -
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    <?php } ?>
</section>

<?php
require_once "includes/footer.php";
