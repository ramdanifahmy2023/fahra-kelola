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

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
      Alert::set('error', 'Masukkan email dan password yang valid.');
      header('Location: ' . burl . '/login?next=' . rawurlencode($next));
      exit;
    }

    $account = $this->m('Account')->findBy('email', $email);
    if (!$account || !password_verify($password, (string)($account['password'] ?? ''))) {
      Alert::set('error', 'Email atau password salah.');
      header('Location: ' . burl . '/login?next=' . rawurlencode($next));
      exit;
    }

    authLogin($account);
    header('Location: ' . burl . $next);
    exit;
  }

  public function logout() {
    authLogout();
    Alert::set('success', 'Anda sudah keluar dari Shopdash.');
    header('Location: ' . burl . '/login');
    exit;
  }
}
