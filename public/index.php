<?php
/**
 * Index Page - Redirects to appropriate module
 */

require_once '../includes/bootstrap.php';

if (Session::isLoggedIn()) {
    if (Session::isAdmin()) {
        redirect(BASE_URL . '/modules/admin/work_orders.php');
    } else {
        redirect(BASE_URL . '/modules/mechanic/work_orders.php');
    }
} else {
    redirect(BASE_URL . '/public/login.php');
}
