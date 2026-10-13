<?php

class Auth extends Controller {
  public function login() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      authRedirect('/login');
    }

    if (!authVerifyCsrf($_POST['csrf_token'] ?? null)) {
      Alert::set('error', 'Sesi formulir sudah kedaluwarsa. Silakan coba lagi.');
      authRedirect('/login');
    }

    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $next = authSafeNext($_POST['next'] ?? '/panel');

    $now = time();
    $window = (int)($_SESSION['auth_failed_window'] ?? 0);
    $failed = (int)($_SESSION['auth_failed_count'] ?? 0);
    if ($window < $now - 900) { $window = $now; $failed = 0; }
    if ($failed >= 10) {
      usleep(250000);
      Alert::set('error', 'Terlalu banyak percobaan masuk. Coba lagi beberapa menit lagi.');
      header('Location: ' . burl . '/login?next=' . rawurlencode($next));
      exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
      Alert::set('error', 'Masukkan email dan password yang valid.');
      header('Location: ' . burl . '/login?next=' . rawurlencode($next));
      exit;
    }

    $account = $this->m('Account')->findBy('email', $email);
    if (!$account || !password_verify($password, (string)($account['password'] ?? ''))) {
      $_SESSION['auth_failed_window'] = $window;
      $_SESSION['auth_failed_count'] = $failed + 1;
      usleep(150000);
      Alert::set('error', 'Email atau password salah.');
      header('Location: ' . burl . '/login?next=' . rawurlencode($next));
      exit;
    }

    authLogin($account);
    header('Location: ' . burl . $next);
    exit;
  }

  public function logout() {
    authRequireMethod('POST');
    authRequireCsrf();
    authLogout();
    Alert::set('success', 'Anda sudah keluar dari Shopdash.');
    header('Location: ' . burl . '/login');
    exit;
  }
}
