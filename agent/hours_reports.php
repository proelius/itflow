<?php

/*
 * Prepaid hours reports - a client's quarterly reports of the hours drawn from its blocks. One button
 * makes the one for the last civil quarter, or opens it when it is made already; the older ones are
 * listed. For agents with the Financial module. The work is in functions/fork_hours_report.php;
 * the handlers are agent/post/hours_report.php.
 */

require_once "includes/inc_all_client.php";

enforceUserPermission('module_financial');

$ready = contractHoursReady();
$can_edit = lookupUserPermission('module_financial') >= 2;
$period = forkHoursReportPeriod();
$current = $ready ? forkHoursReportFor($client_id, $period['start']) : null;
$reports = $ready ? forkHoursReports($client_id) : [];

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-file-alt me-2"></i>Prepaid hours reports</h3>
        <?php if ($ready && $can_edit) { ?>
            <div class="card-tools">
                <?php if ($current) { ?>
                    <a class="btn btn-primary" href="hours_report.php?client_id=<?= $client_id ?>&report_id=<?= intval($current['report_id']) ?>"><i class="fas fa-eye me-2"></i>Open the report for <?= escapeHtml(forkHoursReportTitle($period['start'])) ?></a>
                <?php } else { ?>
                    <form action="post.php" method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="client_id" value="<?= $client_id ?>">
                        <button type="submit" name="generate_hours_report" value="1" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Make the report for <?= escapeHtml(forkHoursReportTitle($period['start'])) ?></button>
                    </form>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
    <?php if (!$ready) { ?>
        <div class="card-body text-secondary">Prepaid hours need the database update - run it from Maintenance &gt; Update first.</div>
    <?php } elseif (!$reports) { ?>
        <div class="card-body text-secondary">
            No report yet. The report for <?= escapeHtml(forkHoursReportTitle($period['start'])) ?> (<?= escapeHtml($period['start']) ?> to <?= escapeHtml($period['end']) ?>) lists the blocks of hours that were good in it, what was drawn from each, what is left, and the tickets that took the hours.
        </div>
    <?php } else { ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="text-dark">
                <tr><th>Quarter</th><th>Period</th><th>Status</th><th>Sent</th><th class="text-center">Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($reports as $report) { ?>
                    <tr>
                        <td class="fw-semibold"><?= escapeHtml(forkHoursReportTitle($report['report_start'])) ?></td>
                        <td><?= escapeHtml($report['report_start']) ?> &ndash; <?= escapeHtml($report['report_end']) ?></td>
                        <td><?= $report['report_status'] === 'sent' ? '<span class="badge bg-success">Sent</span>' : '<span class="badge bg-warning text-dark">Draft</span>' ?></td>
                        <td><?= $report['report_sent_at'] ? escapeHtml($report['report_sent_at']) : '-' ?></td>
                        <td class="text-center text-nowrap">
                            <a class="btn btn-sm btn-secondary" href="hours_report.php?client_id=<?= $client_id ?>&report_id=<?= intval($report['report_id']) ?>"><i class="fas fa-eye"></i></a>
                            <a class="btn btn-sm btn-secondary" href="post.php?hours_report_pdf=<?= intval($report['report_id']) ?>" target="_blank" title="PDF"><i class="fas fa-file-pdf"></i></a>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>

<?php require_once "../includes/footer.php";
