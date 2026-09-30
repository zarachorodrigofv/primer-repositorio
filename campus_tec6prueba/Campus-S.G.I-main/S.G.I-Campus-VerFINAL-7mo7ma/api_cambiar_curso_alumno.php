<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

requireLogin();
requireCsrf();

$rol = strtolower(currentRole() ?? '');
if (!in_array($rol, ['preceptor', 'directivo', 'admin', 'root'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'No autorizado para cambiar el curso.']);
    exit;
}

$pdo = db();
$alumnoDni = trim((string)($_POST['dni'] ?? ''));
$cursoNuevo = (int)($_POST['curso_id'] ?? 0);

if (!ctype_digit($alumnoDni) || $cursoNuevo <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Alumno o curso destino inválido.']);
    exit;
}

$yearId = currentYearId($pdo);
if (!$yearId) {
    echo json_encode(['ok' => false, 'msg' => 'No hay ciclo lectivo actual configurado.']);
    exit;
}

$stmt = $pdo->prepare("SELECT curso_id FROM asignado_alumno WHERE alumno_dni = ? AND year_escolar_id = ? AND estado = 'activo' LIMIT 1");
$stmt->execute([$alumnoDni, $yearId]);
$cursoActual = $stmt->fetchColumn();
if ($cursoActual === false) {
    echo json_encode(['ok' => false, 'msg' => 'El alumno no tiene una inscripción activa en el ciclo actual.']);
    exit;
}
$cursoActual = (int)$cursoActual;

$stmt = $pdo->prepare('SELECT 1 FROM curso WHERE id = ? LIMIT 1');
$stmt->execute([$cursoNuevo]);
if (!$stmt->fetchColumn()) {
    echo json_encode(['ok' => false, 'msg' => 'El curso destino no existe.']);
    exit;
}

if ($rol === 'preceptor') {
    $preceptorDni = (int)($_SESSION['dni'] ?? 0);
    if (!preceptorTieneAlumno($pdo, $preceptorDni, (int)$alumnoDni, $yearId, $cursoActual)
        || !preceptorTieneCurso($pdo, $preceptorDni, $cursoNuevo, $yearId)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'msg' => 'No tenés acceso al alumno o al curso destino.']);
        exit;
    }
}

if ($cursoNuevo !== $cursoActual) {
    $stmt = $pdo->prepare("SELECT 1 FROM asignado_alumno WHERE alumno_dni = ? AND year_escolar_id = ? AND curso_id = ? LIMIT 1");
    $stmt->execute([$alumnoDni, $yearId, $cursoNuevo]);
    if ($stmt->fetchColumn()) {
        echo json_encode(['ok' => false, 'msg' => 'El alumno ya tiene una inscripción en el curso destino para este ciclo.']);
        exit;
    }
}

$stmt = $pdo->prepare('SELECT id, turno FROM curso_turno WHERE curso_id = ?');
$stmt->execute([$cursoNuevo]);
$turnosDisponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
$turnoIdsPorNombre = [];
foreach ($turnosDisponibles as $turnoDisponible) {
    $turnoIdsPorNombre[$turnoDisponible['turno']] = (int)$turnoDisponible['id'];
}
$turnoIds = [];
foreach (['mañana', 'tarde', 'vespertino'] as $turno) {
    if (!isset($turnoIdsPorNombre[$turno])) {
        echo json_encode(['ok' => false, 'msg' => 'Faltan turnos configurados para el curso destino. Ejecutá la migración de turnos.']);
        exit;
    }
    $turnoIds[] = $turnoIdsPorNombre[$turno];
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE asignado_alumno SET curso_id = ? WHERE alumno_dni = ? AND year_escolar_id = ? AND curso_id = ? AND estado = 'activo'");
    $stmt->execute([$cursoNuevo, $alumnoDni, $yearId, $cursoActual]);
    if ($stmt->rowCount() !== 1 && $cursoNuevo !== $cursoActual) {
        throw new RuntimeException('No se pudo actualizar la inscripción del alumno.');
    }

    $stmt = $pdo->prepare('DELETE FROM asignado_alumno_turno WHERE alumno_dni = ? AND year_escolar_id = ? AND curso_id = ?');
    $stmt->execute([$alumnoDni, $yearId, $cursoActual]);

    $stmt = $pdo->prepare('INSERT INTO asignado_alumno_turno (alumno_dni, curso_id, year_escolar_id, turno_id) VALUES (?, ?, ?, ?)');
    foreach ($turnoIds as $turnoId) {
        $stmt->execute([$alumnoDni, $cursoNuevo, $yearId, $turnoId]);
    }

    $pdo->commit();
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error al cambiar el curso: ' . $e->getMessage()]);
}