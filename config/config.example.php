<?php
/**
 * AutoShop Example Configuration File
 *
 * Copy this file to config/config.php and replace placeholder values for your
 * local or production environment. Do not commit real credentials.
 */

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1); // Set to 0 in production.

// Timezone
date_default_timezone_set('America/Toronto');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('APP_NAME', 'AutoShop');
define('APP_VERSION', '1.0.0');

$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true);
if ($isLocalhost) {
    define('BASE_URL', 'http://localhost/autoshop');
    define('BASE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/autoshop');
} else {
    define('BASE_URL', 'https://example.com/');
    define('BASE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/');
}

// Session Settings
define('SESSION_NAME', 'AUTOSHOP_SESSION');
define('SESSION_LIFETIME', 28800); // 8 hours in seconds.

// Work Order Settings
define('WO_PREFIX', 'AUTO-');
define('WO_NUMBER_LENGTH', 6);

// File Upload Settings
define('MAX_FILE_SIZE', 5242880); // 5MB.
define('UPLOAD_PATH', BASE_PATH . '/uploads/');
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Pagination
define('RECORDS_PER_PAGE', 50);

// Date/Time Formats
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'm/d/Y');
define('DISPLAY_DATETIME_FORMAT', 'm/d/Y h:i A');

// Status Values
define('STATUS_NEW', 'NEW');
define('STATUS_PENDING', 'PENDING');
define('STATUS_BILLING', 'BILLING');
define('STATUS_COMPLETED', 'COMPLETED');
define('STATUS_CANCELLED', 'CANCELLED');
define('STATUS_ONHOLD', 'ON-HOLD');

// Priority Values
define('PRIORITY_NORMAL', 'NORMAL');
define('PRIORITY_HIGH', 'HIGH');
define('PRIORITY_URGENT', 'URGENT');

// Vehicle Status
define('VEHICLE_ACTIVE', 'A');
define('VEHICLE_INACTIVE', 'I');

// Employee Status
define('EMPLOYEE_ACTIVE', 'A');
define('EMPLOYEE_INACTIVE', 'I');

// User Roles
define('ROLE_ADMIN', 1);
define('ROLE_MECHANIC', 2);
define('ROLE_FRONTDESK', 3);

// System Customer
define('SYSTEM_CUSTOMER_ID', 1); // Must exist in the database.

// Email Settings
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'smtp_username');
define('SMTP_PASS', 'smtp_password');
define('SMTP_FROM_EMAIL', 'service@example.com');
define('SMTP_FROM_NAME', APP_NAME);

// Vehicle decode provider
define('DECODETHIS_API_KEY', getenv('DECODETHIS_API_KEY') ?: 'your_decode_api_key');
define('DECODETHIS_TIMEOUT_SECONDS', 15);

// Security
define('PASSWORD_MIN_LENGTH', 6);
define('BCRYPT_COST', 10);

// Debug Mode
define('DEBUG_MODE', true); // Set to false in production.
