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
$stmtYear = $pdo->prepare('SELECT `year` FROM year_escolar WHERE id = ? LIMIT 1');
$stmtYear->execute([$yearId]);
$yearActual = (int)$stmtYear->fetchColumn();
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
$stmtNota = $pdo->prepare(
    "SELECT nota_final, nota_numerica, nota_valorativa
     FROM notas_detalle
     WHERE alumno_dni = ? AND materia_id = ? AND year_escolar_id = ? AND cuatrimestre = '2'
     LIMIT 1"
);
$stmtCurso = $pdo->prepare(
    "SELECT CONCAT(cy.year, ' ', cd.division,
                   IF(m.nombre IS NULL, '', CONCAT(' - ', m.nombre)))
     FROM curso c
     JOIN curso_year cy ON cy.id = c.curso_year_id
     JOIN curso_division cd ON cd.id = c.curso_division_id
     LEFT JOIN modalidad m ON m.id = c.modalidad_id
     WHERE c.id = ? LIMIT 1"
);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $alumno) {
    $materias = obtenerMateriasPendientes($pdo, (int)$alumno['alumno_dni'], $yearId);
    $materiasRecursar = [];
    foreach ($materias as $materia) {
        if ($materia['clasificacion'] !== 'recursar' || !$materia['seguimiento_id']) continue;

        $estado = $materia['estado_recursada'] ?? 'pendiente_inscripcion';
        $cursoNombre = null;
        if (!empty($materia['curso_recursada_id'])) {
            $stmtCurso->execute([(int)$materia['curso_recursada_id']]);
            $cursoNombre = $stmtCurso->fetchColumn() ?: null;
        }
        if ($estado === 'cursando' && !empty($materia['year_recursada_id'])) {
            $stmtNota->execute([
                (int)$alumno['alumno_dni'],
                (int)$materia['materia_id'],
                (int)$materia['year_recursada_id'],
            ]);
            $nota = $stmtNota->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($nota) {
                $raw = strtoupper(trim((string)($nota['nota_final'] ?? '')));
                $valorativa = strtoupper(trim((string)($nota['nota_valorativa'] ?? '')));
                $notaNumerica = $raw !== '' && is_numeric($raw)
                    ? (float)$raw
                    : (is_numeric($nota['nota_numerica'] ?? null) ? (float)$nota['nota_numerica'] : null);
                if ($notaNumerica !== null && $notaNumerica >= 7 || $raw === 'TEA' || $valorativa === 'TEA') {
                    $estado = 'aprobada';
                } elseif ($notaNumerica !== null && $notaNumerica < 7 || in_array($raw, ['TEP', 'TED'], true) || in_array($valorativa, ['TEP', 'TED'], true)) {
                    $estado = 'no_aprobada';
                }
            }
        }
        if (!empty($materia['resuelta_por']) && $materia['resuelta_por'] === 'recursada') {
            $estado = 'aprobada';
        }

        $materiasRecursar[] = [
            'materia_id' => (int)$materia['materia_id'],
            'materia' => $materia['materia'],
            'year_origen_id' => (int)$materia['year_id'],
            'year_origen' => (int)$materia['year'],
            'grado' => $materia['grado'],
            'nota_origen' => $materia['nota'],
            'seguimiento_id' => (int)$materia['seguimiento_id'],
            'estado' => $estado,
            'estado_guardado' => $materia['estado_recursada'] ?? 'pendiente_inscripcion',
            'curso_id' => $materia['curso_recursada_id'] !== null ? (int)$materia['curso_recursada_id'] : null,
            'curso' => $cursoNombre,
            'year_recursada_id' => $materia['year_recursada_id'] !== null ? (int)$materia['year_recursada_id'] : null,
            'year_recursada' => $materia['year_recursada_id'] !== null ? $yearActual : null,
            'resuelta' => !empty($materia['resuelta_por']) && $materia['resuelta_por'] === 'recursada',
        ];
    }
    if ($materiasRecursar) {
        $alumnos[] = [
            'dni' => (int)$alumno['alumno_dni'],
            'nombre' => $alumno['nombre'],
            'materias' => $materiasRecursar,
        ];
    }
}

echo json_encode(['ok' => true, 'habilitado' => true, 'alumnos' => $alumnos], JSON_UNESCAPED_UNICODE);