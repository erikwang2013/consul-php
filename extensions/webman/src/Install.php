<?php

namespace Erikwang2013\Consul\Webman;

class Install
{
    public const WEBMAN_PLUGIN = true;

    public static function install(): void
    {
        $configDir = config_path() . '/plugin/erikwang/consul-php';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $configPath = $configDir . '/app.php';
        if (!file_exists($configPath)) {
            copy(__DIR__ . '/config/plugin/erikwang/consul-php/app.php', $configPath);
        }
    }

    public static function uninstall(): void
    {
        $configPath = config_path() . '/plugin/erikwang/consul-php/app.php';
        if (file_exists($configPath)) {
            unlink($configPath);
        }
    }
}
