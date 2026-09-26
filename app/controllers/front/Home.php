<?php

class Home extends Controller {
  // Index
  public function index() {
    $data['judul'] = 'Beranda - ' . app_name;
    
    $this->v('landing/templates/header', $data);
    $this->v('landing/index', $data);
    $this->v('landing/templates/footer');
  }
}
