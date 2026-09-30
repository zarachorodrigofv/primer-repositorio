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

$pdo = db();
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    echo json_encode(['ok' => false, 'msg' => 'Solicitud inválida.']);
    exit;
}

$accion = (string)($payload['accion'] ?? '');
$alumnoDni = (int)($payload['alumno_dni'] ?? 0);
$cursoId = (int)($payload['curso_id'] ?? 0);
$yearId = currentYearId($pdo);
if (!$alumnoDni || !$cursoId || !$yearId) {
    echo json_encode(['ok' => false, 'msg' => 'Alumno, curso o ciclo inválido.']);
    exit;
}

$stmt = $pdo->prepare("SELECT 1 FROM asignado_alumno WHERE alumno_dni = ? AND curso_id = ? AND year_escolar_id = ? AND estado = 'activo' LIMIT 1");
$stmt->execute([$alumnoDni, $cursoId, $yearId]);
if (!$stmt->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'El alumno no pertenece a este curso en el ciclo actual.']);
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
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'Intensificación y recursado se habilitan desde segundo año.']);
    exit;
}

$pendientes = obtenerMateriasPendientes($pdo, $alumnoDni, $yearId);
$pendientes = array_values(array_filter($pendientes, static fn($materia) => !$materia['resuelta']));
$pendientesRecuperables = $pendientes;
$pendientesPorClave = [];
foreach ($pendientes as $materia) {
    $pendientesPorClave[$materia['year_id'] . ':' . $materia['materia_id']] = $materia;
}

