<?php

/*
 * Report of a client's prepaid hours, by default for the last civil quarter.
 *
 * An agent with the Financial module makes it by hand, for the last civil
 * quarter (January-March, April-June, July-September, October-December) that is
 * suggested - or for any other period, by changing the two dates: the
 * blocks of hours that were good in it, what was drawn from each, what is
 * left, and the tickets that took the hours. One report for each client and
 * period - making it again for a period that has its report opens that one; only an
 * admin can make it again, which takes the edits away. The agent can change
 * the introduction, the conclusion and each ticket's wording, but not the
 * numbers. Sent to the client's invoice contacts as a PDF, in the client's
 * language, it is locked: only an admin can change it after that.
 *
 * What is kept is a snapshot (fork_hours_reports.report_data, JSON), so a
 * report reads the same later. forkHoursReportPeriod() and forkHoursReportModel()
 * take plain values and touch no database, so scripts/fork_hours_report_selftest.php
 * can test them alone. The table is the fork's own, made when first needed.
 */

/** The last civil quarter before $today ('Y-m-d') - ['start', 'end', 'year', 'quarter']. */
function forkHoursReportPeriod($today = null) {
    $today = $today ?? date('Y-m-d');
    $year = intval(substr($today, 0, 4));
    $quarter = intdiv(intval(substr($today, 5, 2)) - 1, 3) + 1 - 1;
    if ($quarter === 0) {
        $quarter = 4;
        $year--;
    }
    $first_month = ($quarter - 1) * 3 + 1;
    $start = sprintf('%04d-%02d-01', $year, $first_month);
    return ['start' => $start, 'end' => date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $first_month + 2))), 'year' => $year, 'quarter' => $quarter];
}

/**
 * A length of time in the report: the real time, not hundredths of an hour - "20 min", "1 h 05 min", "3 h". The hours are
 * kept as decimals (a slice of 5 minutes is 0.08 h), so they are put back to minutes, to the nearest one.
 */
function forkHoursReportDuration($hours) {
    $minutes = (int) round(floatval($hours) * 60);
    $sign = $minutes < 0 ? '-' : '';
    $minutes = abs($minutes);
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    if ($h === 0) {
        return $sign . $m . ' min';
    }
    return $sign . $h . ' h' . ($m ? ' ' . sprintf('%02d', $m) . ' min' : '');
}

/** Whether a period is exactly a civil quarter: January-March, April-June, July-September or October-December. */
function forkHoursReportIsQuarter($start, $end) {
    return preg_match('/^\d{4}-(01|04|07|10)-01$/', (string) $start) === 1
        && $end === date('Y-m-t', strtotime($start . ' +2 months'));
}

/** A date as 'Y-m-d' when it is a real one, else null. */
function forkHoursReportDate($value) {
    $value = trim((string) $value);
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : null;
}

/**
 * The period of a report from the two dates of the form: [start, end, error]. Empty dates give the last civil quarter,
 * and the error says what to correct (the dates are not dates, or the end is before the start).
 */
function forkHoursReportPeriodFrom($start, $end, $today = null) {
    if (trim((string) $start) === '' && trim((string) $end) === '') {
        $period = forkHoursReportPeriod($today);
        return [$period['start'], $period['end'], ''];
    }
    $start = forkHoursReportDate($start);
    $end = forkHoursReportDate($end);
    if ($start === null || $end === null || $end < $start) {
        return ['', '', 'Give a period that starts before it ends.'];
    }
    return [$start, $end, ''];
}

/**
 * The report's numbers, from plain rows.
 * $blocks: id, hours, bought, expires (or null), month (or null), note, contract.
 * $uses: block_id, ticket_id, reference, subject, done ('Y-m-d'), hours.
 * A block is in it when something was drawn from it in the quarter, or it is a block bought
 * (not one of a month's included hours) that was good at some point in it.
 * ['blocks' => [...], 'lines' => [...], 'used', 'left', 'lapsed'].
 */
