<?php

class Controller {
  // View Loader
  public function v($view, $data = []) {
    require_once '../app/views/' . $view . '.php';
  }

  // Model Loader
  public function m($model) {
    require_once '../app/models/' . $model . '.php';
    return new $model;
  }
}
