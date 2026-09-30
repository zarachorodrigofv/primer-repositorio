<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
require_once __DIR__ . '/boletin_helpers.php';

requireLogin();
requireCsrf();
$rol = currentRole();
if (!in_array($rol, ['preceptor', 'directivo', 'admin', 'root'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'No autorizado.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$alumnoDni = (int)($payload['alumno_dni'] ?? 0);
$cursoId = (int)($payload['curso_id'] ?? 0);
$materiaId = (int)($payload['materia_id'] ?? 0);
$yearOrigenId = (int)($payload['year_origen_id'] ?? 0);
$estado = (string)($payload['estado'] ?? '');
$pdo = db();
$yearId = currentYearId($pdo);

if (!$alumnoDni || !$cursoId || !$materiaId || !$yearOrigenId || !$yearId
    || !in_array($estado, ['pendiente_inscripcion', 'cursando'], true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'Datos de recursada inválidos.']);
    exit;
}
$stmt = $pdo->prepare(
    "SELECT cy.year, c.curso_year_id
     FROM curso c
     JOIN curso_year cy ON cy.id = c.curso_year_id
     WHERE c.id = ? LIMIT 1"
);
$stmt->execute([$cursoId]);
$cursoDestino = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare(
    "SELECT c.curso_year_id
     FROM asignado_alumno aa
     JOIN curso c ON c.id = aa.curso_id
     WHERE aa.alumno_dni = ? AND aa.curso_id = ? AND aa.year_escolar_id = ? AND aa.estado = 'activo'
     LIMIT 1"
);
$stmt->execute([$alumnoDni, $cursoId, $yearId]);
$cursoInscripto = $stmt->fetchColumn();
if (!$cursoDestino || !$cursoInscripto) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'El alumno no está inscripto en el curso seleccionado.']);
    exit;
}
if ($rol === 'preceptor' && !preceptorTieneCurso($pdo, (int)$_SESSION['dni'], $cursoId, $yearId)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'No tenés acceso a este curso.']);
    exit;
}

$materias = obtenerMateriasPendientes($pdo, $alumnoDni, $yearId);
$materia = null;
foreach ($materias as $fila) {
    if ((int)$fila['materia_id'] === $materiaId && (int)$fila['year_id'] === $yearOrigenId) {
        $materia = $fila;
        break;
    }
}
if (!$materia || $materia['clasificacion'] !== 'recursar' || empty($materia['seguimiento_id'])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'La materia no está clasificada para recursar en este ciclo.']);
    exit;
}
if ($materia['curso_year_id'] === null || (int)$cursoDestino['curso_year_id'] !== (int)$materia['curso_year_id']) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'El curso de recursada debe corresponder al mismo nivel de la materia.']);
    exit;
}
if (!empty($materia['resuelta_por']) && $materia['resuelta_por'] === 'recursada') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'La recursada ya figura aprobada en el boletín.']);
    exit;
}

$cursoRecursadaId = $estado === 'cursando' ? $cursoId : null;
$yearRecursadaId = $estado === 'cursando' ? $yearId : null;
$stmt = $pdo->prepare(
    "UPDATE alumno_materia_seguimiento
     SET estado_recursada = ?, curso_recursada_id = ?, year_recursada_id = ?, recursada_actualizado_por = ?
     WHERE id = ? AND alumno_dni = ? AND year_origen_id = ? AND year_seguimiento_id = ? AND clasificacion = 'recursar'"
);
$stmt->execute([
    $estado,
    $cursoRecursadaId,
    $yearRecursadaId,
    (int)$_SESSION['dni'],
    (int)$materia['seguimiento_id'],
    $alumnoDni,
    $yearOrigenId,
    $yearId,
]);

echo json_encode(['ok' => true, 'estado' => $estado]);