function forkHoursReportModel(array $blocks, array $uses, $start, $end) {
    $model = ['blocks' => [], 'lines' => [], 'used' => 0.0, 'left' => 0.0, 'lapsed' => 0.0];
    $by_ticket = [];
    foreach ($blocks as $block) {
        $before = 0.0;
        $period = 0.0;
        foreach ($uses as $use) {
            if (intval($use['block_id']) !== intval($block['id'])) {
                continue;
            }
            if ($use['done'] < $start) {
                $before += floatval($use['hours']);
            } elseif ($use['done'] <= $end) {
                $period += floatval($use['hours']);
            }
        }
        $good = $block['bought'] <= $end && ($block['expires'] === null || $block['expires'] >= $start);
        if ($period <= 0 && !($block['month'] === null && $good)) {
            continue;
        }
        $hours = round(floatval($block['hours']), 2);
        $left = max(0.0, round($hours - $before - $period, 2));
        $lapsed = $block['expires'] !== null && $block['expires'] <= $end;
        $model['blocks'][] = [
            'id' => intval($block['id']), 'hours' => $hours, 'bought' => (string) $block['bought'], 'expires' => $block['expires'], 'month' => $block['month'],
            'note' => (string) ($block['note'] ?? ''), 'contract' => (string) ($block['contract'] ?? ''),
            'before' => round($before, 2), 'period' => round($period, 2), 'left' => $left, 'lapsed' => $lapsed,
        ];
        $model['used'] += $period;
        if ($lapsed) {
            $model['lapsed'] += $left;
        } else {
            $model['left'] += $left;
        }
    }
    $included = array_column($model['blocks'], 'id');
    foreach ($uses as $use) {
        if ($use['done'] < $start || $use['done'] > $end || !in_array(intval($use['block_id']), $included, true)) {
            continue;
        }
        $id = intval($use['ticket_id']);
        if (!isset($by_ticket[$id])) {
            $by_ticket[$id] = ['ticket_id' => $id, 'reference' => (string) $use['reference'], 'date' => (string) $use['done'], 'label' => (string) $use['subject'], 'hours' => 0.0];
        }
        $by_ticket[$id]['hours'] += floatval($use['hours']);
    }
    foreach ($by_ticket as &$line) {
        $line['hours'] = round($line['hours'], 2);
    }
    unset($line);
    $model['lines'] = array_values($by_ticket);
    usort($model['lines'], function ($a, $b) {
        return [$a['date'], $a['ticket_id']] <=> [$b['date'], $b['ticket_id']];
    });
    foreach (['used', 'left', 'lapsed'] as $key) {
        $model[$key] = round($model[$key], 2);
    }
    return $model;
}

