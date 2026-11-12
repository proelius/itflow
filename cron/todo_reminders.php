<?php
// Set working directory to the directory this cron script lives at.
chdir(dirname(__FILE__));

// Ensure we're running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Prevent overlapping runs of this script
$cron_lock_script = __FILE__;
require_once "includes/cron_lock.php";

require_once "../config.php";
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";

/*
 * ITFlow - To-do reminders
 *
 * Intended to run every minute:
 *   * * * * * php /var/www/itflow/cron/todo_reminders.php
 *
 * Sends the reminders of the to-do list (functions/fork_todo.php) whose time has come - some time before the
 * deadline of a task, or at it - to the agent who has the task, and once a day tells about open tasks still late.
 * A reminder missed by more than an hour (cron was off) is dropped rather than sent late. With no task that has a
 * deadline this does nothing.
 */

// Read the master switch here rather than trusting a global (see the cron rules in CONTRIBUTING.md).
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron FROM settings WHERE company_id = 1"));

$config_enable_cron = intval($row['config_enable_cron']);

// Check cron is enabled
if ($config_enable_cron == 0) {
    logApp("Cron-Todo-Reminders", "error", "Cron To-do reminders unable to run - cron not enabled in admin settings.");
    cronJobStop("Cron: is not enabled -- Quitting..");
}

if (function_exists('forkTodoRemindersRun')) {
    $done = forkTodoRemindersRun();
    if ($done['sent'] || $done['skipped'] || $done['late']) {
        logApp("Cron-Todo-Reminders", "info", "To-do reminders: " . $done['sent'] . " sent, " . $done['skipped'] . " dropped as too late, " . $done['late'] . " late tasks told");
    }
}
