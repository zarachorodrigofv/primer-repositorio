<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verifica que el usuario esté logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: index.html");
    exit;
}

require_once "conexion.php"; // Incluye conexión a la base de datos

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = $_SESSION['dni'] ?? null; // Guardamos el dni en sesión al hacer login
    $currentPassword = $_POST['currentPassword'];
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];

    if ($newPassword !== $confirmPassword) {
        echo json_encode(['status' => 'error', 'message' => 'Las contraseñas nuevas no coinciden.']);
        exit;
    }

    // Buscamos el usuario por dni
    $stmt = $conn->prepare("SELECT password FROM usuarios WHERE dni = ?");
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $usuario = $result->fetch_assoc();
        if (password_verify($currentPassword, $usuario['password'])) {
            // Hasheamos la nueva contraseña
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            $update = $conn->prepare("UPDATE usuarios SET password = ? WHERE dni = ?");
            $update->bind_param("ss", $hashedPassword, $dni);
            if ($update->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Contraseña cambiada correctamente.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error al actualizar la contraseña.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Contraseña actual incorrecta.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Usuario no encontrado.']);
    }

    $stmt->close();
    $conn->close();
}
?>
