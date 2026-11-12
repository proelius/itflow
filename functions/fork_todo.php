<?php

/*
 * To-do list - personal tasks that an agent gives to themselves or to a colleague.
 *
 * Not tickets (they are for clients) and not CRM tasks (they hang on a deal): a plain list with a title,
 * a priority, an optional deadline (a date, with a time if wanted) and reminders chosen per task.
 * Anyone can give a task to themselves or to another agent; the one who receives it is told. The reminders
 * (some time before the deadline, at the deadline, then each day while late) are sent by cron/todo_reminders.php
 * as an ITFlow notification and, if the settings say so, an email.
 *
 * Who sees what: an agent sees the tasks given to them and the ones they gave; an administrator sees all.
 * The one who has a task or gave it can change or finish it; only the one who gave it, or an administrator,
 * can delete it.
 *
 * The tables are fork_todo_*: made when first needed, not part of ITFlow's migrations (like the CRM's).
 * What can be decided from plain values (priorities, dates, reminders, rights) takes no database and is
 * tested by scripts/fork_todo_selftest.php.
 */

/* ---------- plain values ---------- */

/** The priorities: number => name. A higher number is more pressing. */
function forkTodoPriorities() {
    return [1 => 'Low', 2 => 'Normal', 3 => 'High', 4 => 'Urgent'];
}

/** A priority as a number, Normal for anything unknown. */
function forkTodoPriorityClean($priority) {
    $priority = intval($priority);
    return isset(forkTodoPriorities()[$priority]) ? $priority : 2;
}

/** A title on one line, 200 characters at most. */
function forkTodoTitleClean($title) {
    return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $title)), 0, 200);
}

/** A free text, trimmed, $max characters at most. */
function forkTodoTextClean($text, $max = 5000) {
    return mb_substr(trim(str_replace("\r", '', (string) $text)), 0, $max);
}

/** A date as Y-m-d, or null when it is empty or not a real date. */
function forkTodoDateClean($date) {
    $date = trim((string) $date);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || !checkdate(intval($m[2]), intval($m[3]), intval($m[1]))) {
        return null;
    }
    return $date;
}

/** A time as H:i, or null when it is empty or not a time. */
function forkTodoTimeClean($time) {
    $time = trim((string) $time);
    if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $time, $m)) {
        return null;
    }
    return sprintf('%02d:%s', intval($m[1]), $m[2]);
}

/**
 * The moment a task is due, as a timestamp, or null with no date. A task with no time is due at $all_day (H:i)
 * of its day, which is when its reminders count from.
 */
function forkTodoDueMoment($date, $time, $all_day = '08:00') {
    $date = forkTodoDateClean($date);
    if ($date === null) {
        return null;
    }
    $time = forkTodoTimeClean($time) ?? (forkTodoTimeClean($all_day) ?? '08:00');
    return strtotime("$date $time:00");
}

/** How long before the deadline a reminder can be: minutes => label (0 is the deadline itself). */
function forkTodoReminderChoices() {
    return [
        0 => 'When it is due',
        15 => '15 minutes before',
        30 => '30 minutes before',
        60 => '1 hour before',
        180 => '3 hours before',
        1440 => '1 day before',
        2880 => '2 days before',
        10080 => '1 week before',
    ];
}

/** A list of reminders (an array or a text "1440,60") as the valid minutes, the longest first, no doubles. */
function forkTodoReminderClean($list) {
    if (!is_array($list)) {
        $list = explode(',', (string) $list);
    }
    $out = [];
    foreach ($list as $minutes) {
        if ($minutes === '' || $minutes === null || !is_numeric($minutes)) {
            continue;
        }
        $minutes = intval($minutes);
        if (isset(forkTodoReminderChoices()[$minutes])) {
            $out[$minutes] = $minutes;
        }
    }
    rsort($out);
    return array_values($out);
}

