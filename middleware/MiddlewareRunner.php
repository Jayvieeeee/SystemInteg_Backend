<?php
class MiddlewareRunner {
    private $middlewares = [];

    public function add($middleware) {
        $this->middlewares[] = $middleware;
    }

    public function run($request) {
        foreach ($this->middlewares as $middleware) {
            $result = $middleware->handle($request);
            if ($result) {
                $request = $result; // kung may binago (e.g., sanitize), i-update
            }
        }
        return $request;
    }
}
