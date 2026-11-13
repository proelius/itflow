<?php

/*
 * Backup checks pages, shared parts (functions/fork_backup.php).
 */

function backupCsrf() {
    return '<input type="hidden" name="csrf_token" value="' . escapeHtml($_SESSION['csrf_token']) . '">';
}

/** The <option>s of a list (value => label), $selected chosen. */
function backupOptions(array $options, $selected) {
    $html = '';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . escapeHtml($value) . '"' . ((string) $value === (string) $selected ? ' selected' : '') . '>' . escapeHtml($label) . '</option>';
    }
    return $html;
}

/** The <optgroup>s of a list of groups (label => [value => label]). */
function backupOptionGroups(array $groups, $selected) {
    $html = '';
    foreach ($groups as $group => $options) {
        $html .= '<optgroup label="' . escapeHtml($group) . '">' . backupOptions($options, $selected) . '</optgroup>';
    }
    return $html;
}

/** A colour as a badge. */
function backupStateBadge($state) {
    $class = ['green' => 'bg-success', 'yellow' => 'bg-warning text-dark', 'red' => 'bg-danger'][$state] ?? 'bg-secondary';
    return '<span class="badge ' . $class . '">' . escapeHtml(forkBackupStates()[$state] ?? '-') . '</span>';
}

/** The tabs of the client's backup pages: the service and checks, and the reports - both with the Financial module, none without it. */
function backupClientTabs($client_id, $active) {
    if (lookupUserPermission('module_financial') < 1) {
        return '';
    }
    $tabs = ['service' => ['backup_client.php', 'fa-server', 'Service and checks'], 'reports' => ['backup_reports.php', 'fa-file-alt', 'Reports']];
    $html = '<ul class="nav nav-pills mb-3">';
    foreach ($tabs as $key => [$page, $icon, $label]) {
        $html .= '<li class="nav-item"><a class="nav-link' . ($key === $active ? ' active' : '') . '" href="' . $page . '?client_id=' . intval($client_id) . '"><i class="fas fa-fw ' . $icon . ' me-2"></i>' . $label . '</a></li>';
    }
    return $html . '</ul>';
}

/** What is kept for a client, in words: "12 checks, 3 reports and 2 servers". */
function backupDataText(array $data) {
    $n = fn($count, $word) => $count . ' ' . $word . ($count === 1 ? '' : 's');
    return $n($data['checks'], 'check') . ', ' . $n($data['reports'], 'report') . ' and ' . $n($data['servers'], 'server');
}

/** The checks entered for a client (the rows of its page): the week, the worst colour, who entered it, and a link. */
function backupChecksTable(array $checks, $client_id) {
    if (!$checks) {
        return '<div class="card-body text-secondary">Nothing entered yet.</div>';
    }
    $html = '<div class="table-responsive"><table class="table table-hover mb-0"><thead class="text-dark"><tr><th>Week of</th><th>Result</th><th>By</th><th></th></tr></thead><tbody>';
    foreach ($checks as $check) {
        $html .= '<tr><td>' . escapeHtml($check['bk_week']) . '</td><td>' . backupStateBadge(forkBackupWorst(explode(',', $check['states']))) . '</td><td>' . escapeHtml((string) $check['user_name']) . '</td>'
            . '<td class="text-end"><a class="btn btn-sm btn-secondary" href="backup_check.php?client_id=' . intval($client_id) . '&week=' . escapeHtml($check['bk_week']) . '"><i class="fas fa-eye"></i></a></td></tr>';
    }
    return $html . '</tbody></table></div>';
}
