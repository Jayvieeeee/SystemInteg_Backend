<?php
require_once "BaseMiddleware.php";
require_once __DIR__ . "/../exceptions/ValidationException.php";

class ValidateMiddleware extends BaseMiddleware
{
    private $requiredFields;

    public function __construct(array $fields)
    {
        $this->requiredFields = $fields;
    }

    public function handle($request)
    {
        foreach ($this->requiredFields as $field => $label) {
            // Handle simple arrays (numeric keys)
            if (is_numeric($field)) {
                $field = $label;
                $label = ucfirst(str_replace('_', ' ', $field));
            }

            if (!isset($request[$field]) || empty(trim($request[$field]))) {
                throw new ValidationException("Validation failed: $label is required");
            }
        }
    }
}
