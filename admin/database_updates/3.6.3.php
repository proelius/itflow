<?php

/*
 * ITFlow - Database update to version 3.6.3 (from 3.6.2)
 * Included by admin/database_updates.php - do not access directly
 *
 * settings.config_invoice_qr_iban - the QR-IBAN the Swiss QR-bill on an invoice's PDF
 * is paid to (functions/qr_bill.php). Empty, no QR-bill is drawn - nothing changes for
 * an install that does not set one.
 *
 * Re-runnable: the column is made only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'config_invoice_qr_iban' LIMIT 1");
    if (!$itflow_column_exists || mysqli_num_rows($itflow_column_exists) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_invoice_qr_iban` varchar(34) NOT NULL DEFAULT '' AFTER `config_invoice_show_tax_id`");
    }

    unset($itflow_column_exists);
