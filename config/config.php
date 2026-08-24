<?php

define('APP_ENV', 'development');
//if production set production

define('JWT_SECRET_KEY', 'supersecretkey123');

//database constant
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sismoya_db');

//Error Reporting
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 1);
}
