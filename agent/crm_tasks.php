<?php

/*
 * CRM tasks - the open tasks of the deals still being worked, late first: what to do today.
 * Mine (and the unassigned ones) or everyone's. For agents with the Sales module; the handlers are
 * agent/post/crm.php.
 */

require_once "includes/inc_all.php";
require_once "includes/crm_common.php";

enforceUserPermission('module_sales');

$can_edit = lookupUserPermission('module_sales') >= 2;
$everyone = !empty($_GET['all']);
$tasks = forkCrmTasks($everyone ? 0 : $session_user_id);
$today = date('Y-m-d');
$groups = ['overdue' => ['Late', 'text-danger'], 'today' => ['Today', 'text-warning'], 'soon' => ['Next 7 days', 'text-info'], 'later' => ['Later', 'text-secondary']];
$by_state = [];
foreach ($tasks as $task) {
    $by_state[forkCrmDueState($task['activity_due'], $today)][] = $task;
}

?>

<?= crmTabs('tasks') ?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-tasks me-2"></i>Tasks to do</h3>
        <div class="card-tools">
            <a class="btn btn-<?= $everyone ? 'secondary' : 'primary' ?>" href="crm_tasks.php">Mine</a>
            <a class="btn btn-<?= $everyone ? 'primary' : 'secondary' ?>" href="crm_tasks.php?all=1">Everyone's</a>
        </div>
    </div>
    <?php if (!$tasks) { ?>
        <div class="card-body text-secondary">No open task. A task is made from a deal: add an activity with a due date.</div>
    <?php } else { ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="text-dark"><tr><th>Due</th><th>Task</th><th>Deal</th><th>For</th><th>Who</th><th class="text-center">Done</th></tr></thead>
                <tbody>
                <?php foreach ($groups as $state => [$label, $class]) {
                    if (empty($by_state[$state])) {
                        continue;
                    } ?>
                    <tr><td colspan="6" class="fw-semibold <?= $class ?>"><?= $label ?> (<?= count($by_state[$state]) ?>)</td></tr>
                    <?php foreach ($by_state[$state] as $task) { ?>
                        <tr>
                            <td><?= crmDueBadge($task['activity_due']) ?></td>
                            <td><span class="badge bg-secondary"><?= escapeHtml(forkCrmActivityTypes()[$task['activity_type']] ?? 'Task') ?></span> <?= escapeHtml(mb_strimwidth(preg_replace('/\s+/', ' ', $task['activity_text']), 0, 120, '...', 'UTF-8')) ?></td>
                            <td><a href="crm_deal.php?deal_id=<?= intval($task['activity_deal_id']) ?>"><?= escapeHtml($task['deal_title']) ?></a></td>
                            <td><?= escapeHtml((string) ($task['client_name'] ?: $task['deal_prospect'])) ?></td>
                            <td><?= escapeHtml((string) $task['user_name']) ?: '<span class="text-secondary">anyone</span>' ?></td>
                            <td class="text-center">
                                <?php if ($can_edit) { ?>
                                    <form action="post.php" method="post" class="d-inline">
                                        <?= crmCsrf() ?>
                                        <input type="hidden" name="activity_id" value="<?= intval($task['activity_id']) ?>">
                                        <button type="submit" name="done_crm_activity" value="1" class="btn btn-sm btn-outline-success" title="Mark done"><i class="fas fa-check"></i></button>
                                    </form>
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

<?php require_once "../includes/footer.php";
