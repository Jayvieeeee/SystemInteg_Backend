<?php

require_once __DIR__ . '/AppException.php';


class AuthException extends AppException
{
    public function __construct($message = "Unauthorized")
    {
        parent::__construct($message, 401);
    }
}
