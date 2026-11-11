<?php

/*
 * Network diagram - a client's networks on one A4 landscape page: what each is
 * for, how it is addressed and the main equipment on it (functions/network_diagram.php).
 *
 * For agents only - it is not in the client portal. The picture is drawn on the
 * server and nothing is sent to another site. ?format=pdf or ?format=svg
 * downloads it instead of showing it.
 */

$format = $_GET['format'] ?? '';

if (in_array($format, ['pdf', 'svg'], true)) {

    // A download: no page around it
    require_once "../config.php";
    require_once "../functions.php";
    require_once "../includes/check_login.php";

    enforceUserPermission('module_client');
    enforceUserPermission('module_support');
    $client_id = intval($_GET['client_id'] ?? 0);
    enforceClientAccess();

    $client = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = $client_id LIMIT 1"));
    if (!$client) {
        http_response_code(404);
        exit("Client not found");
    }

    $svg = networkDiagramForClient($client_id, $client['client_name']);
    $slug = trim(preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $client['client_name'])), '-');
    $file = 'network-diagram' . ($slug !== '' ? "-$slug" : '') . '.' . $format;

    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    if ($format === 'pdf') {
        header('Content-Type: application/pdf');
        echo networkDiagramPdf($svg, 'Network diagram - ' . $client['client_name']);
    } else {
        header('Content-Type: image/svg+xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $svg;
    }
    exit;
}

require_once "includes/inc_all_client.php";

// Perms
enforceUserPermission('module_support');

if (!isset($_GET['client_id'])) {
    echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='javascript:history.back()'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";
    require_once "../includes/footer.php";
    exit;
}

$client_raw = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = $client_id LIMIT 1"));
$svg = networkDiagramForClient($client_id, $client_raw['client_name'] ?? '');
// On the page it takes the width it is given
$svg_page = preg_replace('/ width="\d+" height="\d+"/', ' width="100%"', $svg, 1);

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-project-diagram me-2"></i>Network diagram</h3>
        <div class="card-tools">
            <div class="btn-group">
                <a class="btn btn-primary" href="network_diagram.php?client_id=<?= $client_id ?>&format=pdf">
                    <i class="fas fa-fw fa-file-pdf"></i><span class="d-none d-lg-inline ms-2">PDF</span>
                </a>
                <a class="btn btn-primary" href="network_diagram.php?client_id=<?= $client_id ?>&format=svg">
                    <i class="fas fa-fw fa-download"></i><span class="d-none d-lg-inline ms-2">SVG</span>
                </a>
                <a class="btn btn-secondary" href="networks.php?client_id=<?= $client_id ?>">
                    <i class="fas fa-fw fa-network-wired"></i><span class="d-none d-lg-inline ms-2">Networks</span>
                </a>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?= $svg_page ?>
    </div>
</div>

<?php
require_once "../includes/footer.php";
