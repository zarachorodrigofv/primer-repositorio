<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$dni = $_POST['dni'];
$password = $_POST['password'];
$rol = $_POST['rol'];
$roles_permitidos = ["Alumno", "Familia"];

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
  $stmt = $conn->prepare("INSERT INTO usuarios (dni, password, rol) VALUES (?, ?, ?)");
  $stmt->bind_param("sss", $dni, $hash, $rol);
  if ($stmt->execute()) {
    echo "Registro exitoso. Podés iniciar sesión.";
  } else {
    echo "Error al registrar.";
  }
}

$conn->close();
?>
