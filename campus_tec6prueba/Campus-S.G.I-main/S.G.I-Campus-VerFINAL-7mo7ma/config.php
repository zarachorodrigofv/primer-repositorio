<?php
// No compartir este documento.
// config.php
// Datos de la base de datos MySQL en InfinityFree:
$db_host = "localhost"; // host
$db_name = "campus";     // nombre
$db_user = "root";            // usuario MySQL
$db_pass = "";           // contraseña 

function db() {
    global $db_host, $db_name, $db_user, $db_pass;
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
    return $pdo;
}
?>
