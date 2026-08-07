<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/config.php';
require __DIR__.'/auth.php';

requireLogin();
$rolSesion = strtolower(currentRole() ?? '');
if ($rolSesion !== 'directivo') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
    exit;
}

$rol = strtolower(trim($_POST['rol'] ?? ''));
$allowedRoles = ['profesor', 'preceptor'];
if (!in_array($rol, $allowedRoles, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Rol inválido']);
    exit;
}

$nombre = trim($_POST['nombre'] ?? '');
$dniStr = trim($_POST['dni'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($nombre === '' || $dniStr === '' || !ctype_digit($dniStr)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'Nombre y DNI numérico son obligatorios']);
    exit;
}

$dni = (int)$dniStr;
if ($dni <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'DNI inválido']);
    exit;
}

if ($password === '') {
    $password = $rol === 'profesor' ? 'Pro1234' : 'Pre1234';
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$pdo = db();

try {
    $sql = "INSERT INTO usuarios (dni, nombre, password, rol, password_changed)
            VALUES (:dni, :nombre, :password, :rol, 0)
            ON DUPLICATE KEY UPDATE nombre = :nombre, rol = :rol";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':dni'      => $dni,
        ':nombre'   => $nombre,
        ':password' => $hash,
        ':rol'      => $rol
    ]);

    echo json_encode([
        'ok' => true,
        'usuario' => [
            'dni'    => (string)$dni,
            'nombre' => $nombre,
            'rol'    => $rol
        ],
        'temp_password' => $password
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error al guardar: ' . $e->getMessage()]);
}
