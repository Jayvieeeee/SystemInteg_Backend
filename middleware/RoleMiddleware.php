<?php

require_once "BaseMiddleware.php";
require_once __DIR__ . "/../exceptions/AuthException.php";

class RoleMiddleware extends BaseMiddleware
{
    private $requiredRole;

    public function __construct($role)
    {
        $this->requiredRole = $role;
    }

    public function handle($request)
    {
        // Check if role exists in request
        if (!isset($request['role'])) {
            throw new AuthException("Role not found in request", 403);
        }

        // Check if user has required role
        if ($request['role'] !== $this->requiredRole) {
            throw new AuthException("Insufficient permissions. Required role: {$this->requiredRole}", 403);
        }

        // Role check passed, continue
        return $request;
    }
}
