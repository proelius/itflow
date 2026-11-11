<?php

/*
 * ITFlow - Database update to version 3.6.4 (from 3.6.3)
 * Included by admin/database_updates.php - do not access directly
 *
 * The late fee as a fixed amount for each reminder (functions/late_fee_fixed.php):
 *   settings.config_invoice_late_fee_mode   'percent' (as always) or 'fixed'
 *   settings.config_invoice_late_fee_fixed  the amount of each fee, in the company's currency
 *   settings.config_invoice_late_fee_from   the first reminder after the due date that adds a fee
 * An install that does not choose 'fixed' keeps the percent late fee, unchanged.
 *
 * Re-runnable: each column is made only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_columns = [
        'config_invoice_late_fee_mode' => "varchar(10) NOT NULL DEFAULT 'percent'",
        'config_invoice_late_fee_fixed' => "decimal(10,2) NOT NULL DEFAULT 0.00",
        'config_invoice_late_fee_from' => "tinyint(3) unsigned NOT NULL DEFAULT 2",
    ];
    $itflow_after = 'config_invoice_late_fee_percent';
    foreach ($itflow_columns as $itflow_column => $itflow_definition) {
        $itflow_column_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = '$itflow_column' LIMIT 1");
        if (!$itflow_column_exists || mysqli_num_rows($itflow_column_exists) === 0) {
            mysqli_query($mysqli, "ALTER TABLE `settings` ADD `$itflow_column` $itflow_definition AFTER `$itflow_after`");
        }
        $itflow_after = $itflow_column;
    }

    unset($itflow_columns, $itflow_after, $itflow_column, $itflow_definition, $itflow_column_exists);
