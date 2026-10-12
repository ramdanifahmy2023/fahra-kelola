<?php

function authUser() {
  $user = $_SESSION['auth_user'] ?? null;
  if (!is_array($user)) return null;
  $id = $user['id'] ?? null;
  if (!(is_int($id) || (is_string($id) && ctype_digit($id)))) return null;
  $id = (int)$id;
  if ($id < 1) return null;
  $user['id'] = $id;
  return $user;
}

function authAccountId(): int {
  return (int)(authUser()['id'] ?? 0);
}

function authIsLoggedIn() {
  return authAccountId() > 0;
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

function authRequestCsrfToken(): ?string {
  $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_XSRF_TOKEN'] ?? null;
  if (is_string($header) && $header !== '') return $header;
  $token = $_POST['csrf_token'] ?? null;
  return is_string($token) ? $token : null;
}

function authRequireCsrf(): void {
  if (!authVerifyCsrf(authRequestCsrfToken())) {
    http_response_code(403);
    if (stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['status' => 'error', 'message' => 'Sesi formulir berakhir. Muat ulang halaman.'], JSON_UNESCAPED_UNICODE);
      exit;
    }
    Alert::set('error', 'Sesi formulir sudah kedaluwarsa. Silakan coba lagi.');
    authRedirect('/login');
  }
}

function authRequireMethod(string $method): void {
  if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== strtoupper($method)) {
    http_response_code(405);
    header('Allow: ' . strtoupper($method));
    exit;
  }
}

function authSafeNext($value) {
  $next = trim((string)$value);
  if ($next === '' || $next[0] !== '/' || substr($next, 0, 2) === '//' || preg_match('/[\\\\\x00-\x1F\x7F]/', $next) || parse_url($next, PHP_URL_HOST) !== null) {
    return '/panel';
  }
  return $next;
}

function authRedirect($path = '/login') {
  header('Location: ' . burl . authSafeNext($path));
  exit;
}

function authLogin($account) {
  $accountId = (int)($account['id'] ?? 0);
  if ($accountId < 1) throw new RuntimeException('Akun login tidak valid.');
  session_regenerate_id(true);
  unset($_SESSION['auth_failed_count'], $_SESSION['auth_failed_window']);
  $_SESSION['auth_user'] = [
    'id' => $accountId,
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
