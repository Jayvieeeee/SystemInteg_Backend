<?php
require_once "BaseMiddleware.php";
require_once __DIR__ . "/../exceptions/ValidationException.php";

class ValidateMiddleware extends BaseMiddleware
{
    private $requiredFields;
    private $route;

    public function __construct(array $options)
    {
        $this->requiredFields = $options['fields'] ?? [];
        $this->route = $options['route'] ?? null;
    }

    public function handle($request)
    {
        $isOptionalUpdate = in_array($this->route, ['updateProfile', 'updateGallon']);

        foreach ($this->requiredFields as $field => $label) {
            if (is_numeric($field)) {
                $field = $label;
                $label = ucfirst(str_replace('_', ' ', $field));
            }

            // Skip validation if field not present in optional updates
            if ($isOptionalUpdate && !isset($request[$field])) {
                continue;
            }

            // Required field check (skip for optional updates)
            if (!$isOptionalUpdate && (!isset($request[$field]) || empty(trim($request[$field])))) {
                throw new ValidationException("$label is required");
            }

            // If field exists but empty in optional update
            if ($isOptionalUpdate && isset($request[$field]) && empty(trim($request[$field]))) {
                throw new ValidationException("$label cannot be empty");
            }

            // Call validation functions based on field
            $request[$field] = $this->validateField($field, $request[$field], $request);
        }

        return $request;
    }

    private function validateField($field, $value, $request)
    {
        switch ($field) {
            case 'first_name':
            case 'firstName':
                return $this->validateName($value, 'First name');

            case 'last_name':
            case 'lastName':
                return $this->validateName($value, 'Last name');

            case 'email':
                return $this->validateEmail($value);

            case 'contact_no':
            case 'contact_number':
            case 'phone':
                return $this->validateContactNumber($value);

            case 'username':
                return $this->validateUsername($value);

            case 'password':
                if ($this->route === 'register') {
                    $this->validatePassword($value);
                }
                return $value;

            case 'new_password':
                if ($this->route === 'changePassword') {
                    $this->validatePassword($value);
                }
                return $value;

            case 'confirm_password':
                if ($this->route === 'changePassword') {
                    $this->validateConfirmPassword($request['new_password'], $value);
                }
                return $value;

            case 'name':
                if (in_array($this->route, ['createGallon', 'updateGallon'])) {
                    return $this->validateGallonName($value);
                }
                return $value;

            case 'size':
                if (in_array($this->route, ['createGallon', 'updateGallon'])) {
                    return $this->validateGallonSize($value);
                }
                return $value;

            case 'price':
                if (in_array($this->route, ['createGallon', 'updateGallon'])) {
                    return $this->validatePrice($value);
                }
                return $value;

            default:
                return $value;
        }
    }

    private function validateName($value, $fieldName)
    {
        $name = trim($value);

        if (strlen($name) < 2 || strlen($name) > 50) {
            throw new ValidationException("$fieldName must be between 2 and 50 characters");
        }

        if (!preg_match('/^[a-zA-Z\s\-\'\.]+$/', $name)) {
            throw new ValidationException("$fieldName can only contain letters, spaces, hyphens, and apostrophes");
        }

        return $name;
    }

    private function validateEmail($value)
    {
        $email = trim($value);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $email !== strtolower($email)) {
            throw new ValidationException("Invalid email. Please enter correct email");
        }

        list($user, $domain) = explode('@', $email);

        if (getenv('SKIP_MX_CHECK') !== '1' && !checkdnsrr($domain, "MX")) {
            throw new ValidationException("Invalid email. Please enter correct email");
        }

        if ($domain === "gmail.com" && strlen($user) < 7) {
            throw new ValidationException("Invalid email. Please enter correct email");
        }

        return strtolower($email);
    }

    private function validateContactNumber($value)
    {
        $contactNo = preg_replace('/[^0-9]/', '', trim($value));

        if (!preg_match('/^09[0-9]{9}$/', $contactNo)) {
            throw new ValidationException("Invalid contact number. Must start with '09' and be exactly 11 digits");
        }

        return $contactNo;
    }

    private function validateUsername($value)
    {
        $username = trim($value);

        if (empty($username)) {
            throw new ValidationException("Username is required");
        }

        if (strlen($username) < 3 || strlen($username) > 20) {
            throw new ValidationException("Username must be between 3 and 20 characters");
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            throw new ValidationException("Username can only contain letters, numbers, and underscores");
        }

        return strtolower($username);
    }

    private function validatePassword($value)
    {
        $errors = [];

        if (strlen($value) < 8) {
            $errors[] = "at least 8 characters";
        }
        if (!preg_match('/[A-Z]/', $value)) {
            $errors[] = "at least one uppercase letter";
        }
        if (!preg_match('/[a-z]/', $value)) {
            $errors[] = "at least one lowercase letter";
        }
        if (!preg_match('/[0-9]/', $value)) {
            $errors[] = "at least one number";
        }
        if (!preg_match('/[\W]/', $value)) {
            $errors[] = "at least one special character";
        }

        if (!empty($errors)) {
            throw new ValidationException("Password must contain " . implode(", ", $errors));
        }
    }

    private function validateConfirmPassword($newPassword, $confirmPassword)
    {
        if ($newPassword !== $confirmPassword) {
            throw new ValidationException("New passwords do not match");
        }
    }

    private function validateGallonName($value)
    {
        $name = trim($value);

        if (strlen($name) < 2 || strlen($name) > 100) {
            throw new ValidationException("Gallon name must be between 2 and 100 characters");
        }

        if (!preg_match('/^[a-zA-Z\s\-\'\.]+$/', $name)) {
            throw new ValidationException("Gallon name can only contain letters and spaces");
        }

        return $name;
    }

    private function validateGallonSize($value)
    {
        $size = trim($value);

        if (strlen($size) < 1 || strlen($size) > 50) {
            throw new ValidationException("Size must be between 1 and 50 characters");
        }

        if (!preg_match('/^[a-zA-Z0-9\s\.\-\/]+$/', $size)) {
            throw new ValidationException("Size can only contain letters, numbers, spaces, and basic punctuation");
        }

        return $size;
    }

    private function validatePrice($value)
    {
        $price = trim($value);

        if (!is_numeric($price)) {
            throw new ValidationException("Price must be a valid number");
        }

        $priceFloat = (float)$price;

        if ($priceFloat < 0) {
            throw new ValidationException("Price must be a positive number");
        }

        if ($priceFloat > 999999.99) {
            throw new ValidationException("Price is too high");
        }

        return number_format($priceFloat, 2, '.', '');
    }
}
