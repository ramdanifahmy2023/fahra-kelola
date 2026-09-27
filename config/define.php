<?php

// Environment File Loader
$env = parse_ini_file(__DIR__ . '/.env');

// Core Application Info
define('app_name', $env['APP_NAME']);

// Use the request origin for web requests so tunneled/public pages never
// point a visitor's browser back to the server's loopback address.
$configuredAppUrl = rtrim((string)($env['APP_URL'] ?? ''), '/');
$requestHost = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
$requestAppUrl = '';
if ($requestHost !== '') {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $forwardedProto = trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
  if ($forwardedProto !== '') {
    $scheme = strtolower(trim(explode(',', $forwardedProto)[0]));
  } elseif (!empty($_SERVER['HTTP_CF_VISITOR'])) {
    $cloudflareVisitor = json_decode((string)$_SERVER['HTTP_CF_VISITOR'], true);
    if (is_array($cloudflareVisitor) && !empty($cloudflareVisitor['scheme'])) {
      $scheme = strtolower((string)$cloudflareVisitor['scheme']);
    }
  }
  if (!in_array($scheme, ['http', 'https'], true)) {
    $scheme = 'http';
  }
  $requestAppUrl = $scheme . '://' . $requestHost;
}
$appUrl = $requestAppUrl !== '' ? $requestAppUrl : $configuredAppUrl;
define('burl', $appUrl);
define('assets', $appUrl . '/assets');
define('images', $appUrl . '/assets/images');
define('web_icons', $appUrl . '/assets/web_icons');

// Routing Constants
define('DEFAULT_CONTROLLER', $env['DEFAULT_CONTROLLER']);

// Database Constants
define('DB_HOST', $env['DB_HOST']);
define('DB_USER', $env['DB_USER']);
define('DB_PASS', $env['DB_PASS']);
define('DB_NAME', $env['DB_NAME']);
