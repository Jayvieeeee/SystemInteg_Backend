<?php

class Container
{
    private $services = [];
    private $instances = [];

    public function set($name, callable $factory)
    {
        $this->services[$name] = $factory;
    }

    public function get($name, array $params = [])
    {
        // Check if we already have an instance (for singletons)
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        if (!isset($this->services[$name])) {
            throw new Exception("Service not found: " . $name);
        }

        $instance = $this->services[$name]($this, $params);

        // Store instance for singleton behavior (optional)
        $this->instances[$name] = $instance;

        return $instance;
    }

    public function has($name): bool
    {
        return isset($this->services[$name]);
    }
}
