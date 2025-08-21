<?php

require_once 'AppException.php';

class NotfoundException extends AppException {
    public function __construct($message = "Resource not found"){
        parent::__construct($message, 404);
    }
}


?>