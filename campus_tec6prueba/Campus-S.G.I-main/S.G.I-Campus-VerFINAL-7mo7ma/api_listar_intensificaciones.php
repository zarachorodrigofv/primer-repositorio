<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
require_once __DIR__ . '/boletin_helpers.php';

requireLogin();
$rol = currentRole();
if (!in_array($rol, ['preceptor', 'directivo', 'admin', 'root'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'No autorizado.']);
    exit;
}

$pdo = db();
$cursoId = (int)($_GET['curso_id'] ?? 0);
$yearId = currentYearId($pdo);
if ($cursoId <= 0 || $yearId <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Curso o ciclo lectivo inválido.']);
    exit;
}

if ($rol === 'preceptor' && !preceptorTieneCurso($pdo, (int)$_SESSION['dni'], $cursoId, $yearId)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'No tenés acceso a este curso.']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT cy.year
     FROM curso c
     JOIN curso_year cy ON cy.id = c.curso_year_id
     WHERE c.id = ? LIMIT 1"
);
$stmt->execute([$cursoId]);
$nivelCurso = nivelNumericoCurso($stmt->fetchColumn() ?: null);
if ($nivelCurso === null || $nivelCurso < 2) {
    echo json_encode(['ok' => true, 'habilitado' => false, 'alumnos' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT aa.alumno_dni, u.nombre
     FROM asignado_alumno aa
     JOIN usuarios u ON u.dni = aa.alumno_dni
     WHERE aa.curso_id = ? AND aa.year_escolar_id = ? AND aa.estado = 'activo'
     ORDER BY u.nombre"
);
$stmt->execute([$cursoId, $yearId]);
$alumnos = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $alumno) {
    $materias = obtenerMateriasPendientes($pdo, (int)$alumno['alumno_dni'], $yearId);
    $materias = array_values(array_filter(
        $materias,
        static fn($materia) => !$materia['resuelta'] || !empty($materia['intentos'])
    ));
    if (!$materias) continue;

    foreach ($materias as &$materia) {
        $materia['habilita_recuperacion'] = true;
        $materia['year_origen'] = (int)$materia['year'];
        $materia['nota_origen'] = $materia['nota'];
    }
    unset($materia);

    $alumnos[] = [
        'dni' => (int)$alumno['alumno_dni'],
        'nombre' => $alumno['nombre'],
        'materias' => $materias,
    ];
}

echo json_encode(['ok' => true, 'habilitado' => true, 'alumnos' => $alumnos], JSON_UNESCAPED_UNICODE);
