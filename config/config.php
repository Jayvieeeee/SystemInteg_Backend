
<?php

define('APP_ENV', getenv('APP_ENV') ?: 'production');

define('JWT_SECRET_KEY', getenv('JWT_SECRET_KEY') ?: '');

// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'sismoya_db');
define('DB_PORT', (int) (getenv('DB_PORT') ?: 3306));

// Error Reporting
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
