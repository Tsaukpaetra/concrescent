<?php

namespace CM3_Lib;

class AppConfig
{
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    public function set(string $key, $value)
    {
        $this->config[$key] = $value;
    }

    public function all(): array
    {
        return $this->config;
    }
}
