<?php
/**
 * Bootstrap File
 * Initialize application and load dependencies
 */

// Load configuration
require_once __DIR__ . '/../config/config.php';

// Load core classes
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/functions.php';

// Load models
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Customer.php';
require_once __DIR__ . '/models/Vehicle.php';
require_once __DIR__ . '/models/WorkOrder.php';
require_once __DIR__ . '/models/WorkOrderPhoto.php';
require_once __DIR__ . '/models/VehicleInspection.php';
require_once __DIR__ . '/models/Employee.php';
require_once __DIR__ . '/models/LetterTemplate.php';
require_once __DIR__ . '/models/CustomerLetter.php';

// Start session
Session::start();
