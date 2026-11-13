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
