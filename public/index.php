<?php

// Timezone Configuration
date_default_timezone_set('Asia/Jakarta');

// Session Setup
if (session_status() === PHP_SESSION_NONE) {
  $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
  $secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';
  session_set_cookie_params([
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
