<?php

/*
 * ITFlow - To-do list (functions/fork_todo.php): tasks that an agent gives to themselves or to a colleague, with a
 * priority, a deadline and reminders. Every agent may use it; one sees the tasks given to or by them, an administrator all.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** A task the agent may change, or back to the list. */
function todoPostTask($todo_id) {
    global $session_user_id, $session_is_admin;
    $task = forkTodoGet($todo_id);
    if (!$task || !forkTodoCanEdit($task, $session_user_id, !empty($session_is_admin))) {
        flashAlert("That task is gone or is not yours.", 'error');
        redirect('todo.php');
    }
    return $task;
}

/** The fields of a task as posted. */
function todoPostData() {
    return [
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'priority' => $_POST['priority'] ?? 2,
        'due' => $_POST['due'] ?? '',
        'due_time' => $_POST['due_time'] ?? '',
        'assignee_id' => $_POST['assignee_id'] ?? 0,
        'reminders' => (array) ($_POST['reminders'] ?? []),
        'overdue_repeat' => $_POST['overdue_repeat'] ?? 0,
    ];
}

if (isset($_POST['add_todo'])) {

    validateCSRFToken();
    [$todo_id, $error] = forkTodoSave(todoPostData(), $session_user_id);
    if ($error !== '') {
        flashAlert($error, 'error');
        redirect('todo.php');
    }
    logAudit("To-do", "Create", "$session_name added the to-do " . escapeSql(forkTodoTitleClean($_POST['title'] ?? '')), 0, $todo_id);
    flashAlert("Task <strong>" . escapeHtml(forkTodoTitleClean($_POST['title'] ?? '')) . "</strong> added");
    redirect('todo.php');

}

if (isset($_POST['edit_todo'])) {

    validateCSRFToken();
    $task = todoPostTask($_POST['todo_id'] ?? 0);
    [$todo_id, $error] = forkTodoSave(todoPostData(), $session_user_id, $task['todo_id']);
    if ($error !== '') {
        flashAlert($error, 'error');
        redirect('todo_task.php?todo_id=' . intval($task['todo_id']));
    }
    logAudit("To-do", "Edit", "$session_name changed the to-do " . escapeSql($task['todo_title']), 0, $todo_id);
    flashAlert("Task <strong>" . escapeHtml(forkTodoTitleClean($_POST['title'] ?? '')) . "</strong> saved");
    redirect('todo_task.php?todo_id=' . intval($todo_id));

}

if (isset($_POST['complete_todo'])) {

    validateCSRFToken();
    $task = todoPostTask($_POST['todo_id'] ?? 0);
    forkTodoComplete($task['todo_id'], $session_user_id);
    logAudit("To-do", "Edit", "$session_name finished the to-do " . escapeSql($task['todo_title']), 0, intval($task['todo_id']));
    flashAlert("Task <strong>" . escapeHtml($task['todo_title']) . "</strong> done");
    redirect();

}

if (isset($_POST['reopen_todo'])) {

    validateCSRFToken();
    $task = todoPostTask($_POST['todo_id'] ?? 0);
    forkTodoReopen($task['todo_id'], $session_user_id);
    logAudit("To-do", "Edit", "$session_name reopened the to-do " . escapeSql($task['todo_title']), 0, intval($task['todo_id']));
    flashAlert("Task <strong>" . escapeHtml($task['todo_title']) . "</strong> reopened");
    redirect();

}

if (isset($_POST['delete_todo'])) {

    validateCSRFToken();
    $task = todoPostTask($_POST['todo_id'] ?? 0);
    if (!forkTodoCanDelete($task, $session_user_id, !empty($session_is_admin))) {
        flashAlert("Only the one who gave a task, or an administrator, can delete it.", 'error');
        redirect('todo_task.php?todo_id=' . intval($task['todo_id']));
    }
    forkTodoDelete($task['todo_id']);
    logAudit("To-do", "Delete", "$session_name deleted the to-do " . escapeSql($task['todo_title']), 0, intval($task['todo_id']));
    flashAlert("Task <strong>" . escapeHtml($task['todo_title']) . "</strong> deleted", 'error');
    redirect('todo.php');

}

if (isset($_POST['save_todo_settings'])) {

    validateCSRFToken();
    if (empty($session_is_admin)) {
        permissionRefuse("Only an administrator changes the to-do settings.");
    }
    forkTodoSettingSet('all_day_time', forkTodoTimeClean($_POST['all_day_time'] ?? '') ?? '08:00');
    forkTodoSettingSet('default_reminders', implode(',', forkTodoReminderClean((array) ($_POST['default_reminders'] ?? []))));
    forkTodoSettingSet('overdue_repeat', !empty($_POST['overdue_repeat']) ? '1' : '0');
    forkTodoSettingSet('email', !empty($_POST['email']) ? '1' : '0');
    logAudit("To-do", "Edit", "$session_name changed the to-do settings", 0, 0);
    flashAlert("To-do settings saved");
    redirect();

}
