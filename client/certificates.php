<?php
/*
 * Client Portal
 * Web certificates - the SSL/TLS certificates for domains owned by this client.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

redirect('domains.php?tab=certificates');
