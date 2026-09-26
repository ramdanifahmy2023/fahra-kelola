<?php

// Environment File Loader
$env = parse_ini_file(__DIR__ . '/.env');

// Core Application Info
define('app_name', $env['APP_NAME']);
define('burl', $env['APP_URL']);
define('assets', $env['APP_URL'] . '/assets');
define('images', $env['APP_URL'] . '/assets/images');
define('web_icons', $env['APP_URL'] . '/assets/web_icons');

// Routing Constants
define('DEFAULT_CONTROLLER', $env['DEFAULT_CONTROLLER']);

// Database Constants
define('DB_HOST', $env['DB_HOST']);
define('DB_USER', $env['DB_USER']);
define('DB_PASS', $env['DB_PASS']);
define('DB_NAME', $env['DB_NAME']);
