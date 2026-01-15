<?php
// TDD Construction Admin System - Entry Point

// 1. Load Configuration
require_once __DIR__ . '/config.php';

// 2. Define Core Paths (if not already defined)
if (!defined('ROOT_DIR'))
    define('ROOT_DIR', __DIR__);
define('CORE_DIR', ROOT_DIR . '/core');
define('MODULES_DIR', ROOT_DIR . '/modules');
define('ASSETS_DIR', ROOT_DIR . '/assets');

// 2. Load Autoloader
require_once CORE_DIR . '/Autoloader.php';
Core\Autoloader::register();

// Register Global Error Handler
require_once CORE_DIR . '/ErrorHandler.php';
Core\ErrorHandler::register();

// 3. Apply Security Hardening (Must be before session start)
\Core\Security::configureSecureSession();
\Core\Security::preventClickjacking();

// 4. Start Session
session_start();

// Disable Cache for Development
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 4. Initialize Core Components
try {
    $router = new Core\Router();
    $router->dispatch();
} catch (\Throwable $e) {
    // Delegate to Global Error Handler for consistency
    Core\ErrorHandler::handleException($e);
}
