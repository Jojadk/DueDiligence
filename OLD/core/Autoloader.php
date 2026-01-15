<?php
namespace Core;

class Autoloader
{
    public static function register()
    {
        if (!defined('CORE_DIR')) {
            define('CORE_DIR', __DIR__);
        }
        if (!defined('MODULES_DIR')) {
            define('MODULES_DIR', dirname(__DIR__) . '/modules');
        }

        spl_autoload_register(function ($class) {
            // Project-specific namespace mapping
            // Core\ClassName -> core/ClassName.php
            // Modules\ModuleName\Controller -> modules/ModuleName/Controller.php

            $prefix = '';
            $base_dir = '';

            if (strpos($class, 'Core\\') === 0) {
                $prefix = 'Core\\';
                $base_dir = \CORE_DIR . '/';
            } elseif (strpos($class, 'Modules\\') === 0) {
                $prefix = 'Modules\\';
                $base_dir = \MODULES_DIR . '/';
            } else {
                return;
            }

            // Get the relative class name
            $len = strlen($prefix);
            $relative_class = substr($class, $len);

            // Replace namespace separators with directory separators
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

            // If the file exists, require it
            if (file_exists($file)) {
                require $file;
            }
        });
    }
}
