<?php
// Fallback redirect to public entry point when .htaccess rewrite is not active.
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
header('Location: ' . $base . '/public/', true, 302);
exit;
