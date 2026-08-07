<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/config.php';
require __DIR__.'/auth.php';

requireLogin();
$rol = strtolower(currentRole() ?? '');
if ($rol !== 'directivo') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
    exit;
}

$dni = isset($_POST['dni']) ? (int)$_POST['dni'] : 0;
if ($dni <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'DNI inválido']);
    exit;
}

function generarContrasenaTemporal(int $longitud = 8): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $max = strlen($chars) - 1;
    $password = '';
    for ($i = 0; $i < $longitud; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    return $password;
}

$password = generarContrasenaTemporal(10);
$hash = password_hash($password, PASSWORD_DEFAULT);

$pdo = db();
$stmt = $pdo->prepare("UPDATE usuarios SET password = :password, password_changed = 0 WHERE dni = :dni");
$stmt->execute([':password' => $hash, ':dni' => $dni]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'msg' => 'Usuario no encontrado']);
    exit;
}

echo json_encode(['ok' => true, 'dni' => (string)$dni, 'temp_password' => $password]);
