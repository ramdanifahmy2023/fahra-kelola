<?php

if (PHP_SAPI !== 'cli') {
  exit("This command must run from the CLI.\n");
}

[$script, $name, $email, $password] = array_pad($argv, 4, '');
$name = trim($name);
$email = strtolower(trim($email));

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
  exit("Usage: php bin/create-admin.php \"Name\" email@example.com password-minimum-8-characters\n");
}

require_once __DIR__ . '/../config/define.php';

$pdo = new PDO(
  'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
  DB_USER,
  DB_PASS,
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$statement = $pdo->prepare(
  'INSERT INTO accounts (name, email, password) VALUES (:name, :email, :password) '
  . 'ON DUPLICATE KEY UPDATE name = VALUES(name), password = VALUES(password)'
);
$statement->execute([
  ':name' => $name,
  ':email' => $email,
  ':password' => password_hash($password, PASSWORD_DEFAULT),
]);

echo "Admin account is ready for {$email}.\n";
