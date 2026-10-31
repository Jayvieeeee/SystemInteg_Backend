<?php

require_once __DIR__ . '/config.php';

$conn = mysqli_init();

mysqli_ssl_set($conn, NULL, NULL, __DIR__ . '/ca.pem', NULL, NULL);

if (!mysqli_real_connect(
    $conn,
    DB_HOST,
    DB_USER,
    DB_PASS,
    DB_NAME,
    DB_PORT,
    NULL,
    MYSQLI_CLIENT_SSL
)) {
    error_log('Database connection failed: ' . mysqli_connect_error());
    die('Database connection failed.');
}
