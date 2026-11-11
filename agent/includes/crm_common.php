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

/** The postal address fields of a prospect: street, zip, city, state, country. $required marks them so. */
function crmAddressFields($required) {
    global $session_company_country;
    $req = $required ? ' required' : '';
    $star = $required ? ' <span class="text-danger">*</span>' : '';
    $html = '<div class="row g-2 mb-2">'
        . '<div class="col-md-5"><label class="form-label">Street and number' . $star . '</label><input type="text" class="form-control" name="address" maxlength="200"' . $req . '></div>'
        . '<div class="col-md-2"><label class="form-label">Postal code' . $star . '</label><input type="text" class="form-control" name="zip" maxlength="200"' . $req . '></div>'
        . '<div class="col-md-5"><label class="form-label">City' . $star . '</label><input type="text" class="form-control" name="city" maxlength="200"' . $req . '></div>'
        . '<div class="col-md-5"><label class="form-label">State / canton</label><input type="text" class="form-control" name="state" maxlength="200"></div>'
        . '<div class="col-md-5"><label class="form-label">Country' . $star . '</label><select class="form-select" name="country"' . $req . '>';
    foreach (settingsLocalizationArrays()['countries'] as $country) {
        $html .= '<option' . ($country === ($session_company_country ?? '') ? ' selected' : '') . '>' . escapeHtml($country) . '</option>';
    }
    return $html . '</select></div></div>';
}

/** The fields of a next step - what, when, how long, who - named next_*; for the activity forms. */
function crmNextStepFields(array $users, $default_user) {
    $types = array_diff_key(forkCrmActivityTypes(), ['note' => 1]);
    return '<div class="row g-2">'
        . '<div class="col-md-3"><label class="form-label small">What</label><select class="form-select form-select-sm" name="next_type">' . crmOptions($types, 'meeting') . '</select></div>'
        . '<div class="col-md-3"><label class="form-label small">Date</label><input type="date" class="form-control form-control-sm" name="next_date"></div>'
        . '<div class="col-md-2"><label class="form-label small">Time</label><input type="time" class="form-control form-control-sm" name="next_time"></div>'
        . '<div class="col-md-2"><label class="form-label small">Length</label><select class="form-select form-select-sm" name="next_minutes">' . crmOptions(forkCrmMinutesChoices(), 60) . '</select></div>'
        . '<div class="col-md-2"><label class="form-label small">Who</label><select class="form-select form-select-sm" name="next_assignee"><option value="0">Anyone</option>' . crmOptions($users, $default_user) . '</select></div>'
        . '<div class="col-12"><input type="text" class="form-control form-control-sm" name="next_text" maxlength="500" placeholder="What it is about (optional)"></div>'
        . '</div>';
}

/** A planned activity's date, with its time and length, for the lists. */
function crmWhenBadge(array $activity) {
    $badge = crmDueBadge($activity['activity_due']);
    $time = forkCrmTimeClean($activity['activity_due_time'] ?? '');
    $text = $time !== null ? ' <span class="small">' . escapeHtml($time) . (intval($activity['activity_minutes'] ?? 0) > 0 ? ' &middot; ' . escapeHtml(forkCrmMinutesChoices()[intval($activity['activity_minutes'])] ?? '') : '') . '</span>' : '';
    return $badge . $text;
}
