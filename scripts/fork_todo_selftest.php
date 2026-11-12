#!/usr/bin/env php
<?php
/**
 * Fork to-do self-test - functions/fork_todo.php
 * Cleaning of input, deadlines, reminder timing, rights, settings defaults, and that the handler, pages, menu and cron
 * job are wired. The queries are not run here (no database): they are checked by use on the test server.
 *
 * CLI only - protected from web access
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

date_default_timezone_set('Europe/Zurich');
$itflow_dir = __DIR__ . '/..';
require_once $itflow_dir . '/functions/fork_todo.php';

$passed = 0;
$failed = 0;

function todo_test($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "  ✓ $name\n";
        $passed++;
    } else {
        echo "  ✗ $name\n";
        $failed++;
    }
}

echo "Priorities\n";
todo_test('four priorities, Urgent is the highest', array_keys(forkTodoPriorities()) === [1, 2, 3, 4] && forkTodoPriorities()[4] === 'Urgent');
todo_test('a known priority stays', forkTodoPriorityClean('3') === 3 && forkTodoPriorityClean(1) === 1);
todo_test('an unknown one is Normal', forkTodoPriorityClean(9) === 2 && forkTodoPriorityClean('x') === 2 && forkTodoPriorityClean(0) === 2);

echo "\nText\n";
todo_test('title on one line, trimmed', forkTodoTitleClean("  Call \n  Bob  ") === 'Call Bob');
todo_test('title cut at 200', mb_strlen(forkTodoTitleClean(str_repeat('é', 300))) === 200);
todo_test('text keeps its lines, loses \\r', forkTodoTextClean("a\r\nb ") === "a\nb");
todo_test('text cut at the limit', mb_strlen(forkTodoTextClean(str_repeat('a', 6000))) === 5000 && mb_strlen(forkTodoTextClean('abcdef', 3)) === 3);

echo "\nDates and times\n";
todo_test('a real date', forkTodoDateClean('2026-11-12') === '2026-11-12');
todo_test('not a date', forkTodoDateClean('') === null && forkTodoDateClean('2026-02-30') === null && forkTodoDateClean('12.11.2026') === null);
todo_test('a time', forkTodoTimeClean('9:05') === '09:05' && forkTodoTimeClean('17:30') === '17:30' && forkTodoTimeClean('17:30:00') === '17:30');
todo_test('not a time', forkTodoTimeClean('') === null && forkTodoTimeClean('24:00') === null && forkTodoTimeClean('12:60') === null && forkTodoTimeClean('abc') === null);
todo_test('moment with a time', forkTodoDueMoment('2026-11-12', '14:30') === strtotime('2026-11-12 14:30:00'));
todo_test('moment with no time uses the all-day time', forkTodoDueMoment('2026-11-12', '') === strtotime('2026-11-12 08:00:00') && forkTodoDueMoment('2026-11-12', '', '09:15') === strtotime('2026-11-12 09:15:00'));
todo_test('moment with a bad all-day time falls back to 08:00', forkTodoDueMoment('2026-11-12', '', 'zz') === strtotime('2026-11-12 08:00:00'));
todo_test('no date, no moment', forkTodoDueMoment('', '10:00') === null);

echo "\nReminders\n";
$choices = forkTodoReminderChoices();
todo_test('eight choices, 0 is the deadline itself', count($choices) === 8 && isset($choices[0]) && isset($choices[10080]));
todo_test('clean from an array: valid only, longest first, no doubles', forkTodoReminderClean(['60', '1440', '60', '7', 'x', '']) === [1440, 60]);
todo_test('clean from text', forkTodoReminderClean('1440,60') === [1440, 60] && forkTodoReminderClean('') === []);
todo_test('zero is kept', forkTodoReminderClean(['0', '15']) === [15, 0]);
$moment = strtotime('2026-11-12 14:00:00');
todo_test('too early: wait', forkTodoReminderAction($moment, 60, $moment - 3601) === 'wait');
todo_test('at the minute: fire', forkTodoReminderAction($moment, 60, $moment - 3600) === 'fire');
todo_test('59 minutes after the time: fire', forkTodoReminderAction($moment, 60, $moment - 3600 + 3599) === 'fire');
todo_test('more than an hour after: skip', forkTodoReminderAction($moment, 60, $moment - 3600 + 3601) === 'skip');
todo_test('0 minutes fires at the deadline', forkTodoReminderAction($moment, 0, $moment) === 'fire' && forkTodoReminderAction($moment, 0, $moment - 1) === 'wait');
todo_test('no moment: wait', forkTodoReminderAction(null, 60, $moment) === 'wait');

echo "\nDeadline state\n";
$now = strtotime('2026-11-12 10:00:00');
todo_test('no date', forkTodoDueState('', '', $now) === 'none');
todo_test('yesterday is overdue', forkTodoDueState('2026-11-11', '', $now) === 'overdue');
todo_test('today with no time is today', forkTodoDueState('2026-11-12', '', $now) === 'today');
todo_test('today, time gone, is overdue', forkTodoDueState('2026-11-12', '09:00', $now) === 'overdue');
todo_test('today, time to come, is today', forkTodoDueState('2026-11-12', '18:00', $now) === 'today');
todo_test('in 7 days is soon, in 8 is later', forkTodoDueState('2026-11-19', '', $now) === 'soon' && forkTodoDueState('2026-11-20', '', $now) === 'later');
todo_test('the daily late reminder starts the day after', forkTodoOverdueDue('2026-11-12', null, $now) === false && forkTodoOverdueDue('2026-11-11', null, $now) === true);
todo_test('and is sent once a day', forkTodoOverdueDue('2026-11-11', '2026-11-12', $now) === false && forkTodoOverdueDue('2026-11-11', '2026-11-11', $now) === true);
todo_test('no date, no late reminder', forkTodoOverdueDue('', null, $now) === false);

echo "\nRights\n";
$task = ['todo_assignee_id' => 5, 'todo_created_by' => 7];
todo_test('the assignee sees and changes, cannot delete', forkTodoCanView($task, 5, false) && forkTodoCanEdit($task, 5, false) && !forkTodoCanDelete($task, 5, false));
todo_test('the creator sees, changes and deletes', forkTodoCanView($task, 7, false) && forkTodoCanEdit($task, 7, false) && forkTodoCanDelete($task, 7, false));
todo_test('another agent sees nothing', !forkTodoCanView($task, 9, false) && !forkTodoCanEdit($task, 9, false) && !forkTodoCanDelete($task, 9, false));
todo_test('an administrator does everything', forkTodoCanView($task, 9, true) && forkTodoCanEdit($task, 9, true) && forkTodoCanDelete($task, 9, true));
todo_test('ids given as text work', forkTodoCanView(['todo_assignee_id' => '5', 'todo_created_by' => '7'], '5', false));

echo "\nSettings\n";
$defaults = forkTodoSettingDefaults();
todo_test('defaults', $defaults['all_day_time'] === '08:00' && $defaults['default_reminders'] === '1440,60' && $defaults['overdue_repeat'] === '1' && $defaults['email'] === '1');
todo_test('the default reminders are valid choices', forkTodoReminderClean($defaults['default_reminders']) === [1440, 60]);

echo "\nWiring\n";
$handler = file_get_contents($itflow_dir . '/agent/post/todo.php');
foreach (['add_todo', 'edit_todo', 'complete_todo', 'reopen_todo', 'delete_todo', 'save_todo_settings'] as $action) {
    todo_test("the handler has $action", strpos($handler, "isset(\$_POST['$action'])") !== false);
}
todo_test('every handler action checks the CSRF token', substr_count($handler, 'validateCSRFToken()') >= 6);
todo_test('the settings are for administrators', preg_match('/save_todo_settings.*?permissionRefuse/s', $handler) === 1);
$jobs = file_get_contents($itflow_dir . '/includes/cron_jobs.php');
todo_test('the cron job is registered and its script exists', strpos($jobs, "'todo_reminders'") !== false && is_file($itflow_dir . '/cron/todo_reminders.php'));
todo_test('functions.php loads the module', strpos(file_get_contents($itflow_dir . '/functions.php'), 'functions/fork_todo.php') !== false);
todo_test('the menu links the page with the count', strpos(file_get_contents($itflow_dir . '/agent/includes/side_nav.php'), '/agent/todo.php') !== false && strpos(file_get_contents($itflow_dir . '/agent/includes/get_side_nav_counts.php'), 'forkTodoDueCount') !== false);
todo_test('the pages exist', is_file($itflow_dir . '/agent/todo.php') && is_file($itflow_dir . '/agent/todo_task.php'));
todo_test('the todo tables are not in ITFlow\'s own database updates', strpos(file_get_contents($itflow_dir . '/db.sql'), 'fork_todo') === false);

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
