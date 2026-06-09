<?php
// api_actualizar_alumno.php
header('Content-Type: application/json; charset=utf-8');

require __DIR__.'/config.php';
require __DIR__.'/auth.php';

requireLogin();
$rol = strtolower(currentRole() ?? '');

if (!in_array($rol, ['preceptor','directivo'], true)) {
    echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
    exit;
}

$pdo = db();

// DNI viene de la tabla (corresponde a usuarios.dni y alumnos.alumno_dni)
$dni = isset($_POST['dni']) ? (int)$_POST['dni'] : 0;
$nombre    = trim($_POST['nombre']    ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$telefono  = trim($_POST['telefono']  ?? '');
$ausente   = trim($_POST['ausente']   ?? '');
$presente  = trim($_POST['presente']  ?? '');

if ($dni <= 0) {
    echo json_encode(['ok'=>false,'msg'=>'DNI inválido']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1) Actualizar nombre en usuarios
    if ($nombre !== '') {
        $sqlUser = "UPDATE usuarios SET nombre = :nom WHERE dni = :dni";
        $stUser = $pdo->prepare($sqlUser);
        $stUser->execute([
            ':nom' => $nombre,
            ':dni' => $dni
        ]);
    }

    // 2) Actualizar datos en alumnos (usa alumno_dni)
    $sqlAlu = "
        UPDATE alumnos
        SET direccion = :dir,
            telefono  = :tel,
            ausente   = :aus,
            presente  = :pre
        WHERE alumno_dni = :dni
    ";
    $stAlu = $pdo->prepare($sqlAlu);
    $stAlu->execute([
        ':dir' => $direccion,
        ':tel' => $telefono,
        ':aus' => $ausente,
        ':pre' => $presente,
        ':dni' => $dni
    ]);

    $pdo->commit();

    echo json_encode([
        'ok'   => true,
        'alumno' => [
            'dni'       => $dni,
            'nombre'    => $nombre,
            'direccion' => $direccion,
            'telefono'  => $telefono,
            'ausente'   => $ausente,
            'presente'  => $presente
        ]
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode([
        'ok'  => false,
        'msg' => 'Error al actualizar: '.$e->getMessage()
    ]);
}

