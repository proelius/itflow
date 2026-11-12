<?php

/*
 * One to-do task (functions/fork_todo.php): change it, finish or reopen it, delete it, and see what happened to it.
 * The handlers are agent/post/todo.php.
 */

require_once "includes/inc_all.php";
require_once "includes/crm_common.php";
require_once "includes/todo_common.php";

$task = forkTodoGet($_GET['todo_id'] ?? 0);
if (!$task || !forkTodoCanView($task, $session_user_id, !empty($session_is_admin))) {
    flashAlert("That task is gone or is not yours.", 'error');
    redirect('todo.php');
}
$done = $task['todo_done_at'] !== null;
$users = forkTodoUsers();
$log = forkTodoLog($task['todo_id']);

?>

<ul class="nav nav-pills mb-3"><li class="nav-item"><a class="nav-link" href="todo.php"><i class="fas fa-fw fa-arrow-left me-2"></i>To-do</a></li></ul>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-clipboard-check me-2"></i><?= escapeHtml($task['todo_title']) ?> <?= todoPriorityBadge($task['todo_priority']) ?> <?= $done ? '<span class="badge bg-success">Done</span>' : todoDueBadge($task) ?></h3>
        <div class="card-tools">
            <form action="post.php" method="post" class="d-inline">
                <?= todoCsrf() ?>
                <input type="hidden" name="todo_id" value="<?= intval($task['todo_id']) ?>">
                <?php if ($done) { ?>
                    <button type="submit" name="reopen_todo" value="1" class="btn btn-secondary"><i class="fas fa-undo me-2"></i>Reopen</button>
                <?php } else { ?>
                    <button type="submit" name="complete_todo" value="1" class="btn btn-success"><i class="fas fa-check me-2"></i>Mark done</button>
                <?php } ?>
                <?php if (forkTodoCanDelete($task, $session_user_id, !empty($session_is_admin))) { ?>
                    <button type="submit" name="delete_todo" value="1" class="btn btn-danger" onclick="return confirm('Delete this task?')"><i class="fas fa-trash me-2"></i>Delete</button>
                <?php } ?>
            </form>
        </div>
    </div>
    <form action="post.php" method="post" class="card-body">
        <?= todoCsrf() ?>
        <input type="hidden" name="todo_id" value="<?= intval($task['todo_id']) ?>">
        <?= todoFormFields($task, $users) ?>
        <p class="text-secondary small mb-3">Given by <?= escapeHtml((string) $task['creator_name']) ?> on <?= escapeHtml(substr($task['todo_created_at'], 0, 10)) ?><?= $done ? ' - done ' . escapeHtml(substr($task['todo_done_at'], 0, 16)) : '' ?>.</p>
        <button type="submit" name="edit_todo" value="1" class="btn btn-primary"><i class="fas fa-check me-2"></i>Save</button>
    </form>
    <?php if ($reminders = forkTodoReminders($task['todo_id'])) { ?>
        <div class="card-body border-top small">
            <h5 class="mb-2">Reminders</h5>
            <?php foreach ($reminders as $minutes => $sent_at) { ?>
                <div><?= escapeHtml(forkTodoReminderChoices()[$minutes] ?? ($minutes . ' min')) ?> - <?= $sent_at ? 'sent ' . escapeHtml(substr($sent_at, 0, 16)) : '<span class="text-secondary">waiting</span>' ?></div>
            <?php } ?>
        </div>
    <?php } ?>
    <div class="card-body border-top">
        <h5 class="mb-2">History</h5>
        <?php foreach ($log as $line) { ?>
            <div class="small"><span class="text-secondary"><?= escapeHtml(substr($line['log_at'], 0, 16)) ?></span> <?= escapeHtml($line['log_text']) ?> <span class="text-secondary">- <?= escapeHtml((string) $line['user_name']) ?></span></div>
        <?php } ?>
    </div>
</div>

<?php require_once "../includes/footer.php";
