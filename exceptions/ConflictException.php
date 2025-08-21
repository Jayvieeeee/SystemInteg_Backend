<?php
require_once "AppException.php";

class ConflictException extends AppException {
    public function __construct($message = "Conflict occurred") {
        parent::__construct($message, 409);
    }
}
?>