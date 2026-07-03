<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/config.php';

$dni      = trim($_POST['dni']      ?? '');
$nombre   = trim($_POST['nombre']   ?? '');
$password = $_POST['password']      ?? '';
$rol      = strtolower(trim($_POST['rol'] ?? ''));

$roles_permitidos = ['alumno', 'familia'];

// Validaciones
if (!in_array($rol, $roles_permitidos, true)) {
    header("Location: index.html?registro=error&msg=" . urlencode("Rol inválido."));
    exit;
}

if ($dni === '' || $nombre === '') {
    header("Location: index.html?registro=error&msg=" . urlencode("Completá el DNI y el nombre."));
    exit;
}

if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/", $password)) {
    header("Location: index.html?registro=error&msg=" . urlencode("La contraseña no cumple los requisitos de seguridad."));
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo = db();

    // Verificar si el DNI ya existe
    $stmt = $pdo->prepare("SELECT dni FROM usuarios WHERE dni = ?");
    $stmt->execute([$dni]);

    if ($stmt->rowCount() > 0) {
        header("Location: index.html?registro=error&msg=" . urlencode("El DNI ya está registrado."));
        exit;
    }

    // Insertar nuevo usuario
    $stmt = $pdo->prepare("INSERT INTO usuarios (dni, nombre, password, rol, password_changed) VALUES (?, ?, ?, ?, 1)");
    $stmt->execute([$dni, $nombre, $hash, $rol]);

    // Registro exitoso: redirigir al login con aviso
    header("Location: index.html?registro=ok");
    exit;

} catch (Throwable $e) {
    header("Location: index.html?registro=error&msg=" . urlencode("Error al registrar: " . $e->getMessage()));
    exit;
}
?>