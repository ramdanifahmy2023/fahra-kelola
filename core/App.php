<?php

class App {
  // Default Routes
  protected $controller = DEFAULT_CONTROLLER;
  protected $method = 'index';
  protected $params = [];
  protected $path = 'front/';

  public function __construct() {
    $url = $this->parseURL();

    // Controller Setup
    if (isset($url[0])) {
      $controllerName = ucfirst($url[0]);
      
      // Cari di folder front
      if (file_exists('../app/controllers/front/' . $controllerName . '.php')) {
        $this->controller = $controllerName;
        $this->path = 'front/';
        unset($url[0]);
      }
      // Cari di folder back
      elseif (file_exists('../app/controllers/back/' . $controllerName . '.php')) {
        $this->controller = $controllerName;
        $this->path = 'back/';
        unset($url[0]);
      }
      // Cari di root controllers
      elseif (file_exists('../app/controllers/' . $controllerName . '.php')) {
        $this->controller = $controllerName;
        $this->path = '';
        unset($url[0]);
      }
    }

    // Controller Instantiation
    require_once '../app/controllers/' . $this->path . $this->controller . '.php';
    $this->controller = new $this->controller;

    // Method Setup
    if (isset($url[1])) {
      if (method_exists($this->controller, $url[1])) {
        $this->method = $url[1];
        unset($url[1]);
      }
    }

    // Parameters Setup
    if (!empty($url)) {
      $this->params = array_values($url);
    }

    // Execution
    call_user_func_array([$this->controller, $this->method], $this->params);
  }

  // URL Parser
  public function parseURL() {
    if (isset($_GET['url'])) {
      $url = rtrim($_GET['url'], '/');
      $url = filter_var($url, FILTER_SANITIZE_URL);
      $url = explode('/', $url);
      return $url;
    }
    return [];
  }
}
