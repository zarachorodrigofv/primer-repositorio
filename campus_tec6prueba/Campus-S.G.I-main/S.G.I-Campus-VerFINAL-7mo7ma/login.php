<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$dni      = trim($_POST['dni']      ?? '');
$password = (string)($_POST['password'] ?? '');

if ($dni === '' || $password === '') {
    header("Location: index.html?login=error&msg=" . urlencode("Completá DNI y contraseña."));
    exit;
}

// Cuenta raíz institucional: DNI "root" y contraseña "root".
// Se resuelve aquí para no depender de un registro adicional en la BD.
if ($dni === 'root' && hash_equals('root', $password)) {
    session_regenerate_id(true);
    $_SESSION['dni'] = 'root';
    $_SESSION['usuario'] = 'Root';
    $_SESSION['rol'] = 'root';
    $_SESSION['must_change_password'] = false;
    header("Location: panel_root.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "campus");
if ($conn->connect_error) die("Conexión fallida: " . $conn->connect_error);

// DNI ahora es VARCHAR — usar "s" en bind_param
$stmt = $conn->prepare("SELECT dni, nombre, password, rol, password_changed FROM usuarios WHERE dni = ?");
$stmt->bind_param("s", $dni);  // ← "s" en vez de "i" para soportar "root"
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close(); $conn->close();
    header("Location: index.html?login=error&msg=" . urlencode("DNI no encontrado."));
    exit;
}

$usuario = $result->fetch_assoc();
$stmt->close();

$hash = $usuario['password'];
$ok   = false;

if (strlen($hash) >= 60 && strncmp($hash, '$2y$', 4) === 0) {
    $ok = password_verify($password, $hash);
} else {
    if (hash_equals($hash, $password)) {
        $ok = true;
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE usuarios SET password = ? WHERE dni = ?");
        $upd->bind_param("ss", $newHash, $usuario['dni']);
        $upd->execute(); $upd->close();
    }
}

if (!$ok) {
    $conn->close();
    header("Location: index.html?login=error&msg=" . urlencode("Contraseña incorrecta."));
    exit;
}

$conn->close();

session_regenerate_id(true);
$_SESSION['dni']                 = $usuario['dni'];   // string, no int
$_SESSION['usuario']             = $usuario['nombre'];
$_SESSION['rol']                 = strtolower(trim($usuario['rol']));
$_SESSION['must_change_password'] = ((int)$usuario['password_changed'] === 0);

// La figura de alumno no tiene acceso como cuenta independiente.
if ($_SESSION['rol'] === 'alumno') {
    session_unset();
    session_destroy();
    header("Location: index.html?login=error&msg=" . urlencode("Las cuentas de alumno son administradas por la escuela y no pueden iniciar sesión."));
    exit;
}

if (!empty($_SESSION['must_change_password'])) {
    header("Location: changepassword.html");
} elseif ($_SESSION['rol'] === 'root') {
    header("Location: panel_root.php");
} else {
    header("Location: SGI.php");
}
exit;
