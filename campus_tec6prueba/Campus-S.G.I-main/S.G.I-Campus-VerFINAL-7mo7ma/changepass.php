<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verifica que el usuario esté logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: index.html");
    exit;
}

require_once "config.php";
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = $_SESSION['dni'] ?? null; // Guardamos el dni en sesión al hacer login
    $currentPassword = $_POST['currentPassword'];
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];

    if ($newPassword !== $confirmPassword) {
        echo json_encode(['status' => 'error', 'message' => 'Las contraseñas nuevas no coinciden.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE dni = ?");
    $stmt->execute([$dni]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        if (password_verify($currentPassword, $usuario['password'])) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE usuarios SET password = ?, password_changed = 1 WHERE dni = ?");
            $update->execute([$hashedPassword, $dni]);

            $_SESSION['must_change_password'] = false;
            echo json_encode(['status' => 'success', 'message' => 'Contraseña cambiada correctamente.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Contraseña actual incorrecta.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Usuario no encontrado.']);
    }
}
?>
