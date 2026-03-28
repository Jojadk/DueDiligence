<?php
// Core Bootstrap
if (!defined('ROOT_DIR'))
    define('ROOT_DIR', dirname(__DIR__));
if (!defined('CORE_DIR'))
    define('CORE_DIR', ROOT_DIR . '/core');
if (!defined('MODULES_DIR'))
    define('MODULES_DIR', ROOT_DIR . '/modules');
if (!defined('ASSETS_DIR'))
    define('ASSETS_DIR', ROOT_DIR . '/assets');

// Database Configuration
if (!defined('DB_HOST'))
    define('DB_HOST', getenv('DB_HOST') ?: '172.17.0.2');
if (!defined('DB_PORT'))
    define('DB_PORT', getenv('DB_PORT') ?: '5432');
if (!defined('DB_NAME'))
    define('DB_NAME', getenv('DB_NAME') ?: 'TDD_System');
if (!defined('DB_USER'))
    define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS'))
    define('DB_PASS', getenv('DB_PASS') ?: 'root');

// Autoloader
require_once CORE_DIR . '/Autoloader.php';
\Core\Autoloader::register();
