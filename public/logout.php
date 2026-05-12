<?php
/**
 * Logout Page
 */

require_once '../includes/bootstrap.php';

Session::destroy();
redirect(BASE_URL . '/public/login.php');
