<?php
/*
 * Client Portal
 * New ticket form
 */

require_once 'includes/inc_all.php';

// Allow clients to select a related asset when raising a ticket
$sql_assets = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type FROM assets WHERE asset_contact_id = $session_contact_id AND asset_client_id = $session_client_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");

?>

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item">
            <a href="index.php"><?= t('Home') ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="tickets.php"><?= t('Tickets') ?></a>
        </li>
        <li class="breadcrumb-item active"><?= t('New Ticket') ?></li>
    </ol>

    <h3><?= t('Raise a new ticket') ?></h3>

    <div class="col-md-8">
        <form action="post.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="mb-3">
                <label><?= t('Subject') ?> <strong class="text-danger">*</strong></label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                    <input type="text" class="form-control" name="subject" placeholder="<?= t('Subject') ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col">
                    <div class="mb-3">
                        <label><?= t('Priority') ?> <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                            <select class="form-select select2" name="priority" required>
                                <option<?= t('Low') === 'Low' ? '' : ' value="Low"' ?>><?= t('Low') ?></option>
                                <option<?= t('Medium') === 'Medium' ? '' : ' value="Medium"' ?> selected><?= t('Medium') ?></option>
                                <option<?= t('High') === 'High' ? '' : ' value="High"' ?>><?= t('High') ?></option>
                                <option<?= t('Urgent') === 'Urgent' ? '' : ' value="Urgent"' ?>><?= t('Urgent') ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="col">
                    <div class="mb-3">
                    <label><?= t('Category') ?></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                        <select class="form-select select2" name="category">
                            <option value="0"><?= t('- No Category -') ?></option>
                            <?php
                            $sql_categories = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Ticket' AND category_archived_at IS NULL");
                            while ($row = mysqli_fetch_assoc($sql_categories)) {
                                $category_id = intval($row['category_id']);
                                $category_name = escapeHtml($row['category_name']);

                                ?>
                                <option value="<?= $category_id ?>"><?= $category_name ?></option>
                            <?php } ?>

                        </select>
                    </div>
                </div>
                </div>
            </div>

            <?php if (mysqli_num_rows($sql_assets) > 0) { ?>
                <div class="mb-3">
                    <label>Asset</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                        <select class="form-select select2" name="asset">
                            <option value="0">- None -</option>
                            <?php

                            while ($row = mysqli_fetch_assoc($sql_assets)) {
                                $asset_id = intval($row['asset_id']);
                                $asset_name = escapeSql($row['asset_name']);
                                $asset_type = escapeSql($row['asset_type']);
                                ?>
                                <option value="<?= $asset_id ?>"><?= "$asset_name ($asset_type)" ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>
            <?php } ?>


            <div class="mb-3">
                <label><?= t('Details') ?> <strong class="text-danger">*</strong></label>
                <textarea class="form-control tinymce" name="details"></textarea>
            </div>

            <button class="btn btn-primary" name="add_ticket"><?= t('Raise ticket') ?></button>

        </form>
    </div>

<?php
require_once 'includes/footer.php';
