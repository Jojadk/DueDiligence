<?php
// System Configuration

// Development Mode
define('DEV_MODE', true);

// Asset Caching
// Set to false during development to force browser to load fresh files
// Set to true in production for performance
define('ASSET_CACHE_ENABLED', false);

// Database Configuration (Fallback)
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
