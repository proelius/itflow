<?php

/*
 * Settings of this fork, kept in a table of their own.
 *
 * ITFlow keeps its settings as columns of `settings` and adds columns through
 * the numbered files of admin/database_updates/. The fork's settings do not go
 * there: a version number of ours could collide with, or be skipped by, a
 * later ITFlow one. They are rows of `fork_settings` (a key and a value),
 * a table ITFlow knows nothing about, which this file creates when first needed -
 * no database update, and `settings` stays as ITFlow made it.
 *
 * The keys are the names the settings had as columns (config_invoice_qr_iban...),
 * so the settings page, its search and the audit log are unchanged. A setting
 * saved as a column by an earlier version of the fork is taken over once, when
 * the table is made (forkSettingsImportLegacy()).
 */

/** The fork's settings and what each is until it is saved. */
function forkSettingsDefaults() {
    return [
        'config_invoice_qr_iban' => '',
        'config_invoice_late_fee_mode' => 'percent',
        'config_invoice_late_fee_fixed' => '0.00',
        'config_invoice_late_fee_from' => '2',
    ];
}

/** The table, made if it is not there yet - and the old columns' values taken into it. */
function forkSettingsEnsure() {
    global $mysqli;
    static $ready = false;
    if ($ready) {
        return;
    }
    $ready = true;
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `fork_settings` (
        `setting_key` varchar(64) NOT NULL,
        `setting_value` text DEFAULT NULL,
        `setting_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    forkSettingsImportLegacy();
}

/** Take the values an earlier version of the fork saved as columns of `settings` - only for a key with no row yet, and only while the column is there. */
function forkSettingsImportLegacy() {
    global $mysqli;
    foreach (array_keys(forkSettingsDefaults()) as $key) {
        if (!settingsColumnExists($mysqli, $key)) {
            continue;
        }
        $have = mysqli_query($mysqli, "SELECT 1 FROM fork_settings WHERE setting_key = '" . escapeSql($key) . "'");
        if ($have && mysqli_num_rows($have) > 0) {
            continue;
        }
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT `$key` AS value FROM settings WHERE company_id = 1"));
        if ($row && $row['value'] !== null) {
            mysqli_query($mysqli, "INSERT INTO fork_settings (setting_key, setting_value) VALUES ('" . escapeSql($key) . "', '" . escapeSql((string) $row['value']) . "')");
        }
    }
}

/** The fork's settings - every key, saved or default - as text. $fresh reads them again. */
function forkSettingsGet($fresh = false) {
    global $mysqli;
    static $values = null;
    if ($values !== null && !$fresh) {
        return $values;
    }
    $values = forkSettingsDefaults();
    forkSettingsEnsure();
    $result = mysqli_query($mysqli, "SELECT setting_key, setting_value FROM fork_settings");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        if (array_key_exists($row['setting_key'], $values) && $row['setting_value'] !== null) {
            $values[$row['setting_key']] = (string) $row['setting_value'];
        }
    }
    return $values;
}

/** One setting. */
function forkSetting($key) {
    return forkSettingsGet()[$key] ?? null;
}

/** Which of $after differ from $before, by key - pure: only the fork's keys, values compared as settings are (30 and '30.00' are the same). */
function forkSettingsChanged(array $before, array $after) {
    $changed = [];
    $known = forkSettingsDefaults();
    foreach ($after as $key => $value) {
        if (!array_key_exists($key, $known)) {
            continue;
        }
        if (!array_key_exists($key, $before) || !settingsSame($before[$key], $value)) {
            $changed[$key] = $value;
        }
    }
    return $changed;
}

/** Save what changed - the changed ones, by key. */
function forkSettingsSave(array $before, array $after) {
    global $mysqli;
    forkSettingsEnsure();
    $changed = forkSettingsChanged($before, $after);
    foreach ($changed as $key => $value) {
        mysqli_query($mysqli, "INSERT INTO fork_settings (setting_key, setting_value) VALUES ('" . escapeSql($key) . "', '" . escapeSql(is_float($value) ? sprintf('%.2F', $value) : (string) $value) . "')
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    }
    forkSettingsGet(true);
    return $changed;
}
