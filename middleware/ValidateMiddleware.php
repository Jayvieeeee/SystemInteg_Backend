<?php 
require_once "BaseMiddleware.php";
require_once __DIR__ . "/../exceptions/ValidationException.php";

class ValidateMiddleware extends BaseMiddleware {
    private $requiredFields;

    public function __construct(array $fields){
        $this->requiredFields = $fields;
    }

    public function handle($request) {
        foreach ($this->requiredFields as $field){

            if(!isset($request[$field]) || empty($request[$field])) {
                throw new ValidationException("Validation failed: $field is required");
            }
        }
    }

 }

?>