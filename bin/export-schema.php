<?php

if (PHP_SAPI !== 'cli') {
  exit("This command must run from the CLI.\n");
}

require_once __DIR__ . '/../config/define.php';

$output = $argv[1] ?? (__DIR__ . '/../database/schema.sql');
$pdo = new PDO(
  'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
  DB_USER,
  DB_PASS,
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
sort($tables, SORT_STRING);

$sql = "-- Generated schema only. No application data or credentials.\n";
$sql .= 'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', DB_NAME) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;\n";
$sql .= 'USE `' . str_replace('`', '``', DB_NAME) . "`;\n\n";
$sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

foreach ($tables as $table) {
  $quotedTable = '`' . str_replace('`', '``', $table) . '`';
  $row = $pdo->query("SHOW CREATE TABLE {$quotedTable}")->fetch(PDO::FETCH_NUM);
  $create = preg_replace('/ AUTO_INCREMENT=\d+/', '', $row[1]);
  $sql .= "DROP TABLE IF EXISTS {$quotedTable};\n{$create};\n\n";
}

$sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
file_put_contents($output, $sql);
echo 'Exported ' . count($tables) . " tables to {$output}.\n";
