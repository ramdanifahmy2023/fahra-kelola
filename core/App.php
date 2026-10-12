<?php

class App {
  // Default Routes
  protected $controller = DEFAULT_CONTROLLER;
  protected $method = 'index';
  protected $params = [];
  protected $path = 'front/';

  private const ROUTES = [
    'front' => ['home' => 'Home', 'login' => 'Login', 'panel' => 'Panel'],
    'back' => ['auth' => 'Auth', 'procads' => 'ProcAds', 'procaiconnections' => 'ProcAiConnections', 'procautomation' => 'ProcAutomation', 'procboost' => 'ProcBoost', 'procchat' => 'ProcChat', 'proccustomers' => 'ProcCustomers', 'procdashboard' => 'ProcDashboard', 'procfinance' => 'ProcFinance', 'procnotifications' => 'ProcNotifications', 'procorders' => 'ProcOrders', 'procproducts' => 'ProcProducts', 'procrealtime' => 'ProcRealtime', 'procreports' => 'ProcReports', 'procsearch' => 'ProcSearch', 'procshops' => 'ProcShops', 'procsync' => 'ProcSync', 'procworkspace' => 'ProcWorkspace', 'procpromotions' => 'ProcPromotions']
  ];

  public function __construct() {
    if (!in_array(strtolower((string)$this->controller), array_map('strtolower', self::ROUTES['front']), true)) $this->controller = 'Home';
    $url = $this->parseURL();

    // Controller Setup
    if (isset($url[0]) && $url[0] !== '') {
      $routeKey = strtolower((string)$url[0]);
      $route = null;
      foreach (self::ROUTES as $path => $routes) if (isset($routes[$routeKey])) { $route = [$path, $routes[$routeKey]]; break; }
      if ($route === null) { http_response_code(404); exit('Not Found'); }
      [$this->path, $this->controller] = [$route[0] . '/', $route[1]];
      unset($url[0]);
    }

    // Controller Instantiation
    require_once '../app/controllers/' . $this->path . $this->controller . '.php';
    $this->controller = new $this->controller;

    // Method Setup
    if (isset($url[1])) {
      $candidate = (string)$url[1];
      if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $candidate) && method_exists($this->controller, $candidate)) {
        $reflection = new ReflectionMethod($this->controller, $candidate);
        if (!$reflection->isPublic() || str_starts_with($candidate, '_') || $reflection->getDeclaringClass()->getName() !== get_class($this->controller)) { http_response_code(404); exit('Not Found'); }
        $this->method = $candidate;
        unset($url[1]);
      } else { http_response_code(404); exit('Not Found'); }
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
      $url = rtrim((string)$_GET['url'], '/');
      if ($url === '' || strlen($url) > 300 || preg_match('/[\x00-\x1F\x7F]/', $url)) return [];
      $url = filter_var($url, FILTER_SANITIZE_URL);
      $url = explode('/', $url);
      if (count($url) > 8) return [];
      return $url;
    }
    return [];
  }
}