/** The table, made if it is not there yet. */
function forkHoursReportEnsure() {
    global $mysqli;
    static $ready = false;
    if ($ready) {
        return;
    }
    $ready = true;
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `fork_hours_reports` (
        `report_id` int(11) NOT NULL AUTO_INCREMENT,
        `report_client_id` int(11) NOT NULL,
        `report_start` date NOT NULL,
        `report_end` date NOT NULL,
        `report_status` varchar(10) NOT NULL DEFAULT 'draft',
        `report_intro` text DEFAULT NULL,
        `report_conclusion` text DEFAULT NULL,
        `report_data` longtext NOT NULL,
        `report_created_by` int(11) NOT NULL DEFAULT 0,
        `report_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `report_updated_at` datetime DEFAULT NULL,
        `report_sent_at` datetime DEFAULT NULL,
        `report_sent_by` int(11) NOT NULL DEFAULT 0,
        `report_sent_to` text DEFAULT NULL,
        PRIMARY KEY (`report_id`),
        UNIQUE KEY `report_client_period` (`report_client_id`, `report_start`, `report_end`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    // A table made when a report was one for each quarter: its key was the client and the start only. A period can now be
    // chosen, so the key is the client, the start and the end (the fork's own table, not an ITFlow one)
    $columns = mysqli_fetch_all(mysqli_query($mysqli, "SHOW INDEX FROM `fork_hours_reports` WHERE Key_name = 'report_client_period'"), MYSQLI_ASSOC);
    if (count($columns) === 2) {
        mysqli_query($mysqli, "ALTER TABLE `fork_hours_reports` DROP INDEX `report_client_period`, ADD UNIQUE KEY `report_client_period` (`report_client_id`, `report_start`, `report_end`)");
    }
}

/**
 * "Quarter 3 2026" in the language in force - or, for another period (when $end is given and it is not a civil
 * quarter), "Period from 01.04.2026 to 15.05.2026".
 */
function forkHoursReportTitle($start, $end = null) {
    if ($end !== null && !forkHoursReportIsQuarter($start, $end)) {
        return t('Period {from} to {to}', ['{from}' => i18n_date_value($start), '{to}' => i18n_date_value($end)]);
    }
    return t('Quarter {quarter} {year}', ['{quarter}' => intdiv(intval(substr($start, 5, 2)) - 1, 3) + 1, '{year}' => substr($start, 0, 4)]);
}

/**
 * The words of the report that say "this quarter", in the language in force: for a civil quarter they are the ones
 * the reports have always had; for another period they say "the period".
 */
function forkHoursReportWords($start, $end) {
    if (forkHoursReportIsQuarter($start, $end)) {
        return ['used' => t('Used this quarter'), 'total' => t('Total used this quarter'), 'no_hours' => t('No prepaid hours this quarter.'), 'no_tickets' => t('No ticket drew hours this quarter.')];
    }
    return ['used' => t('Used in the period'), 'total' => t('Total used in the period'), 'no_hours' => t('No prepaid hours in this period.'), 'no_tickets' => t('No ticket drew hours in this period.')];
}

/** A client's report row, by id - or null. */
function forkHoursReportRow($report_id) {
    global $mysqli;
    forkHoursReportEnsure();
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT fork_hours_reports.*, client_name FROM fork_hours_reports JOIN clients ON client_id = report_client_id WHERE report_id = " . intval($report_id)));
    if (!$row) {
        return null;
    }
    $row['data'] = json_decode((string) $row['report_data'], true) ?: ['blocks' => [], 'lines' => [], 'used' => 0, 'left' => 0, 'lapsed' => 0];
    return $row;
}

/** The report a client has for the period from $start to $end ($end empty: the one starting $start) - or null. */
function forkHoursReportFor($client_id, $start, $end = null) {
    global $mysqli;
    forkHoursReportEnsure();
    $id = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT report_id FROM fork_hours_reports WHERE report_client_id = " . intval($client_id) . " AND report_start = '" . escapeSql($start) . "'"
        . ($end !== null ? " AND report_end = '" . escapeSql($end) . "'" : '') . " ORDER BY report_end DESC LIMIT 1"))[0] ?? 0);
    return $id ? forkHoursReportRow($id) : null;
}

/** All of a client's reports, newest quarter first. */
function forkHoursReports($client_id) {
    global $mysqli;
    forkHoursReportEnsure();
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT report_id, report_start, report_end, report_status, report_created_at, report_sent_at FROM fork_hours_reports WHERE report_client_id = " . intval($client_id) . " ORDER BY report_start DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/** A client's reports that were sent to it, newest quarter first - what the client portal shows. */
function forkHoursReportsSent($client_id) {
    global $mysqli;
    forkHoursReportEnsure();
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT report_id, report_start, report_end, report_sent_at FROM fork_hours_reports
        WHERE report_client_id = " . intval($client_id) . " AND report_status = 'sent' ORDER BY report_start DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/** The numbers for a client and quarter, from the blocks and what was drawn from them. */
function forkHoursReportBuild($client_id, $start, $end) {
    global $mysqli;
    forkHoursEnsure();
    $client_id = intval($client_id);
    $blocks = [];
    $sql = mysqli_query($mysqli, "SELECT contract_block_id, contract_block_hours, contract_block_bought, contract_block_expires, contract_block_month, contract_block_note, contract_name
        FROM contract_blocks JOIN contracts ON contract_block_contract_id = contract_id
        LEFT JOIN fork_hours_blocks ON fork_block_id = contract_block_id
        WHERE contract_client_id = $client_id AND COALESCE(fork_block_state, 'active') <> 'pending' ORDER BY contract_block_bought ASC, contract_block_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $blocks[] = ['id' => $row['contract_block_id'], 'hours' => $row['contract_block_hours'], 'bought' => $row['contract_block_bought'], 'expires' => $row['contract_block_expires'],
            'month' => $row['contract_block_month'], 'note' => $row['contract_block_note'], 'contract' => $row['contract_name']];
    }
    $uses = [];
    $sql = mysqli_query($mysqli, "SELECT contract_block_use_block_id, contract_block_use_hours, ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_created_at, ticket_resolved_at, ticket_closed_at
        FROM contract_block_uses JOIN contract_blocks ON contract_block_use_block_id = contract_block_id JOIN contracts ON contract_block_contract_id = contract_id
        JOIN tickets ON contract_block_use_ticket_id = ticket_id
        WHERE contract_client_id = $client_id AND contract_block_use_hours > 0");
    while ($row = mysqli_fetch_assoc($sql)) {
        $uses[] = ['block_id' => $row['contract_block_use_block_id'], 'ticket_id' => $row['ticket_id'], 'reference' => $row['ticket_prefix'] . $row['ticket_number'],
            'subject' => $row['ticket_subject'], 'done' => contractHoursDoneOn($row), 'hours' => $row['contract_block_use_hours']];
    }
    return forkHoursReportModel($blocks, $uses, $start, $end);
}

/** The default introduction and conclusion, in the client's language. */
function forkHoursReportTexts($start, $end) {
    return i18n_with(client_language(), function () use ($start, $end) {
        return [
            t('Here is how your prepaid hours were used between {from} and {to}.', ['{from}' => i18n_date_value($start), '{to}' => i18n_date_value($end)]),
            t('Thank you for your trust. Do not hesitate to contact us to renew your hours.'),
        ];
    });
}

/** Make the report for a period ($period: start, end). Its id, or 0 when one exists already for it. */
function forkHoursReportCreate($client_id, array $period, $user_id) {
    global $mysqli;
    forkHoursReportEnsure();
    $model = forkHoursReportBuild($client_id, $period['start'], $period['end']);
    [$intro, $conclusion] = forkHoursReportTexts($period['start'], $period['end']);
    mysqli_query($mysqli, "INSERT IGNORE INTO fork_hours_reports SET report_client_id = " . intval($client_id) . ", report_start = '" . escapeSql($period['start']) . "', report_end = '" . escapeSql($period['end']) . "',
        report_intro = '" . escapeSql($intro) . "', report_conclusion = '" . escapeSql($conclusion) . "', report_data = '" . mysqli_real_escape_string($mysqli, json_encode($model, JSON_UNESCAPED_UNICODE)) . "',
        report_created_by = " . intval($user_id));
    return mysqli_affected_rows($mysqli) === 1 ? intval(mysqli_insert_id($mysqli)) : 0;
}

/** Make it again from what is in ITFlow now (an admin) - the edits go. */
function forkHoursReportRebuild(array $report) {
    global $mysqli;
    $model = forkHoursReportBuild($report['report_client_id'], $report['report_start'], $report['report_end']);
    [$intro, $conclusion] = forkHoursReportTexts($report['report_start'], $report['report_end']);
    mysqli_query($mysqli, "UPDATE fork_hours_reports SET report_intro = '" . escapeSql($intro) . "', report_conclusion = '" . escapeSql($conclusion) . "',
        report_data = '" . mysqli_real_escape_string($mysqli, json_encode($model, JSON_UNESCAPED_UNICODE)) . "', report_status = 'draft', report_sent_at = NULL, report_sent_to = NULL, report_updated_at = NOW()
        WHERE report_id = " . intval($report['report_id']));
}

/** Save the wording: the introduction, the conclusion and each ticket's label (by position). */
function forkHoursReportSave(array $report, $intro, $conclusion, array $labels) {
    global $mysqli;
    $data = $report['data'];
    foreach ($data['lines'] as $i => $line) {
        if (isset($labels[$i])) {
            $label = mb_substr(trim(strip_tags((string) $labels[$i])), 0, 250);
            $data['lines'][$i]['label'] = $label !== '' ? $label : $line['label'];
        }
    }
    mysqli_query($mysqli, "UPDATE fork_hours_reports SET report_intro = '" . escapeSql(mb_substr(trim(strip_tags((string) $intro)), 0, 2000)) . "', report_conclusion = '" . escapeSql(mb_substr(trim(strip_tags((string) $conclusion)), 0, 2000)) . "',
        report_data = '" . mysqli_real_escape_string($mysqli, json_encode($data, JSON_UNESCAPED_UNICODE)) . "', report_updated_at = NOW() WHERE report_id = " . intval($report['report_id']));
}

/** A block as the report words it - its note, or the hours and the day bought - in the language in force. */
function forkHoursReportBlockName(array $block) {
    if ($block['month'] !== null) {
        return t('Hours included in {month}', ['{month}' => i18n_date('F Y', strtotime($block['month'] . '-01'))]);
    }
    return trim((string) $block['note']) !== '' ? (string) $block['note'] : t('{hours} bought on {date}', ['{hours}' => forkHoursReportDuration($block['hours']), '{date}' => i18n_date_value($block['bought'])]);
}

/** The report as a PDF, in the client's language - ['name', 'data']. */
function forkHoursReportPdf(array $report) {
    require_once __DIR__ . '/../libs/TCPDF/tcpdf.php';
    require_once __DIR__ . '/billing_pdf.php';
    return i18n_with(client_language(), fn() => forkHoursReportPdfRender($report));
}

function forkHoursReportPdfRender(array $report) {
    billingPdfConfig();
    $company = billingCompany();
    $data = $report['data'];
    $e = function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };
    $grey = '#6c757d';
    $th = 'font-size:6.5pt; color:' . $grey . '; font-weight:bold; border-bottom:0.6px solid #adb5bd;';
    $td = 'border-bottom:0.3px solid #dee2e6;';
    $title = forkHoursReportTitle($report['report_start'], $report['report_end']);
    $words = forkHoursReportWords($report['report_start'], $report['report_end']);

    $format = in_array($company['company_country'] ?? '', ['United States', 'USA', 'US', 'Canada'], true) ? 'LETTER' : 'A4';
    $pdf = new BillingPdf('P', 'mm', $format, true, 'UTF-8', false);
    $pdf->SetCreator('ITFlow');
    $pdf->SetAuthor((string) ($company['company_name'] ?? ''));
    $pdf->SetTitle(t('Prepaid hours report') . ' ' . $report['client_name'] . ' ' . $title);
    $pdf->SetMargins(16, 14, 16);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(12);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->setFontSubsetting(true);
    $pdf->footer_label = trim(($company['company_name'] ?? '') . ' · ' . t('Prepaid hours report') . ' · ' . $report['client_name'], ' ·');
    $pdf->AddPage();
    $pdf->Ln(2);
    $pdf->SetFont('dejavusans', '', 8.5);
    $pdf->SetTextColor(33, 37, 41);

    $html = '<table cellpadding="0" cellspacing="0" width="100%"><tr>
        <td width="55%"><span style="font-size:12pt; font-weight:bold;">' . $e($company['company_name'] ?? '') . '</span></td>
        <td width="45%" align="right"><span style="font-size:8pt; color:#0a58ca; font-weight:bold; letter-spacing:2px;">' . $e(mb_strtoupper(t('Prepaid hours report'))) . '</span><br>
            <span style="font-size:13pt; font-weight:bold;">' . $e($title) . '</span><br>
            <span style="font-size:8pt; color:' . $grey . ';">' . $e(t('{from} to {to}', ['{from}' => i18n_date_value($report['report_start']), '{to}' => i18n_date_value($report['report_end'])])) . '</span></td>
    </tr></table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(2);
    $pdf->writeHTML('<span style="font-size:10pt; font-weight:bold;">' . $e($report['client_name']) . '</span>', true, false, true, false, '');
    $pdf->Ln(1);
    if (trim((string) $report['report_intro']) !== '') {
        $pdf->writeHTML('<span>' . nl2br($e($report['report_intro'])) . '</span>', true, false, true, false, '');
        $pdf->Ln(2);
    }

    // The blocks of hours
    $html = '<table cellpadding="4" cellspacing="0" width="100%"><thead><tr>
        <td width="38%" style="' . $th . '">' . $e(mb_strtoupper(t('Hours'))) . '</td><td width="14%" align="right" style="' . $th . '">' . $e(mb_strtoupper(t('Prepaid'))) . '</td>
        <td width="18%" align="right" style="' . $th . '">' . $e(mb_strtoupper($words['used'])) . '</td><td width="14%" align="right" style="' . $th . '">' . $e(mb_strtoupper(t('Left'))) . '</td>
        <td width="16%" align="right" style="' . $th . '">' . $e(mb_strtoupper(t('Expires'))) . '</td></tr></thead>';
    foreach ($data['blocks'] as $block) {
        $html .= '<tr nobr="true"><td width="38%" style="' . $td . '">' . $e(forkHoursReportBlockName($block)) . '</td><td width="14%" align="right" style="' . $td . '">' . $e(forkHoursReportDuration($block['hours'])) . '</td>
            <td width="18%" align="right" style="' . $td . '">' . $e(forkHoursReportDuration($block['period'])) . '</td><td width="14%" align="right" style="' . $td . '"><b>' . $e(forkHoursReportDuration($block['left'])) . '</b></td>
            <td width="16%" align="right" style="' . $td . '">' . ($block['expires'] !== null ? $e(i18n_date_value($block['expires'])) : '-') . '</td></tr>';
    }
    if (!$data['blocks']) {
        $html .= '<tr><td colspan="5" align="center" style="color:' . $grey . ';">' . $e($words['no_hours']) . '</td></tr>';
    }
    $html .= '<tr><td colspan="2" align="right" style="border-top:0.6px solid #adb5bd;"><b>' . $e($words['total']) . '</b></td><td align="right" style="border-top:0.6px solid #adb5bd;"><b>' . $e(forkHoursReportDuration($data['used'])) . '</b></td>
        <td align="right" style="border-top:0.6px solid #adb5bd;"><b>' . $e(forkHoursReportDuration($data['left'])) . '</b></td><td style="border-top:0.6px solid #adb5bd;"></td></tr></table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(3);

    // The tickets
    $html = '<table cellpadding="4" cellspacing="0" width="100%"><thead><tr>
        <td width="14%" style="' . $th . '">' . $e(mb_strtoupper(t('Date'))) . '</td><td width="14%" style="' . $th . '">' . $e(mb_strtoupper(t('Ticket'))) . '</td>
        <td width="58%" style="' . $th . '">' . $e(mb_strtoupper(t('Description'))) . '</td><td width="14%" align="right" style="' . $th . '">' . $e(mb_strtoupper(t('Hours'))) . '</td></tr></thead>';
    foreach ($data['lines'] as $line) {
        $html .= '<tr nobr="true"><td width="14%" style="' . $td . '">' . $e(i18n_date_value($line['date'])) . '</td><td width="14%" style="' . $td . '">' . $e($line['reference']) . '</td>
            <td width="58%" style="' . $td . '">' . $e($line['label']) . '</td><td width="14%" align="right" style="' . $td . '">' . $e(forkHoursReportDuration($line['hours'])) . '</td></tr>';
    }
    if (!$data['lines']) {
        $html .= '<tr><td colspan="4" align="center" style="color:' . $grey . ';">' . $e($words['no_tickets']) . '</td></tr>';
    }
    $html .= '</table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    if (trim((string) $report['report_conclusion']) !== '') {
        $pdf->Ln(3);
        $pdf->writeHTML('<span>' . nl2br($e($report['report_conclusion'])) . '</span>', true, false, true, false, '');
    }

    $which = forkHoursReportIsQuarter($report['report_start'], $report['report_end'])
        ? substr($report['report_start'], 0, 4) . '-Q' . (intdiv(intval(substr($report['report_start'], 5, 2)) - 1, 3) + 1)
        : $report['report_start'] . '_' . $report['report_end'];
    $name = preg_replace('/[^A-Za-z0-9_\-]+/', '_', trim($report['report_end'] . '_' . $report['client_name'] . '_Prepaid_hours_' . $which, '_')) . '.pdf';
    return ['name' => $name, 'data' => $pdf->Output($name, 'S')];
}

/** The email that carries the report - [subject, email], in the client's language. */
function forkHoursReportEmail(array $report) {
    return billingEmailInClientLanguage(function () use ($report) {
        $title = forkHoursReportTitle($report['report_start'], $report['report_end']);
        $quarter = forkHoursReportIsQuarter($report['report_start'], $report['report_end']);
        $words = forkHoursReportWords($report['report_start'], $report['report_end']);
        $company = billingCompany();
        $contact_line = !empty($company['phone_display']) ? t('Questions? Reply to this email or call us on {phone}.', ['{phone}' => $company['phone_display']]) : t('Questions? Reply to this email.');
        return [t('Prepaid hours report - {quarter}', ['{quarter}' => $title]), [
            'kind' => t('Prepaid hours'), 'tone' => 'primary', 'title' => t('Prepaid hours report - {quarter}', ['{quarter}' => $title]),
            'intro' => [$quarter ? t('Please find attached the report of your prepaid hours for {quarter}.', ['{quarter}' => $title])
                : t('Please find attached the report of your prepaid hours for the period {from} to {to}.', ['{from}' => billingEmailDate($report['report_start']), '{to}' => billingEmailDate($report['report_end'])])],
            'summary' => [
                t('Period') => t('{from} to {to}', ['{from}' => billingEmailDate($report['report_start']), '{to}' => billingEmailDate($report['report_end'])]),
                $words['used'] => forkHoursReportDuration($report['data']['used']),
                t('Left') => forkHoursReportDuration($report['data']['left']),
            ],
            'after' => [$contact_line],
            'footnote' => billingEmailFootnote($report['client_name']),
        ]];
    });
}

/** Send the report to the client's invoice contacts, as a PDF - and lock it. Who it went to ("Name (email)"), or [] when there is nobody. */
function forkHoursReportSend(array $report, $user_id) {
    global $mysqli;
    $recipients = billingEmailRecipients($report['report_client_id']);
    if (!$recipients) {
        return [];
    }
    $pdf = billingEmailPdf(forkHoursReportPdf($report));
    [$subject, $email] = forkHoursReportEmail($report);
    $sent = billingEmailQueue($recipients, $subject, $email, ['attach' => $pdf ? [$pdf] : []]);
    mysqli_query($mysqli, "UPDATE fork_hours_reports SET report_status = 'sent', report_sent_at = NOW(), report_sent_by = " . intval($user_id) . ",
        report_sent_to = '" . escapeSql(is_array($sent) ? implode(', ', $sent) : (string) $sent) . "' WHERE report_id = " . intval($report['report_id']));
    return is_array($sent) ? $sent : [(string) $sent];
}
