<?php

class Controller {
  // View Loader
  public function v($view, $data = []) {
    $view = str_replace('\\', '/', (string)$view);
    if (!preg_match('#^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$#', $view)) { http_response_code(404); exit('Not Found'); }
    require_once '../app/views/' . $view . '.php';
  }

  // Model Loader
  public function m($model) {
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', (string)$model)) { throw new InvalidArgumentException('Model tidak valid.'); }
    require_once '../app/models/' . $model . '.php';
    return new $model;
  }
}
