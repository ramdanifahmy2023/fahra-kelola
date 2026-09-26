<?php
$docroot = __DIR__ . '/public';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = $docroot . $path;

if ($path !== '/' && is_file($file)) {
  return false;
}

if ($path !== '/') {
  $_GET['url'] = trim($path, '/');
}

chdir($docroot);
require $docroot . '/index.php';
