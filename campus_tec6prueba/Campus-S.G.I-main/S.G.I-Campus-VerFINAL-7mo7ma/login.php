<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

//Leer datos del form
$dni = isset($_POST['dni']) ? trim($_POST['dni']) : '';
$password = isset($_POST['password']) ? (string)$_POST['password'] : '';

if ($dni === '' || $password === '') {
  header("Location: index.html?login=error&msg=" . urlencode("Completá DNI y contraseña."));
  exit;
}

//Conectar a MySQL
$conn = new mysqli("localhost", "root", "", "campus");
if ($conn->connect_error) {
  die("Conexión fallida: " . $conn->connect_error);
}

//Traer usuario por DNI
$sql = "SELECT dni, nombre, password, rol, password_changed FROM usuarios WHERE dni = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $dni); // dni es INT en tu DB
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
  $stmt->close();
  $conn->close();

  header("Location: index.html?login=error&msg=" . urlencode("DNI no encontrado."));
  exit;
}

$usuario = $result->fetch_assoc();
$stmt->close();

// 4) Verificar contraseña
$hash = $usuario['password'];
$ok = false;

if (strlen($hash) >= 60 && strncmp($hash, '$2y$', 4) === 0) {
  $ok = password_verify($password, $hash);
} else {
  if (hash_equals($hash, $password)) {
    $ok = true;
    // Migrar a hash seguro
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $upd = $conn->prepare("UPDATE usuarios SET password = ? WHERE dni = ?");
    $upd->bind_param("si", $newHash, $usuario['dni']);
    $upd->execute();
    $upd->close();
  }
}

if (!$ok) {
  $conn->close();

  header("Location: index.html?login=error&msg=" . urlencode("Contraseña incorrecta."));
  exit;
}

$_SESSION['dni']                  = (int)$usuario['dni'];
$_SESSION['usuario']               = $usuario['nombre'];
$_SESSION['rol']                   = strtolower(trim($usuario['rol']));
$_SESSION['must_change_password']  = ((int)$usuario['password_changed'] === 0);

$conn->close();

if (!empty($_SESSION['must_change_password'])) {
  header("Location: changepassword.html");
} else {
  header("Location: SGI.php");
}
exit;

