<?php

class Login extends Controller {
  public function index() {
    if (authIsLoggedIn()) {
      authRedirect('/panel');
    }

    $data['judul'] = 'Masuk - ' . app_name;
    $data['next'] = authSafeNext($_GET['next'] ?? '/panel');
    $this->v('login/index', $data);
  }
}
