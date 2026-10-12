<?php

// Timezone Configuration
date_default_timezone_set('Asia/Jakarta');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cross-Origin-Opener-Policy: same-origin');
header("Content-Security-Policy: default-src 'self' https: data: blob:; script-src 'self' 'unsafe-inline'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
$runtimeEnv = @parse_ini_file(__DIR__ . '/../config/.env') ?: [];
$trustedForwardedProto = in_array(strtolower((string)($runtimeEnv['APP_TRUST_PROXY'] ?? '')), ['1', 'true', 'yes'], true) ? strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0])) : '';
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $trustedForwardedProto === 'https') {
  header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// Session Setup
if (session_status() === PHP_SESSION_NONE) {
  ini_set('session.use_strict_mode', '1');
  ini_set('session.use_only_cookies', '1');
  ini_set('session.cookie_httponly', '1');
  $forwardedProto = $trustedForwardedProto;
  $secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'secure' => $secureCookie,
    'samesite' => 'Lax'
  ]);
  session_start();
}

// Bootstrapping
require_once '../app/init.php';

// Authentication Guard
$route = trim((string)($_GET['url'] ?? ''), '/');
$routeParts = $route === '' ? [] : explode('/', $route);
$routeRoot = strtolower((string)($routeParts[0] ?? ''));
$publicRoutes = ['', 'home', 'login', 'auth'];
if (!in_array($routeRoot, $publicRoutes, true) && !authIsLoggedIn()) {
  $requestPath = '/' . ltrim((string)($_SERVER['REQUEST_URI'] ?? '/panel'), '/');
  $requestPath = strtok($requestPath, '?') ?: '/panel';
  header('Location: ' . burl . '/login?next=' . rawurlencode(authSafeNext($requestPath)));
  exit;
}

// App Initialization
$app = new App();
