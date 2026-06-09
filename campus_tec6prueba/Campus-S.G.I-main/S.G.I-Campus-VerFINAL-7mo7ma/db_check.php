<?php
// No compartir este documento.
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

$host = 'sql103.infinityfree.com';
$db   = 'if0_39451587_campus';
$user = 'if0_39451587';
$pass = 'Practicastec6';

try {
  $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4",$user,$pass,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
  ]);
  echo "DB OK<br>\n";

  $stmt = $pdo->query("SHOW TABLES");
  while ($row = $stmt->fetch(PDO::FETCH_NUM)) {   // ← filas numéricas
    echo htmlspecialchars($row[0])."<br>\n";      // ← ya existe índice 0
  }
} catch (Throwable $e){
  http_response_code(500);
  echo "DB ERROR: ".$e->getMessage();
}
