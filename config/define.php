<?php

// Environment File Loader
$env = parse_ini_file(__DIR__ . '/.env');

// Core Application Info
define('app_name', $env['APP_NAME']);

// Use the request origin for web requests so tunneled/public pages never
// point a visitor's browser back to the server's loopback address.
$configuredAppUrl = rtrim((string)($env['APP_URL'] ?? ''), '/');
$configuredParts = parse_url($configuredAppUrl) ?: [];
$configuredHost = strtolower((string)($configuredParts['host'] ?? ''));
$allowedHosts = array_values(array_filter(array_map(static function ($host) {
  $host = strtolower(trim((string)$host));
  return preg_replace('/:\d+$/', '', $host);
}, explode(',', (string)($env['APP_ALLOWED_HOSTS'] ?? '')))));
if (!$allowedHosts && $configuredHost !== '') $allowedHosts[] = $configuredHost;
$requestHost = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
$requestHostPort = null;
if ($requestHost !== '' && preg_match('/^(?:\[[0-9A-Fa-f:.]+\]|[A-Za-z0-9.-]+)(?::\d{1,5})?$/D', $requestHost)) {
  $requestHostPort = parse_url('http://' . $requestHost, PHP_URL_PORT);
  if ($requestHostPort !== null && ((int)$requestHostPort < 1 || (int)$requestHostPort > 65535)) $requestHost = '';
} else {
  $requestHost = '';
}
$requestHostName = strtolower((string)(parse_url('http://' . $requestHost, PHP_URL_HOST) ?? ''));
$requestHostAllowed = $requestHostName !== '' && in_array($requestHostName, $allowedHosts, true);
$requestAppUrl = '';
if ($requestHost !== '' && $requestHostAllowed) {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $trustProxy = in_array(strtolower((string)($env['APP_TRUST_PROXY'] ?? '')), ['1', 'true', 'yes'], true);
  $forwardedProto = $trustProxy ? trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) : '';
  if ($forwardedProto !== '') {
    $scheme = strtolower(trim(explode(',', $forwardedProto)[0]));
  } elseif ($trustProxy && !empty($_SERVER['HTTP_CF_VISITOR'])) {
    $cloudflareVisitor = json_decode((string)$_SERVER['HTTP_CF_VISITOR'], true);
    if (is_array($cloudflareVisitor) && !empty($cloudflareVisitor['scheme'])) {
      $scheme = strtolower((string)$cloudflareVisitor['scheme']);
    }
  }
  if (!in_array($scheme, ['http', 'https'], true)) {
    $scheme = 'http';
  }
  if (!empty($env['APP_FORCE_HTTPS']) && in_array(strtolower((string)$env['APP_FORCE_HTTPS']), ['1', 'true', 'yes'], true)) $scheme = 'https';
  $requestAppUrl = $scheme . '://' . $requestHost;
}
$appUrl = $requestAppUrl !== '' ? $requestAppUrl : $configuredAppUrl;
if ($appUrl === '' && $configuredHost === '') $appUrl = 'http://127.0.0.1';
define('burl', $appUrl);
define('assets', $appUrl . '/assets');
define('images', $appUrl . '/assets/images');
define('web_icons', $appUrl . '/assets/web_icons');

// Routing Constants
define('DEFAULT_CONTROLLER', $env['DEFAULT_CONTROLLER']);

// Database Constants
$databaseHost = (string)$env['DB_HOST'];
$databasePort = (int)($env['DB_PORT'] ?? 0);
if ($databasePort <= 0 && preg_match('/^(.+):(\d+)$/', $databaseHost, $databaseAddress)) {
  $databaseHost = $databaseAddress[1];
  $databasePort = (int)$databaseAddress[2];
}
define('DB_HOST', $databaseHost);
define('DB_PORT', $databasePort > 0 ? $databasePort : 3306);
define('DB_USER', $env['DB_USER']);
define('DB_PASS', $env['DB_PASS']);
define('DB_NAME', $env['DB_NAME']);
