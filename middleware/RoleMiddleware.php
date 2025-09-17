<?php

require_once "BaseMiddleware";
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
        if (!isset($request['userRole']) || $request['userRole'] !== $this->requiredRole) {
            throw new AuthException("Insufficient role");
        }
    }
}
