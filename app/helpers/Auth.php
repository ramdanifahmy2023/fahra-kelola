<?php

function authUser() {
  return isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user']) ? $_SESSION['auth_user'] : null;
}

function authIsLoggedIn() {
  return authUser() !== null;
}

function authCsrfToken() {
  if (empty($_SESSION['auth_csrf'])) {
    $_SESSION['auth_csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['auth_csrf'];
}

function authVerifyCsrf($token) {
  return is_string($token) && !empty($_SESSION['auth_csrf']) && hash_equals($_SESSION['auth_csrf'], $token);
}

function authSafeNext($value) {
  $next = trim((string)$value);
  if ($next === '' || $next[0] !== '/' || substr($next, 0, 2) === '//') {
    return '/panel';
  }
  return $next;
}

function authRedirect($path = '/login') {
  header('Location: ' . burl . authSafeNext($path));
  exit;
}

function authLogin($account) {
  session_regenerate_id(true);
  $_SESSION['auth_user'] = [
    'id' => (int)($account['id'] ?? 0),
    'name' => (string)($account['name'] ?? ''),
    'email' => (string)($account['email'] ?? '')
  ];
  $_SESSION['auth_csrf'] = bin2hex(random_bytes(32));
}

function authLogout() {
  $_SESSION = [];
  session_regenerate_id(true);
  $_SESSION['auth_csrf'] = bin2hex(random_bytes(32));
}
