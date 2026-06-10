<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$dni = trim($_POST['dni'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$password = $_POST['password'] ?? '';
$rol = strtolower(trim($_POST['rol'] ?? ''));
$roles_permitidos = ['alumno', 'familia'];

if (!in_array($rol, $roles_permitidos, true)) {
  echo "Rol inválido.";
  exit;
}

if ($dni === '' || $nombre === '') {
  echo "Completá el DNI y el nombre.";
  exit;
}

if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/", $password)) {
  echo "La contraseña no cumple los requisitos.";
  exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$conn = new mysqli("localhost", "root", "", "campus");
if ($conn->connect_error) {
  die("Fallo de conexión: " . $conn->connect_error);
}

$sql = "SELECT dni FROM usuarios WHERE dni = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $dni);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
  echo "El DNI ya está registrado.";
} else {
  $stmt = $conn->prepare("INSERT INTO usuarios (dni, nombre, password, rol, password_changed) VALUES (?, ?, ?, ?, 1)");
  $stmt->bind_param("ssss", $dni, $nombre, $hash, $rol);
  if ($stmt->execute()) {
    echo "Registro exitoso. Podés iniciar sesión.";
  } else {
    echo "Error al registrar.";
  }
}

$conn->close();
?>
