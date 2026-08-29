<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/config.php';
require __DIR__.'/auth.php';

requireLogin();
requireCsrf();
$rol = currentRole();
if (!in_array($rol, ['preceptor','admin','directivo','root'], true)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
    exit;
}

$familia = trim($_POST['familia_dni'] ?? '');
$alumno  = trim($_POST['alumno_dni'] ?? '');
$parentesco = trim($_POST['parentesco'] ?? 'Tutor');

if ($familia === '' || $alumno === '') {
    http_response_code(422);
    echo json_encode(['ok'=>false,'msg'=>'Faltan familia o alumno.']);
    exit;
}

$pdo = db();
$yearId = currentYearId($pdo);
if ($rol === 'preceptor' && (!$yearId || !preceptorTieneAlumno($pdo, (int)$_SESSION['dni'], (int)$alumno, $yearId))) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'msg'=>'No tenÃ©s acceso a este alumno.']);
    exit;
}
$st = $pdo->prepare("SELECT dni, rol FROM usuarios WHERE dni IN (?,?)");
$st->execute([$familia,$alumno]);
$usuarios = $st->fetchAll(PDO::FETCH_KEY_PAIR);

if (($usuarios[$familia] ?? '') !== 'familia') {
    http_response_code(422);
    echo json_encode(['ok'=>false,'msg'=>'La cuenta seleccionada no tiene rol familia.']);
    exit;
}
if (($usuarios[$alumno] ?? '') !== 'alumno') {
    http_response_code(422);
    echo json_encode(['ok'=>false,'msg'=>'La cuenta seleccionada no es un alumno administrado por la escuela.']);
    exit;
}

$check = $pdo->prepare("SELECT id FROM familia_alumno WHERE familia_dni=? AND alumno_dni=? LIMIT 1");
$check->execute([$familia,$alumno]);
if ($check->fetchColumn()) {
    echo json_encode(['ok'=>false,'msg'=>'Esa familia ya está vinculada a ese alumno.']);
    exit;
}

$ins = $pdo->prepare("INSERT INTO familia_alumno (familia_dni, alumno_dni, parentesco) VALUES (?,?,?)");
$ins->execute([$familia,$alumno,$parentesco]);

echo json_encode(['ok'=>true]);
