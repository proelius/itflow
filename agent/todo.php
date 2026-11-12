<?php

/*
 * To-do list (functions/fork_todo.php): the tasks given to me, the ones I gave, the finished ones, and for an
 * administrator everybody's. A task can be added here in one go; the handlers are agent/post/todo.php.
 */

require_once "includes/inc_all.php";
require_once "includes/crm_common.php";
require_once "includes/todo_common.php";

$view = in_array($_GET['view'] ?? '', ['mine', 'given', 'done', 'all'], true) ? $_GET['view'] : 'mine';
if ($view === 'all' && empty($session_is_admin)) {
    $view = 'mine';
}
$priority = forkTodoPriorityClean($_GET['priority'] ?? 0) === intval($_GET['priority'] ?? 0) ? intval($_GET['priority'] ?? 0) : 0;
$search = trim((string) ($_GET['q'] ?? ''));
$tasks = forkTodoList($session_user_id, $view, $priority, $search);
$users = forkTodoUsers();
$now = time();
$groups = ['overdue' => ['Late', 'text-danger'], 'today' => ['Today', 'text-warning'], 'soon' => ['Next 7 days', 'text-info'], 'later' => ['Later', 'text-secondary'], 'none' => ['No deadline', 'text-secondary']];
$by_state = [];
foreach ($tasks as $task) {
    $by_state[$view === 'done' ? 'none' : forkTodoDueState($task['todo_due'], substr((string) $task['todo_due_time'], 0, 5), $now)][] = $task;
}
if ($view === 'done') {
    $groups = ['none' => ['Finished', 'text-secondary']];
}

?>

<?= todoTabs($view, $priority, $search) ?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-clipboard-check me-2"></i>To-do</h3>
        <div class="card-tools">
            <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#todoAdd"><i class="fas fa-plus me-2"></i>New task</button>
        </div>
    </div>
    <div class="collapse border-bottom" id="todoAdd">
        <form action="post.php" method="post" class="card-body">
            <?= todoCsrf() ?>
            <?= todoFormFields(null, $users) ?>
            <button type="submit" name="add_todo" value="1" class="btn btn-primary"><i class="fas fa-check me-2"></i>Add the task</button>
        </form>
    </div>
    <div class="card-body border-bottom py-2">
        <form method="get" class="row g-2 align-items-center">
            <input type="hidden" name="view" value="<?= escapeHtml($view) ?>">
            <div class="col-auto"><input type="text" class="form-control" name="q" placeholder="Search" value="<?= escapeHtml($search) ?>"></div>
            <div class="col-auto"><select class="form-select" name="priority"><option value="0">All priorities</option><?= crmOptions(forkTodoPriorities(), $priority) ?></select></div>
            <div class="col-auto"><button type="submit" class="btn btn-secondary"><i class="fas fa-filter me-2"></i>Filter</button></div>
        </form>
    </div>
    <?php if (!$tasks) { ?>
        <div class="card-body text-secondary"><?= $view === 'done' ? 'Nothing finished yet.' : 'Nothing to do here. Add a task with the button above.' ?></div>
    <?php } else { ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="text-dark"><tr><th style="width: 3.5em"></th><th>Task</th><th>Priority</th><th>Deadline</th><th><?= $view === 'given' ? 'Given to' : 'Who' ?></th></tr></thead>
                <tbody>
                <?php foreach ($groups as $state => [$label, $class]) {
                    if (empty($by_state[$state])) {
                        continue;
                    } ?>
                    <tr><td colspan="5" class="fw-semibold <?= $class ?>"><?= $label ?> (<?= count($by_state[$state]) ?>)</td></tr>
                    <?php foreach ($by_state[$state] as $task) {
                        $done = $task['todo_done_at'] !== null; ?>
                        <tr>
                            <td class="text-center">
                                <form action="post.php" method="post" class="d-inline">
                                    <?= todoCsrf() ?>
                                    <input type="hidden" name="todo_id" value="<?= intval($task['todo_id']) ?>">
                                    <?php if ($done) { ?>
                                        <button type="submit" name="reopen_todo" value="1" class="btn btn-sm btn-outline-secondary" title="Reopen"><i class="fas fa-undo"></i></button>
                                    <?php } else { ?>
                                        <button type="submit" name="complete_todo" value="1" class="btn btn-sm btn-outline-success" title="Mark done"><i class="fas fa-check"></i></button>
                                    <?php } ?>
                                </form>
                            </td>
                            <td>
                                <a href="todo_task.php?todo_id=<?= intval($task['todo_id']) ?>" class="<?= $done ? 'text-decoration-line-through text-secondary' : 'fw-semibold' ?>"><?= escapeHtml($task['todo_title']) ?></a>
                                <?php if ((string) $task['todo_description'] !== '') { ?>
                                    <div class="text-secondary small"><?= escapeHtml(mb_strimwidth(preg_replace('/\s+/u', ' ', $task['todo_description']), 0, 110, '…')) ?></div>
                                <?php } ?>
                            </td>
                            <td><?= todoPriorityBadge($task['todo_priority']) ?></td>
                            <td class="text-nowrap"><?= todoDueBadge($task) ?></td>
                            <td>
                                <?= escapeHtml((string) $task['assignee_name']) ?>
                                <?php if (intval($task['todo_created_by']) !== intval($task['todo_assignee_id']) && $view !== 'given') { ?>
                                    <div class="text-secondary small">from <?= escapeHtml((string) $task['creator_name']) ?></div>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>

<?php if (!empty($session_is_admin)) { ?>
    <div class="card mt-3">
        <div class="card-header py-2"><a class="text-decoration-none" data-bs-toggle="collapse" href="#todoSettings"><i class="fas fa-fw fa-sliders-h me-2"></i>To-do settings</a></div>
        <div class="collapse" id="todoSettings">
            <form action="post.php" method="post" class="card-body">
                <?= todoCsrf() ?>
                <div class="mb-2"><label class="form-label">A deadline with no time counts from</label><input type="time" class="form-control" style="width: 140px" name="all_day_time" value="<?= escapeHtml(forkTodoSetting('all_day_time')) ?>"></div>
                <div class="mb-2"><label class="form-label">Reminders offered on a new task</label><div>
                    <?php $default = forkTodoReminderClean(forkTodoSetting('default_reminders'));
                    foreach (forkTodoReminderChoices() as $minutes => $label) { ?>
                        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="default_reminders[]" id="def<?= $minutes ?>" value="<?= $minutes ?>"<?= in_array($minutes, $default, true) ? ' checked' : '' ?>><label class="form-check-label" for="def<?= $minutes ?>"><?= escapeHtml($label) ?></label></div>
                    <?php } ?>
                </div></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="overdue_repeat" id="setRepeat" value="1"<?= forkTodoSetting('overdue_repeat') === '1' ? ' checked' : '' ?>><label class="form-check-label" for="setRepeat">New tasks remind every day once they are late</label></div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="email" id="setEmail" value="1"<?= forkTodoSetting('email') === '1' ? ' checked' : '' ?>><label class="form-check-label" for="setEmail">Also send the reminders and the assignments by email</label></div>
                <button type="submit" name="save_todo_settings" value="1" class="btn btn-primary"><i class="fas fa-check me-2"></i>Save the settings</button>
            </form>
        </div>
    </div>
<?php } ?>

<?php require_once "../includes/footer.php";
