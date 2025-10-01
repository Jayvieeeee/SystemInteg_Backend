<?php

define('APP_ENV', 'development');
//if production set production

define('JWT_SECRET_KEY', 'supersecretkey123');

//database constant
define('DB_HOST', 'localhost');
define('DB_USER', 'u465107535_root');
define('DB_PASS', 'Dbpass#23');
define('DB_NAME', 'u465107535_sismoya_db');

//Error Reporting
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 1);
}
