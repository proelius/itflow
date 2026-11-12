<?php

/*
 * To-do pages, shared parts (functions/fork_todo.php): the badges and the form of a task.
 */

/** A hidden CSRF field. */
function todoCsrf() {
    return '<input type="hidden" name="csrf_token" value="' . escapeHtml($_SESSION['csrf_token']) . '">';
}

/** The tabs of the to-do pages. $active: mine, given, done, all. */
function todoTabs($active, $priority = 0, $search = '') {
    global $session_is_admin;
    $tabs = ['mine' => ['fa-user-check', 'Mine'], 'given' => ['fa-share', 'Given by me'], 'done' => ['fa-check-double', 'Done']];
    if (!empty($session_is_admin)) {
        $tabs['all'] = ['fa-users', 'Everyone\'s'];
    }
    $html = '<ul class="nav nav-pills mb-3">';
    foreach ($tabs as $key => [$icon, $label]) {
        $html .= '<li class="nav-item"><a class="nav-link' . ($key === $active ? ' active' : '') . '" href="todo.php?view=' . $key
            . ($priority ? '&amp;priority=' . intval($priority) : '') . ($search !== '' ? '&amp;q=' . urlencode($search) : '')
            . '"><i class="fas fa-fw ' . $icon . ' me-2"></i>' . $label . '</a></li>';
    }
    return $html . '</ul>';
}

/** A priority as a coloured badge. */
function todoPriorityBadge($priority) {
    $priority = forkTodoPriorityClean($priority);
    $class = [1 => 'bg-secondary', 2 => 'bg-info text-dark', 3 => 'bg-warning text-dark', 4 => 'bg-danger'][$priority];
    return '<span class="badge ' . $class . '">' . escapeHtml(forkTodoPriorities()[$priority]) . '</span>';
}

/** A deadline with a colour for how close it is: red late, orange today, blue within a week, grey after. */
function todoDueBadge(array $task) {
    $state = forkTodoDueState($task['todo_due'], substr((string) $task['todo_due_time'], 0, 5), time());
    if ($state === 'none') {
        return '<span class="text-secondary">-</span>';
    }
    $class = ['overdue' => 'bg-danger', 'today' => 'bg-warning text-dark', 'soon' => 'bg-info text-dark', 'later' => 'bg-secondary'][$state];
    $label = $task['todo_due'] . ($task['todo_due_time'] ? ' ' . substr($task['todo_due_time'], 0, 5) : '');
    return '<span class="badge ' . $class . '">' . escapeHtml($label) . '</span>';
}

/**
 * The fields of a task, for a new one ($task null) or to change one. $users: the agents a task can be given to.
 * The reminders and the "still late" repeat are those the settings offer for a new task.
 */
function todoFormFields($task, array $users) {
    global $session_user_id;
    $priority = $task ? $task['todo_priority'] : 2;
    $assignee = $task ? $task['todo_assignee_id'] : $session_user_id;
    $chosen = $task ? array_keys(forkTodoReminders($task['todo_id'])) : forkTodoReminderClean(forkTodoSetting('default_reminders'));
    $repeat = $task ? intval($task['todo_overdue_repeat']) === 1 : forkTodoSetting('overdue_repeat') === '1';
    $names = [];
    foreach ($users as $user_id => $user) {
        $names[$user_id] = $user['name'] . ($user_id === intval($session_user_id) ? ' (me)' : '');
    }
    $html = '<div class="row g-2 mb-2">'
        . '<div class="col-md-8"><label class="form-label">Title <span class="text-danger">*</span></label><input type="text" class="form-control" name="title" maxlength="200" required value="' . escapeHtml($task['todo_title'] ?? '') . '"></div>'
        . '<div class="col-md-4"><label class="form-label">Priority</label><select class="form-select" name="priority">' . crmOptions(forkTodoPriorities(), $priority) . '</select></div>'
        . '</div>'
        . '<div class="mb-2"><label class="form-label">Details</label><textarea class="form-control" name="description" rows="3" maxlength="5000">' . escapeHtml($task['todo_description'] ?? '') . '</textarea></div>'
        . '<div class="row g-2 mb-2">'
        . '<div class="col-md-4"><label class="form-label">Given to</label><select class="form-select" name="assignee_id">' . crmOptions($names, $assignee) . '</select></div>'
        . '<div class="col-md-4"><label class="form-label">Deadline</label><input type="date" class="form-control" name="due" value="' . escapeHtml($task['todo_due'] ?? '') . '"></div>'
        . '<div class="col-md-4"><label class="form-label">Time <span class="text-secondary">(optional)</span></label><input type="time" class="form-control" name="due_time" value="' . escapeHtml(substr((string) ($task['todo_due_time'] ?? ''), 0, 5)) . '"></div>'
        . '</div>'
        . '<div class="mb-2"><label class="form-label">Reminders <span class="text-secondary">(need a deadline; a deadline with no time counts from ' . escapeHtml(forkTodoSetting('all_day_time')) . ')</span></label><div>';
    foreach (forkTodoReminderChoices() as $minutes => $label) {
        $html .= '<div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="reminders[]" id="rem' . $minutes . '" value="' . $minutes . '"' . (in_array($minutes, $chosen, true) ? ' checked' : '') . '>'
            . '<label class="form-check-label" for="rem' . $minutes . '">' . escapeHtml($label) . '</label></div>';
    }
    $html .= '</div></div>'
        . '<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="overdue_repeat" id="overdueRepeat" value="1"' . ($repeat ? ' checked' : '') . '><label class="form-check-label" for="overdueRepeat">Remind me every day once it is late</label></div>';
    return $html;
}
