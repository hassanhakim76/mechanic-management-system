<?php
/**
 * Backward-compatible route for the old inspection template page.
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

if (!Session::isAdmin()) {
    redirect(BASE_URL . '/modules/mechanic/work_orders.php');
}

redirect('inspection_settings.php');
