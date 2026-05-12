<?php
// Fallback redirect to public entry point when .htaccess rewrite is not active.
header('Location: /autoshop/public/', true, 302);
exit;
