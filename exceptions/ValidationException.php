<?php

require_once __DIR__ . '/AppException.php';


class ValidationException extends AppException
{
    public function __construct($message = "Validation failed")
    {
        parent::__construct($message, 422);
    }
}
