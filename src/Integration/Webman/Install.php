<?php

namespace Erikwang2013\Consul\Integration\Webman;

class Install
{
    public const WEBMAN_PLUGIN = true;

    public static function install(): void
    {
        $configDir = config_path() . '/plugin/erikwang2013/consul-php';
        if (!is_dir($configDir) && !mkdir($configDir, 0755, true) && !is_dir($configDir)) {
            throw new \RuntimeException("Failed to create config directory: {$configDir}");
        }

        $configPath = $configDir . '/app.php';
        if (!file_exists($configPath)) {
            $source = __DIR__ . '/config/app.php';
            if (!copy($source, $configPath)) {
                throw new \RuntimeException("Failed to copy config file to: {$configPath}");
            }
        }
    }

    public static function uninstall(): void
    {
        $configPath = config_path() . '/plugin/erikwang2013/consul-php/app.php';
        if (file_exists($configPath) && !unlink($configPath)) {
            throw new \RuntimeException("Failed to remove config file: {$configPath}");
        }
    }
}