try {
    if ($accion === 'deshacer_intento') {
        $materiaId = (int)($payload['materia_id'] ?? 0);
        $yearOrigenId = (int)($payload['year_origen_id'] ?? 0);
        $instancia = trim((string)($payload['instancia'] ?? ''));
        if (!$materiaId || !$yearOrigenId || $instancia === '') {
            throw new InvalidArgumentException('Faltan datos para deshacer la instancia.');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "SELECT s.id
             FROM alumno_materia_seguimiento s
             JOIN alumno_materia_intensificacion i ON i.seguimiento_id = s.id
             WHERE s.alumno_dni = ? AND s.materia_id = ? AND s.year_origen_id = ?
               AND s.year_seguimiento_id = ? AND s.clasificacion = 'intensificar'
               AND i.instancia = ?
             LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([$alumnoDni, $materiaId, $yearOrigenId, $yearId, $instancia]);
        $seguimientoId = $stmt->fetchColumn();
        if (!$seguimientoId) {
            throw new InvalidArgumentException('La instancia ya no existe o no se puede deshacer.');
        }

        $stmt = $pdo->prepare("DELETE FROM alumno_materia_intensificacion WHERE seguimiento_id = ? AND instancia = ?");
        $stmt->execute([(int)$seguimientoId, $instancia]);
        $pdo->commit();
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($accion === 'guardar_clasificacion') {
        $clasificaciones = $payload['clasificaciones'] ?? [];
        if (!is_array($clasificaciones)) {
            throw new InvalidArgumentException('La selección de materias es inválida.');
        }
        $clasificacionPorClave = [];
        foreach ($clasificaciones as $seleccion) {
            $materiaId = (int)($seleccion['materia_id'] ?? 0);
            $yearOrigenId = (int)($seleccion['year_origen_id'] ?? 0);
            $clasificacion = (string)($seleccion['clasificacion'] ?? '');
            $key = $yearOrigenId . ':' . $materiaId;
            if (!$materiaId || !$yearOrigenId || !isset($pendientesPorClave[$key])
                || !in_array($clasificacion, ['intensificar', 'recursar'], true)) {
                throw new InvalidArgumentException('Solo se pueden clasificar materias de segundo año en adelante.');
            }
            $clasificacionPorClave[$key] = $clasificacion;
        }
        if (count($clasificacionPorClave) !== count($pendientesRecuperables)) {
            throw new InvalidArgumentException('Elegí intensificar o recursar para cada materia elegible.');
        }
        if (count($clasificaciones) !== count($clasificacionPorClave)) {
            throw new InvalidArgumentException('Hay materias repetidas en la selección.');
        }
        if (count(array_filter($clasificacionPorClave, static fn($value) => $value === 'intensificar')) > 5) {
            throw new InvalidArgumentException('Cada alumno puede intensificar como máximo cinco materias por ciclo.');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id FROM asignado_alumno WHERE alumno_dni = ? AND curso_id = ? AND year_escolar_id = ? AND estado = 'activo' FOR UPDATE");
        $stmt->execute([$alumnoDni, $cursoId, $yearId]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('La inscripción del alumno cambió; recargá la página.');
        }

        $stmtCheck = $pdo->prepare(
            "SELECT s.clasificacion,
                    EXISTS(SELECT 1 FROM alumno_materia_intensificacion i WHERE i.seguimiento_id = s.id) AS tiene_intentos
             FROM alumno_materia_seguimiento s
             WHERE s.alumno_dni = ? AND s.materia_id = ? AND s.year_origen_id = ? AND s.year_seguimiento_id = ?
             LIMIT 1"
        );
        $stmtSave = $pdo->prepare(
            "INSERT INTO alumno_materia_seguimiento
                (alumno_dni, materia_id, year_origen_id, year_seguimiento_id, clasificacion, asignado_por)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                estado_recursada = IF(VALUES(clasificacion) = 'intensificar', 'pendiente_inscripcion', estado_recursada),
                curso_recursada_id = IF(VALUES(clasificacion) = 'intensificar', NULL, curso_recursada_id),
                year_recursada_id = IF(VALUES(clasificacion) = 'intensificar', NULL, year_recursada_id),
                recursada_actualizado_por = IF(VALUES(clasificacion) = 'intensificar', NULL, recursada_actualizado_por),
                clasificacion = VALUES(clasificacion),
                asignado_por = VALUES(asignado_por)"
        );

        foreach ($pendientesRecuperables as $materia) {
            $key = $materia['year_id'] . ':' . $materia['materia_id'];
            $clasificacion = $clasificacionPorClave[$key];
            $stmtCheck->execute([$alumnoDni, $materia['materia_id'], $materia['year_id'], $yearId]);
            $actual = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if ($actual && (int)$actual['tiene_intentos'] && $actual['clasificacion'] !== $clasificacion) {
                throw new InvalidArgumentException('No se puede cambiar la clasificación después de iniciar una intensificación o recursada.');
            }
            $stmtSave->execute([
                $alumnoDni,
                $materia['materia_id'],
                $materia['year_id'],
                $yearId,
                $clasificacion,
                (int)$_SESSION['dni'],
            ]);
        }
        $pdo->commit();
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($accion === 'registrar_intento') {
        $materiaId = (int)($payload['materia_id'] ?? 0);
        $yearOrigenId = (int)($payload['year_origen_id'] ?? 0);
        $key = $yearOrigenId . ':' . $materiaId;
        if (!isset($pendientesPorClave[$key])) {
            throw new InvalidArgumentException('La materia ya no está pendiente o no corresponde al alumno.');
        }
        $instancia = trim((string)($payload['instancia'] ?? ''));
        if ($instancia === '' || mb_strlen($instancia, 'UTF-8') > 80) {
            throw new InvalidArgumentException('Escribí una instancia de hasta 80 caracteres.');
        }
        $notaRaw = trim((string)($payload['nota'] ?? ''));
        $nota = $notaRaw === '' ? null : filter_var($notaRaw, FILTER_VALIDATE_FLOAT);
        if ($notaRaw !== '' && ($nota === false || $nota < 1 || $nota > 10)) {
            throw new InvalidArgumentException('La nota debe estar entre 1 y 10.');
        }
        $notaValorativa = strtoupper(trim((string)($payload['nota_valorativa'] ?? '')));
        if ($notaValorativa !== '' && !in_array($notaValorativa, ['TEP', 'TEA', 'TED'], true)) {
            throw new InvalidArgumentException('Estado valorativo inválido.');
        }
        if ($notaValorativa === '') $notaValorativa = null;
        if ($nota === null && $notaValorativa === null) {
            $estado = 'presentada';
        } elseif (in_array($notaValorativa, ['TEP', 'TED'], true)) {
            $estado = 'no_aprobada';
        } elseif ($notaValorativa === 'TEA' || ($nota !== null && $nota >= 7)) {
            $estado = 'aprobada';
        } else {
            $estado = 'no_aprobada';
        }
        if ($estado === 'aprobada') {
            $notaValorativa = 'TEA';
        } elseif ($estado === 'no_aprobada' && $notaValorativa === null) {
            $notaValorativa = 'TEP';
        }

                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
            "SELECT id FROM alumno_materia_seguimiento
             WHERE alumno_dni = ? AND materia_id = ? AND year_origen_id = ? AND year_seguimiento_id = ?
               AND clasificacion = 'intensificar'
                         LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([$alumnoDni, $materiaId, $yearOrigenId, $yearId]);
        $seguimientoId = $stmt->fetchColumn();
        if (!$seguimientoId) {
            throw new InvalidArgumentException('Primero clasificá la materia como intensificación.');
        }

        $stmt = $pdo->prepare(
            "INSERT INTO alumno_materia_intensificacion
                (seguimiento_id, instancia, estado, nota, nota_valorativa, observaciones, registrado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE estado = VALUES(estado), nota = VALUES(nota),
                 nota_valorativa = VALUES(nota_valorativa), observaciones = VALUES(observaciones),
                 registrado_por = VALUES(registrado_por), fecha = CURRENT_TIMESTAMP"
        );
        $stmt->execute([
            (int)$seguimientoId,
            $instancia,
            $estado,
            $nota,
            $notaValorativa,
            trim((string)($payload['observaciones'] ?? '')) ?: null,
            (int)$_SESSION['dni'],
        ]);
        $stmt = $pdo->prepare(
            "UPDATE alumno_materia_seguimiento
             SET estado_recursada = 'pendiente_inscripcion', curso_recursada_id = NULL,
                 year_recursada_id = NULL, recursada_actualizado_por = NULL
             WHERE id = ? AND clasificacion = 'intensificar'"
        );
        $stmt->execute([(int)$seguimientoId]);
        $pdo->commit();
        echo json_encode(['ok' => true, 'estado' => $estado]);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Acción desconocida.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($e instanceof InvalidArgumentException) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        exit;
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'No se pudo guardar el seguimiento: ' . $e->getMessage()]);
}