/**
 * Where a reminder stands: 'wait' (its time has not come), 'fire' (send it) or 'skip' (its time went by more than
 * an hour ago - cron was off, or the task was made late - so it is dropped, not sent late).
 */
function forkTodoReminderAction($moment, $minutes, $now) {
    if ($moment === null) {
        return 'wait';
    }
    $at = $moment - intval($minutes) * 60;
    if ($now < $at) {
        return 'wait';
    }
    return $now <= $at + 3600 ? 'fire' : 'skip';
}

/**
 * How close a deadline is: none, overdue, today, soon (the next 7 days) or later. A deadline with no time is
 * late from the next day; with a time, from that minute.
 */
function forkTodoDueState($date, $time, $now) {
    $date = forkTodoDateClean($date);
    if ($date === null) {
        return 'none';
    }
    $today = date('Y-m-d', $now);
    $time = forkTodoTimeClean($time);
    if ($time !== null && strtotime("$date $time:00") < $now) {
        return 'overdue';
    }
    if ($date < $today) {
        return 'overdue';
    }
    if ($date === $today) {
        return 'today';
    }
    return $date <= date('Y-m-d', strtotime($today . ' +7 days')) ? 'soon' : 'later';
}

/**
 * Whether the daily "still late" reminder of an open task is due: it starts the day after the deadline and is
 * sent once a day ($notified_on is the day of the last one).
 */
function forkTodoOverdueDue($date, $notified_on, $now) {
    $date = forkTodoDateClean($date);
    if ($date === null) {
        return false;
    }
    $today = date('Y-m-d', $now);
    return $today > $date && (string) $notified_on !== $today;
}

/** May this agent see the task? Given to them, given by them, or an administrator. $task: todo_assignee_id, todo_created_by. */
function forkTodoCanView(array $task, $user_id, $is_admin) {
    $user_id = intval($user_id);
    return $is_admin || intval($task['todo_assignee_id']) === $user_id || intval($task['todo_created_by']) === $user_id;
}

/** May this agent change or finish the task? Same as seeing it. */
function forkTodoCanEdit(array $task, $user_id, $is_admin) {
    return forkTodoCanView($task, $user_id, $is_admin);
}

/** May this agent delete the task? Only the one who gave it, or an administrator. */
function forkTodoCanDelete(array $task, $user_id, $is_admin) {
    return $is_admin || intval($task['todo_created_by']) === intval($user_id);
}

/* ---------- settings ---------- */

/** The settings and what they are when nobody changed them. */
function forkTodoSettingDefaults() {
    return [
        'all_day_time' => '08:00',       // when a task with a date and no time is due, for its reminders
        'default_reminders' => '1440,60', // reminders offered on a new task
        'overdue_repeat' => '1',          // a new task reminds each day once late
        'email' => '1',                   // reminders and assignments also go by email
    ];
}

/** One setting, as text. */
function forkTodoSetting($key) {
    global $mysqli;
    static $values = null;
    $defaults = forkTodoSettingDefaults();
    if ($values === null) {
        $values = $defaults;
        forkTodoEnsure();
        $result = mysqli_query($mysqli, "SELECT setting_key, setting_value FROM fork_todo_settings");
        while ($result && ($row = mysqli_fetch_assoc($result))) {
            if (array_key_exists($row['setting_key'], $defaults)) {
                $values[$row['setting_key']] = (string) $row['setting_value'];
            }
        }
    }
    return $values[$key] ?? '';
}

/** Save one setting. */
function forkTodoSettingSet($key, $value) {
    global $mysqli;
    if (!array_key_exists($key, forkTodoSettingDefaults())) {
        return;
    }
    forkTodoEnsure();
    mysqli_query($mysqli, "REPLACE INTO fork_todo_settings SET setting_key = '" . escapeSql($key) . "', setting_value = '" . escapeSql((string) $value) . "'");
}

/* ---------- tables ---------- */

