<?php

/*
 * CRM pages, shared parts (functions/fork_crm.php): the tabs at the top and the small pieces
 * that show a deal's date, money and tasks the same way everywhere.
 */

/** The tabs of the CRM pages. $active: pipeline, tasks, dashboard, stages. */
function crmTabs($active) {
    $tabs = [
        'pipeline' => ['crm.php', 'fa-columns', 'Pipeline', 1],
        'tasks' => ['crm_tasks.php', 'fa-tasks', 'Tasks', 1],
        'dashboard' => ['crm_dashboard.php', 'fa-chart-line', 'Dashboard', 1],
        'stages' => ['crm_stages.php', 'fa-sliders-h', 'Stages', 3],
    ];
    $html = '<ul class="nav nav-pills mb-3">';
    foreach ($tabs as $key => [$href, $icon, $label, $level]) {
        if (forkCrmLevel() < $level) {
            continue;
        }
        $html .= '<li class="nav-item"><a class="nav-link' . ($key === $active ? ' active' : '') . '" href="' . $href . '"><i class="fas fa-fw ' . $icon . ' me-2"></i>' . $label . '</a></li>';
    }
    return $html . '</ul>';
}

/** A date with a colour for how close it is: red late, orange today, plain after. */
function crmDueBadge($date) {
    $state = forkCrmDueState($date, date('Y-m-d'));
    if ($state === 'none') {
        return '<span class="text-secondary">-</span>';
    }
    $class = ['overdue' => 'bg-danger', 'today' => 'bg-warning text-dark', 'soon' => 'bg-info text-dark', 'later' => 'bg-secondary'][$state];
    return '<span class="badge ' . $class . '">' . escapeHtml($date) . '</span>';
}

/** A percentage with one decimal, or a dash. */
function crmPercent($value) {
    return $value === null ? '-' : rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.') . ' %';
}

/** A hidden CSRF field. */
function crmCsrf() {
    return '<input type="hidden" name="csrf_token" value="' . escapeHtml($_SESSION['csrf_token']) . '">';
}

/** The options of a select: [value => label], with $selected chosen. */
function crmOptions(array $choices, $selected) {
    $html = '';
    foreach ($choices as $value => $label) {
        $html .= '<option value="' . escapeHtml($value) . '"' . ((string) $value === (string) $selected ? ' selected' : '') . '>' . escapeHtml($label) . '</option>';
    }
    return $html;
}
