<?php
/*
 * Client Portal - streams a file for download or inline viewing without
 * exposing the uploads directory on-disk location.
 */

require_once '../config.php';
require_once '../includes/load_global_settings.php';
require_once '../functions.php';
require_once 'includes/check_login.php';
require_once 'functions.php';
i18n_set_language(client_language());

// Require a file ID
if (!isset($_GET['file_id'])) {
    http_response_code(400);
    exit(t('File ID required'));
}

$file_id = intval($_GET['file_id']);

// Disposition: download by default, inline only when explicitly requested
$disposition = "attachment";
if (isset($_GET['action']) && $_GET['action'] == "view") {
    $disposition = "inline";
}

// Look up the file
$sql = mysqli_query($mysqli, "SELECT file_id, file_name, file_reference_name, file_client_id FROM files WHERE file_id = $file_id AND file_archived_at IS NULL LIMIT 1");

if (mysqli_num_rows($sql) !== 1) {
    http_response_code(404);
    exit(t('File not found'));
}

$row = mysqli_fetch_array($sql);
$file_client_id = intval($row['file_client_id']);

// Verify the contact can access this client
if ($file_client_id !== $session_client_id) {
    http_response_code(404);
    exit(t('File not found'));
}

// Build the on-disk path
$uploads_base = realpath(__DIR__ . "/../uploads");
$file_path = realpath(__DIR__ . "/../uploads/files/$file_id/" . $row['file_reference_name']);

// Path traversal guard
if ($file_path === false || $uploads_base === false || strpos($file_path, $uploads_base) !== 0) {
    http_response_code(404);
    exit(t('File not found'));
}

// Detect MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$file_mime_type = finfo_file($finfo, $file_path);
finfo_close($finfo);

// MIME types safe to render inline
$inline_allowed_mime_types = getInlineViewableMimeTypes();

if ($disposition == "inline" && !in_array($file_mime_type, $inline_allowed_mime_types, true)) {
    $disposition = "attachment";
}

// Strip characters that would break the Content-Disposition header
$safe_file_name = str_replace(array('"', "\r", "\n"), "", basename($row['file_name']));

// Caching
$file_mtime = filemtime($file_path);
$etag = '"' . md5($file_mtime . filesize($file_path) . $file_id) . '"';

header("Cache-Control: private, max-age=3600");
header("ETag: $etag");
header("Last-Modified: " . gmdate("D, d M Y H:i:s", $file_mtime) . " GMT");

// Conditional GET
if ((isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) == $etag)
    || (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $file_mtime)
) {
    http_response_code(304);
    exit;
}

// Audit log
if (!portalViewingAs()) {
    logAudit("File", "Download", "Client contact $session_contact_name viewed file " . escapeSql($row['file_name']) . " via portal", $session_client_id);
}

// Send the file
header("Content-Type: $file_mime_type");
header("Content-Disposition: $disposition; filename=\"$safe_file_name\"");
header("Content-Length: " . filesize($file_path));
header("X-Content-Type-Options: nosniff");

while (ob_get_level()) {
    ob_end_clean();
}

readfile($file_path);
exit;
