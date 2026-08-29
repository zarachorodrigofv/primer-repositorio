<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/config.php';
require __DIR__.'/auth.php';

requireLogin();
requireCsrf();
$rol = strtolower(currentRole() ?? '');
if (!in_array($rol, ['root','admin','directivo','jefe_preceptores'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
    exit;
}

$pdo = db();
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

if ($rol === 'jefe_preceptores') {
    $chk = $pdo->prepare("SELECT rol FROM usuarios WHERE dni = :dni LIMIT 1");
    $chk->execute([':dni'=>$dni]);
    if ($chk->fetchColumn() !== 'preceptor') {
        http_response_code(403);
        echo json_encode(['ok'=>false,'msg'=>'El jefe de preceptores solo puede resetear cuentas de preceptores.']);
        exit;
    }
}

$password = generarContrasenaTemporal(10);
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE usuarios SET password = :password, password_changed = 0 WHERE dni = :dni");
$stmt->execute([':password' => $hash, ':dni' => $dni]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'msg' => 'Usuario no encontrado']);
    exit;
}

echo json_encode(['ok' => true, 'dni' => (string)$dni, 'temp_password' => $password]);
