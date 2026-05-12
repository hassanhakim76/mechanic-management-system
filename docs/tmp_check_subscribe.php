<?php
error_reporting(E_ALL);
ini_set('display_errors',1);
require __DIR__ . '/config/config.php';
try {
  $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
  $col = $pdo->query("SHOW COLUMNS FROM customers LIKE 'subscribe'")->fetch(PDO::FETCH_ASSOC);
  var_dump($col);
  $vals = $pdo->query("SELECT subscribe, COUNT(*) c FROM customers GROUP BY subscribe ORDER BY c DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
  var_dump($vals);
} catch (Throwable $e) {
  echo 'ERR: ' . $e->getMessage() . PHP_EOL;
}
?>
