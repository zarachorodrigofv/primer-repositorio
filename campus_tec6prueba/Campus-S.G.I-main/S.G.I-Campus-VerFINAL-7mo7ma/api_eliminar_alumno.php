<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/config.php';
require __DIR__.'/auth.php';

requireLogin();
requireCsrf();
$rol = strtolower(currentRole());

if (!in_array($rol, ['preceptor','directivo','admin','root'], true)) {
    echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
    exit;
}

$pdo = db();

// DNI recibido (corresponde a alumno_dni)
$alumno_dni = isset($_POST['dni']) ? (int)$_POST['dni'] : 0;
$curso_id   = isset($_POST['curso_id']) ? (int)$_POST['curso_id'] : 0;

if ($alumno_dni <= 0) {
    echo json_encode(['ok'=>false,'msg'=>'DNI inválido']);
    exit;
}

// Obtener año lectivo activo
$yearRow = $pdo->query("SELECT id FROM year_escolar ORDER BY `year` DESC LIMIT 1")->fetch();
$year_id = (int)($yearRow['id'] ?? 0);

if (!$year_id) {
    echo json_encode(['ok'=>false,'msg'=>'No hay año lectivo activo']);
    exit;
}

if ($rol === 'preceptor') {
    if ($curso_id <= 0 || !preceptorTieneAlumno($pdo, (int)$_SESSION['dni'], $alumno_dni, $year_id, $curso_id)) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'msg'=>'No tenÃ©s acceso a este alumno o curso']);
        exit;
    }
}

try {
    $pdo->beginTransaction();

    // 1) Borrar asignación del curso
    $sql = "DELETE FROM asignado_alumno 
            WHERE alumno_dni = :dni AND year_escolar_id = :year";

    $params = [
        ':dni'  => $alumno_dni,
        ':year' => $year_id
    ];

    if ($curso_id > 0) {
        $sql .= " AND curso_id = :curso";
        $params[':curso'] = $curso_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // 2) Borrar datos de alumno (tabla alumnos)
    $stmt2 = $pdo->prepare("DELETE FROM alumnos WHERE alumno_dni = :dni");
    $stmt2->execute([':dni' => $alumno_dni]);

    // 3) (Opcional) Si querés también borrar notas:
    // $pdo->prepare("DELETE FROM notas_detalle WHERE alumno_dni = :dni")
    //     ->execute([':dni' => $alumno_dni]);

    $pdo->commit();
    echo json_encode(['ok'=>true]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['ok'=>false,'msg'=>'Error al eliminar: '.$e->getMessage()]);
}
