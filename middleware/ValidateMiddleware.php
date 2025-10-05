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
        foreach ($this->requiredFields as $field => $label) {
            if (is_numeric($field)) {
                $field = $label;
                $label = ucfirst(str_replace('_', ' ', $field));
            }

            // Required field check
            if (!isset($request[$field]) || empty(trim($request[$field]))) {
                throw new ValidationException("$label is required");
            }


            // First Name validation
            if ($field === 'first_name' || $field === 'firstName') {
                $firstName = trim($request[$field]);

                // Length check (2-50 characters)
                if (strlen($firstName) < 2 || strlen($firstName) > 50) {
                    throw new ValidationException("First name must be between 2 and 50 characters");
                }

                // Only letters, spaces, hyphens, and apostrophes allowed
                if (!preg_match('/^[a-zA-Z\s\-\'\.]+$/', $firstName)) {
                    throw new ValidationException("First name can only contain letters, spaces, hyphens, and apostrophes");
                }

                $request[$field] = $firstName;
            }

            // Last Name validation
            if ($field === 'last_name' || $field === 'lastName') {
                $lastName = trim($request[$field]);

                // Length check (2-50 characters)
                if (strlen($lastName) < 2 || strlen($lastName) > 50) {
                    throw new ValidationException("Last name must be between 2 and 50 characters");
                }

                // Only letters, spaces, hyphens, and apostrophes allowed
                if (!preg_match('/^[a-zA-Z\s\-\'\.]+$/', $lastName)) {
                    throw new ValidationException("Last name can only contain letters, spaces, hyphens, and apostrophes");
                }

                $request[$field] = $lastName;
            }

            // Email validation: lowercase + valid format + DNS domain check + min length before @
            if ($field === 'email') {
                $email = trim($request[$field]);

                $isValid = true;

                // Format check + lowercase check
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $email !== strtolower($email)) {
                    $isValid = false;
                } else {
                    list($user, $domain) = explode('@', $email);

                    // Domain MX check
                    if (!checkdnsrr($domain, "MX")) {
                        $isValid = false;
                    }

                    // Gmail min length check
                    if ($domain === "gmail.com" && strlen($user) < 7) {
                        $isValid = false;
                    }
                }

                if (!$isValid) {
                    throw new ValidationException("Invalid email. Please enter correct email");
                }

                $request[$field] = strtolower($email);
            }



            // Contact number validation: must start with 09 and be exactly 11 digits
            if ($field === 'contact_no' || $field === 'contact_number' || $field === 'phone') {
                $contactNo = trim($request[$field]);

                // Remove any spaces, dashes, or other characters
                $contactNo = preg_replace('/[^0-9]/', '', $contactNo);

                // Combined validation: must start with 09 and be exactly 11 digits
                if (!preg_match('/^09[0-9]{9}$/', $contactNo)) {
                    throw new ValidationException("Invalid contact number. Must start with '09' and be exactly 11 digits");
                }

                $request[$field] = $contactNo;
            }


            if ($field === 'username') {
                $username = trim($request[$field]);

                // Required
                if (empty($username)) {
                    throw new ValidationException("Username is required");
                }

                // Length check (3–20 chars)
                if (strlen($username) < 3 || strlen($username) > 20) {
                    throw new ValidationException("Username must be between 3 and 20 characters");
                }

                // Allowed characters: only letters, numbers, underscore
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                    throw new ValidationException("Username can only contain letters, numbers, and underscores");
                }

                // Auto-lowercase
                $request[$field] = strtolower($username);
            }

            // ✅ Apply password rules only if register route
            if ($field === 'password' && $this->route === 'register') {
                $password = $request[$field];
                $errors = [];

                if (strlen($password) < 8) {
                    $errors[] = "at least 8 characters";
                }
                if (!preg_match('/[A-Z]/', $password)) {
                    $errors[] = "at least one uppercase letter";
                }
                if (!preg_match('/[a-z]/', $password)) {
                    $errors[] = "at least one lowercase letter";
                }
                if (!preg_match('/[0-9]/', $password)) {
                    $errors[] = "at least one number";
                }
                if (!preg_match('/[\W]/', $password)) {
                    $errors[] = "at least one special character";
                }

                if (!empty($errors)) {
                    throw new ValidationException("Password must contain " . implode(", ", $errors));
                }
            }
        }


        return $request;
    }
}