/** Make the tables when they are not there yet. */
function forkTodoEnsure() {
    global $mysqli;
    static $ready = false;
    if ($ready) {
        return;
    }
    $ready = true;
    $tail = ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `fork_todo_tasks` (
        `todo_id` int(11) NOT NULL AUTO_INCREMENT,
        `todo_title` varchar(200) NOT NULL,
        `todo_description` text DEFAULT NULL,
        `todo_priority` tinyint(3) unsigned NOT NULL DEFAULT 2,
        `todo_due` date DEFAULT NULL,
        `todo_due_time` time DEFAULT NULL,
        `todo_assignee_id` int(11) NOT NULL DEFAULT 0,
        `todo_created_by` int(11) NOT NULL DEFAULT 0,
        `todo_overdue_repeat` tinyint(1) NOT NULL DEFAULT 1,
        `todo_overdue_notified_on` date DEFAULT NULL,
        `todo_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `todo_updated_at` datetime NOT NULL DEFAULT current_timestamp(),
        `todo_done_at` datetime DEFAULT NULL,
        `todo_done_by` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`todo_id`),
        KEY `todo_assignee_id` (`todo_assignee_id`),
        KEY `todo_created_by` (`todo_created_by`)
    $tail");
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `fork_todo_reminders` (
        `reminder_id` int(11) NOT NULL AUTO_INCREMENT,
        `reminder_todo_id` int(11) NOT NULL,
        `reminder_minutes` int(11) NOT NULL DEFAULT 0,
        `reminder_sent_at` datetime DEFAULT NULL,
        PRIMARY KEY (`reminder_id`),
        KEY `reminder_todo_id` (`reminder_todo_id`)
    $tail");
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `fork_todo_log` (
        `log_id` int(11) NOT NULL AUTO_INCREMENT,
        `log_todo_id` int(11) NOT NULL,
        `log_user_id` int(11) NOT NULL DEFAULT 0,
        `log_text` varchar(255) NOT NULL,
        `log_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`log_id`),
        KEY `log_todo_id` (`log_todo_id`)
    $tail");
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `fork_todo_settings` (
        `setting_key` varchar(40) NOT NULL,
        `setting_value` varchar(255) NOT NULL DEFAULT '',
        PRIMARY KEY (`setting_key`)
    $tail");
}

/* ---------- people ---------- */

/** The agents a task can be given to: [user_id => ['name', 'email', 'admin']]. */
function forkTodoUsers() {
    global $mysqli;
    $out = [];
    $result = mysqli_query($mysqli, "SELECT u.user_id, u.user_name, u.user_email, r.role_is_admin FROM users u JOIN user_roles r ON r.role_id = u.user_role_id
        WHERE u.user_status = 1 AND u.user_archived_at IS NULL ORDER BY u.user_name");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $out[intval($row['user_id'])] = ['name' => $row['user_name'], 'email' => $row['user_email'], 'admin' => intval($row['role_is_admin']) === 1];
    }
    return $out;
}

/* ---------- reading ---------- */

/** One task with the names of who has it and who gave it, or null. */
function forkTodoGet($todo_id) {
    global $mysqli;
    forkTodoEnsure();
    $result = mysqli_query($mysqli, "SELECT t.*, a.user_name AS assignee_name, c.user_name AS creator_name FROM fork_todo_tasks t
        LEFT JOIN users a ON a.user_id = t.todo_assignee_id LEFT JOIN users c ON c.user_id = t.todo_created_by WHERE t.todo_id = " . intval($todo_id));
    $row = $result ? mysqli_fetch_assoc($result) : null;
    return $row ?: null;
}

/**
 * Tasks for a list. $view: 'mine' (given to me, open), 'given' (given by me to someone else, open), 'done' (finished,
 * mine or given by me, the last 100), 'all' (every open task - administrators). $priority 0 = any; $search matches the title and text.
 */
function forkTodoList($user_id, $view, $priority = 0, $search = '') {
    global $mysqli;
    forkTodoEnsure();
    $user_id = intval($user_id);
    $where = ['1'];
    if ($view === 'done') {
        $where[] = "t.todo_done_at IS NOT NULL AND (t.todo_assignee_id = $user_id OR t.todo_created_by = $user_id)";
    } else {
        $where[] = "t.todo_done_at IS NULL";
        if ($view === 'given') {
            $where[] = "t.todo_created_by = $user_id AND t.todo_assignee_id <> $user_id";
        } elseif ($view !== 'all') {
            $where[] = "t.todo_assignee_id = $user_id";
        }
    }
    if (forkTodoPriorityClean($priority) === intval($priority) && intval($priority) > 0) {
        $where[] = "t.todo_priority = " . intval($priority);
    }
    $search = trim((string) $search);
    if ($search !== '') {
        $like = escapeSql('%' . addcslashes($search, '%_\\') . '%');
        $where[] = "(t.todo_title LIKE '$like' OR t.todo_description LIKE '$like')";
    }
    $order = $view === 'done' ? "t.todo_done_at DESC LIMIT 100" : "t.todo_due IS NULL, t.todo_due, t.todo_due_time IS NULL, t.todo_due_time, t.todo_priority DESC, t.todo_id";
    $out = [];
    $result = mysqli_query($mysqli, "SELECT t.*, a.user_name AS assignee_name, c.user_name AS creator_name FROM fork_todo_tasks t
        LEFT JOIN users a ON a.user_id = t.todo_assignee_id LEFT JOIN users c ON c.user_id = t.todo_created_by
        WHERE " . implode(' AND ', $where) . " ORDER BY $order");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $out[] = $row;
    }
    return $out;
}

/** The reminders of a task: [minutes => sent_at or null]. */
function forkTodoReminders($todo_id) {
    global $mysqli;
    forkTodoEnsure();
    $out = [];
    $result = mysqli_query($mysqli, "SELECT reminder_minutes, reminder_sent_at FROM fork_todo_reminders WHERE reminder_todo_id = " . intval($todo_id));
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $out[intval($row['reminder_minutes'])] = $row['reminder_sent_at'];
    }
    krsort($out);
    return $out;
}

/** What happened to a task, newest first: [text, at, who]. */
function forkTodoLog($todo_id) {
    global $mysqli;
    forkTodoEnsure();
    $out = [];
    $result = mysqli_query($mysqli, "SELECT l.log_text, l.log_at, u.user_name FROM fork_todo_log l LEFT JOIN users u ON u.user_id = l.log_user_id
        WHERE l.log_todo_id = " . intval($todo_id) . " ORDER BY l.log_id DESC");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $out[] = $row;
    }
    return $out;
}

/** How many open tasks given to this agent are late or due today - for the menu. */
function forkTodoDueCount($user_id) {
    global $mysqli;
    forkTodoEnsure();
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM fork_todo_tasks WHERE todo_done_at IS NULL AND todo_assignee_id = " . intval($user_id) . "
        AND todo_due IS NOT NULL AND todo_due <= '" . date('Y-m-d') . "'"));
    return $row ? intval($row[0]) : 0;
}

/* ---------- writing ---------- */

/** One line in the history of a task. */
function forkTodoLogAdd($todo_id, $text, $user_id) {
    global $mysqli;
    mysqli_query($mysqli, "INSERT INTO fork_todo_log SET log_todo_id = " . intval($todo_id) . ", log_user_id = " . intval($user_id) . ", log_text = '" . escapeSql(mb_substr($text, 0, 255)) . "'");
}

/**
 * Make or change a task. $data: title, description, priority, due, due_time, assignee_id, reminders (array of minutes),
 * overdue_repeat (0/1). $todo_id 0 makes one. Returns [todo_id, error text or '']. The one who receives a task from
 * someone else is told; the reminders whose time already went by are marked sent, so a late task does not fire them at once.
 */
function forkTodoSave(array $data, $user_id, $todo_id = 0) {
    global $mysqli;
    forkTodoEnsure();
    $user_id = intval($user_id);
    $todo_id = intval($todo_id);
    $users = forkTodoUsers();

    $title = forkTodoTitleClean($data['title'] ?? '');
    if ($title === '') {
        return [0, 'A task needs a title.'];
    }
    $assignee = intval($data['assignee_id'] ?? 0) ?: $user_id;
    if (!isset($users[$assignee])) {
        return [0, 'That agent cannot be given a task.'];
    }
    $description = forkTodoTextClean($data['description'] ?? '');
    $priority = forkTodoPriorityClean($data['priority'] ?? 2);
    $due = forkTodoDateClean($data['due'] ?? '');
    $time = $due !== null ? forkTodoTimeClean($data['due_time'] ?? '') : null;
    $reminders = $due !== null ? forkTodoReminderClean($data['reminders'] ?? []) : [];
    $repeat = !empty($data['overdue_repeat']) ? 1 : 0;

    $old = $todo_id > 0 ? forkTodoGet($todo_id) : null;
    if ($todo_id > 0 && !$old) {
        return [0, 'That task does not exist.'];
    }

    $due_sql = $due !== null ? "'$due'" : 'NULL';
    $time_sql = $time !== null ? "'$time:00'" : 'NULL';
    $fields = "todo_title = '" . escapeSql($title) . "', todo_description = '" . escapeSql($description) . "', todo_priority = $priority,
        todo_due = $due_sql, todo_due_time = $time_sql, todo_assignee_id = $assignee, todo_overdue_repeat = $repeat, todo_updated_at = NOW()";

    $due_changed = true;
    if ($old) {
        $due_changed = (string) $old['todo_due'] !== (string) $due || substr((string) $old['todo_due_time'], 0, 5) !== (string) $time;
        if ($due_changed) {
            $fields .= ", todo_overdue_notified_on = NULL";
        }
        mysqli_query($mysqli, "UPDATE fork_todo_tasks SET $fields WHERE todo_id = $todo_id");
    } else {
        mysqli_query($mysqli, "INSERT INTO fork_todo_tasks SET $fields, todo_created_by = $user_id");
        $todo_id = mysqli_insert_id($mysqli);
    }

    // Reminders: keep what was already sent while the deadline did not move; otherwise drop the ones whose time is past
    $sent = $old && !$due_changed ? forkTodoReminders($todo_id) : [];
    mysqli_query($mysqli, "DELETE FROM fork_todo_reminders WHERE reminder_todo_id = $todo_id");
    $moment = forkTodoDueMoment($due, $time, forkTodoSetting('all_day_time'));
    $now = time();
    foreach ($reminders as $minutes) {
        $sent_sql = 'NULL';
        if (!empty($sent[$minutes])) {
            $sent_sql = "'" . escapeSql($sent[$minutes]) . "'";
        } elseif ($moment !== null && $now >= $moment - $minutes * 60) {
            $sent_sql = 'NOW()';
        }
        mysqli_query($mysqli, "INSERT INTO fork_todo_reminders SET reminder_todo_id = $todo_id, reminder_minutes = $minutes, reminder_sent_at = $sent_sql");
    }

    // History and who is told
    if (!$old) {
        forkTodoLogAdd($todo_id, 'Created' . ($assignee !== $user_id ? ' for ' . $users[$assignee]['name'] : ''), $user_id);
    } else {
        if (intval($old['todo_assignee_id']) !== $assignee) {
            forkTodoLogAdd($todo_id, 'Given to ' . $users[$assignee]['name'], $user_id);
        }
        if (intval($old['todo_priority']) !== $priority) {
            forkTodoLogAdd($todo_id, 'Priority set to ' . forkTodoPriorities()[$priority], $user_id);
        }
        if ($due_changed) {
            forkTodoLogAdd($todo_id, $due === null ? 'Deadline removed' : 'Deadline set to ' . $due . ($time !== null ? " $time" : ''), $user_id);
        }
    }
    $assigned_now = !$old || intval($old['todo_assignee_id']) !== $assignee;
    if ($assigned_now && $assignee !== $user_id) {
        $by = $users[$user_id]['name'] ?? 'Someone';
        forkTodoNotify($assignee, "$by gave you a task", "$by gave you the task \"$title\"" . ($due !== null ? ", due $due" . ($time !== null ? " $time" : '') : '') . '.', $todo_id, 'info', $priority);
    }
    return [$todo_id, ''];
}

/** Mark a task done. The one who gave it is told when somebody else finished it. Returns false when it is not open. */
function forkTodoComplete($todo_id, $user_id) {
    global $mysqli;
    forkTodoEnsure();
    $task = forkTodoGet($todo_id);
    if (!$task || $task['todo_done_at'] !== null) {
        return false;
    }
    mysqli_query($mysqli, "UPDATE fork_todo_tasks SET todo_done_at = NOW(), todo_done_by = " . intval($user_id) . ", todo_updated_at = NOW() WHERE todo_id = " . intval($todo_id));
    forkTodoLogAdd($todo_id, 'Done', $user_id);
    $creator = intval($task['todo_created_by']);
    if ($creator > 0 && $creator !== intval($user_id)) {
        $by = forkTodoUsers()[intval($user_id)]['name'] ?? 'Someone';
        forkTodoNotify($creator, "$by finished a task", "$by finished the task \"" . $task['todo_title'] . "\" you gave.", $todo_id, 'success', 0);
    }
    return true;
}

/** Put a finished task back among the open ones. Returns false when it is not done. */
function forkTodoReopen($todo_id, $user_id) {
    global $mysqli;
    forkTodoEnsure();
    $task = forkTodoGet($todo_id);
    if (!$task || $task['todo_done_at'] === null) {
        return false;
    }
    mysqli_query($mysqli, "UPDATE fork_todo_tasks SET todo_done_at = NULL, todo_done_by = 0, todo_overdue_notified_on = NULL, todo_updated_at = NOW() WHERE todo_id = " . intval($todo_id));
    forkTodoLogAdd($todo_id, 'Reopened', $user_id);
    return true;
}

/** Delete a task with its reminders and history. */
function forkTodoDelete($todo_id) {
    global $mysqli;
    forkTodoEnsure();
    $todo_id = intval($todo_id);
    mysqli_query($mysqli, "DELETE FROM fork_todo_reminders WHERE reminder_todo_id = $todo_id");
    mysqli_query($mysqli, "DELETE FROM fork_todo_log WHERE log_todo_id = $todo_id");
    mysqli_query($mysqli, "DELETE FROM fork_todo_tasks WHERE todo_id = $todo_id");
}

/* ---------- telling people ---------- */

/**
 * Tell an agent about a task: an ITFlow notification and, if the settings say so, an email. $tone: info, success
 * or warning. $priority: 3 and 4 are named in the email.
 */
function forkTodoNotify($user_id, $subject, $text, $todo_id, $tone = 'info', $priority = 0) {
    global $mysqli, $config_base_url;
    $user_id = intval($user_id);
    $path = '/agent/todo_task.php?todo_id=' . intval($todo_id);
    mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'To-do', notification = '" . escapeSql(substr($text, 0, 900)) . "', notification_action = '" . escapeSql($path) . "',
        notification_client_id = 0, notification_user_id = $user_id, notification_entity_id = " . intval($todo_id));
    if (forkTodoSetting('email') !== '1') {
        return;
    }
    $agent = forkTodoUsers()[$user_id] ?? null;
    if (!$agent || trim((string) $agent['email']) === '') {
        return;
    }
    $task = forkTodoGet($todo_id);
    $summary = [];
    if ($task) {
        $summary = array_filter([
            'Task' => $task['todo_title'],
            'Priority' => forkTodoPriorities()[forkTodoPriorityClean($task['todo_priority'])],
            'Deadline' => $task['todo_due'] ? $task['todo_due'] . ($task['todo_due_time'] ? ' ' . substr($task['todo_due_time'], 0, 5) : '') : '',
            'Given by' => (string) $task['creator_name'],
        ], function ($value) { return $value !== ''; });
    }
    emailQueue([['email' => $agent['email'], 'name' => $agent['name'], 'footnote' => 'You get this from the to-do list in ITFlow.']], $subject, [
        'kind' => 'To-do', 'tone' => in_array($tone, ['info', 'success', 'warning'], true) ? $tone : 'info',
        'title' => $subject,
        'intro' => [$text],
        'summary' => $summary,
        'button' => ['label' => 'Open the task', 'url' => "https://$config_base_url$path"],
    ]);
}

/**
 * What cron/todo_reminders.php does each minute: send the reminders whose time has come, drop the ones missed by more than
 * an hour, and tell once a day about open tasks still late. Returns ['sent' => n, 'skipped' => n, 'late' => n].
 */
function forkTodoRemindersRun($now = null) {
    global $mysqli;
    forkTodoEnsure();
    $now = $now ?? time();
    $all_day = forkTodoSetting('all_day_time');
    $sent = $skipped = $late = 0;

    $result = mysqli_query($mysqli, "SELECT r.reminder_id, r.reminder_minutes, t.todo_id, t.todo_title, t.todo_due, t.todo_due_time, t.todo_assignee_id, t.todo_priority
        FROM fork_todo_reminders r JOIN fork_todo_tasks t ON t.todo_id = r.reminder_todo_id
        WHERE r.reminder_sent_at IS NULL AND t.todo_done_at IS NULL AND t.todo_due IS NOT NULL");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $moment = forkTodoDueMoment($row['todo_due'], substr((string) $row['todo_due_time'], 0, 5), $all_day);
        $action = forkTodoReminderAction($moment, $row['reminder_minutes'], $now);
        if ($action === 'wait') {
            continue;
        }
        mysqli_query($mysqli, "UPDATE fork_todo_reminders SET reminder_sent_at = NOW() WHERE reminder_id = " . intval($row['reminder_id']));
        if ($action === 'skip') {
            $skipped++;
            continue;
        }
        $minutes = intval($row['reminder_minutes']);
        $when = $minutes === 0 ? 'is due now' : 'is due in ' . strtolower(str_replace(' before', '', forkTodoReminderChoices()[$minutes] ?? ($minutes . ' minutes')));
        $deadline = $row['todo_due'] . ($row['todo_due_time'] ? ' ' . substr($row['todo_due_time'], 0, 5) : '');
        forkTodoNotify($row['todo_assignee_id'], 'Reminder: ' . $row['todo_title'], "The task \"" . $row['todo_title'] . "\" $when ($deadline).", $row['todo_id'],
            intval($row['todo_priority']) >= 3 || $minutes === 0 ? 'warning' : 'info', $row['todo_priority']);
        $sent++;
    }

    $result = mysqli_query($mysqli, "SELECT todo_id, todo_title, todo_due, todo_due_time, todo_assignee_id, todo_priority, todo_overdue_notified_on FROM fork_todo_tasks
        WHERE todo_done_at IS NULL AND todo_due IS NOT NULL AND (todo_overdue_repeat = 1 OR todo_priority = 4)");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        if (!forkTodoOverdueDue($row['todo_due'], $row['todo_overdue_notified_on'], $now)) {
            continue;
        }
        mysqli_query($mysqli, "UPDATE fork_todo_tasks SET todo_overdue_notified_on = '" . date('Y-m-d', $now) . "' WHERE todo_id = " . intval($row['todo_id']));
        forkTodoNotify($row['todo_assignee_id'], 'Late: ' . $row['todo_title'], "The task \"" . $row['todo_title'] . "\" was due " . $row['todo_due'] . " and is still open.", $row['todo_id'], 'warning', $row['todo_priority']);
        $late++;
    }
    return ['sent' => $sent, 'skipped' => $skipped, 'late' => $late];
}
