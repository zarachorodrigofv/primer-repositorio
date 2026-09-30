<?php
/**
 * Fuente única para boletines académicos.
 * La lógicaoffline se centraliza aquí para evitar que distintas pantallas
 * calculen boletines con consultas distintas.
 */
function obtenerIntensificacionesPorMateria(PDO $pdo, array $alumnosDni, int $yearOrigenId): array {
    $alumnosDni = array_values(array_unique(array_filter(array_map('intval', $alumnosDni))));
    if (!$alumnosDni || $yearOrigenId <= 0) return [];

    $placeholders = implode(',', array_fill(0, count($alumnosDni), '?'));
    $stmt = $pdo->prepare(
        "SELECT s.alumno_dni, s.materia_id, i.instancia, i.estado,
                i.nota, i.nota_valorativa, ys.`year` AS year_seguimiento
         FROM alumno_materia_seguimiento s
         JOIN alumno_materia_intensificacion i ON i.seguimiento_id = s.id
         JOIN year_escolar ys ON ys.id = s.year_seguimiento_id
         WHERE s.year_origen_id = ?
           AND s.alumno_dni IN ($placeholders)
         ORDER BY s.alumno_dni, s.materia_id,
                                    (i.estado = 'aprobada') DESC,
                                    (i.estado = 'no_aprobada') DESC, i.fecha DESC, i.id DESC"
    );
    $stmt->execute(array_merge([$yearOrigenId], $alumnosDni));

    $resultados = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $alumnoDni = (int)$fila['alumno_dni'];
        $materiaId = (int)$fila['materia_id'];
        if (isset($resultados[$alumnoDni][$materiaId])) continue;

        $valorativa = strtoupper(trim((string)($fila['nota_valorativa'] ?? '')));
        if ($fila['estado'] === 'aprobada') {
            $resultado = 'TEA';
        } elseif (in_array($valorativa, ['TEP', 'TED'], true)) {
            $resultado = $valorativa;
        } elseif ($fila['estado'] === 'no_aprobada') {
            $resultado = 'TEP';
        } else {
            $resultado = null;
        }

        $fila['resultado'] = $resultado;
        $resultados[$alumnoDni][$materiaId] = $fila;
    }

    return $resultados;
}

function obtenerBoletinAlumno(PDO $pdo, int $alumnoDni, int $yearId): array {
    $stmt = $pdo->prepare(
        "SELECT nd.materia_id, m.nombre AS materia,
                MAX(CASE WHEN nd.cuatrimestre='1' THEN nd.nota_valorativa END) AS c1_val,
                MAX(CASE WHEN nd.cuatrimestre='1' THEN nd.nota_numerica END) AS c1_num,
                MAX(CASE WHEN nd.cuatrimestre='2' THEN nd.nota_valorativa END) AS c2_val,
                MAX(CASE WHEN nd.cuatrimestre='2' THEN nd.nota_numerica END) AS c2_num,
                MAX(CASE WHEN nd.cuatrimestre='2' THEN nd.nota_final END) AS nota_final,
                MAX(CASE WHEN nd.cuatrimestre='2' THEN nd.observaciones END) AS observaciones
         FROM notas_detalle nd
         JOIN materias m ON m.id = nd.materia_id
         WHERE nd.alumno_dni = :alumno_dni
           AND nd.year_escolar_id = :year_id
         GROUP BY nd.materia_id, m.nombre
         ORDER BY m.nombre"
    );
    $stmt->execute([
        ':alumno_dni' => $alumnoDni,
        ':year_id'    => $yearId,
    ]);

    $boletin = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $intensificaciones = obtenerIntensificacionesPorMateria($pdo, [$alumnoDni], $yearId)[$alumnoDni] ?? [];
    foreach ($boletin as &$fila) {
        $resultado = $intensificaciones[(int)$fila['materia_id']]['resultado'] ?? null;
        $fila['instancia_intensificacion'] = $resultado;
    }
    unset($fila);

    return $boletin;
}

function nivelNumericoCurso(?string $grado): ?int {
    if ($grado === null || !preg_match('/\d+/', $grado, $coincidencia)) return null;
    return (int)$coincidencia[0];
}

/**
 * Materias pendientes del ciclo consultado y de ciclos anteriores, enriquecidas
 * con la clasificación e intentos del ciclo consultado.
 */
function obtenerMateriasPendientes(PDO $pdo, int $alumnoDni, int $yearActualId): array {
    $stmtYear = $pdo->prepare("SELECT `year` FROM year_escolar WHERE id = ? LIMIT 1");
    $stmtYear->execute([$yearActualId]);
    $yearActual = $stmtYear->fetchColumn();
    if ($yearActual === false) return [];

    $stmt = $pdo->prepare(
        "SELECT y.id AS year_id, y.`year` AS year_escolar,
                m.id AS materia_id, m.nombre AS materia,
                nd.cuatrimestre, nd.nota_final, nd.nota_numerica, nd.nota_valorativa
         FROM notas_detalle nd
         JOIN materias m ON m.id = nd.materia_id
         JOIN year_escolar y ON y.id = nd.year_escolar_id
         WHERE nd.alumno_dni = ?
           AND y.`year` <= ?
         ORDER BY y.`year` DESC, m.nombre, FIELD(nd.cuatrimestre, '2', 'F', '1')"
    );
    $stmt->execute([$alumnoDni, $yearActual]);

    $stCursosAlumno = $pdo->prepare(
        "SELECT aa.year_escolar_id, c.curso_year_id
         FROM asignado_alumno aa
         JOIN curso c ON c.id = aa.curso_id
         WHERE aa.alumno_dni = ?
         ORDER BY CASE WHEN aa.estado = 'activo' THEN 0 ELSE 1 END, aa.id DESC"
    );
    $stCursosAlumno->execute([$alumnoDni]);
    $cursoYearPorCiclo = [];
    foreach ($stCursosAlumno->fetchAll(PDO::FETCH_ASSOC) as $asignacion) {
        $cicloId = (int)$asignacion['year_escolar_id'];
        if (!isset($cursoYearPorCiclo[$cicloId])) {
            $cursoYearPorCiclo[$cicloId] = (int)$asignacion['curso_year_id'];
        }
    }

    $stMateriasNiveles = $pdo->prepare(
        "SELECT cm.year_escolar_id, cm.materia_id,
                COUNT(DISTINCT c.curso_year_id) AS cantidad_niveles,
                MIN(c.curso_year_id) AS curso_year_id
         FROM curso_materia cm
         JOIN curso c ON c.id = cm.curso_id
         WHERE cm.year_escolar_id <= ?
         GROUP BY cm.year_escolar_id, cm.materia_id"
    );
    $stMateriasNiveles->execute([$yearActualId]);
    $cursoYearMateriaCiclo = [];
    foreach ($stMateriasNiveles->fetchAll(PDO::FETCH_ASSOC) as $mapeo) {
        $cursoYearMateriaCiclo[(int)$mapeo['year_escolar_id'] . ':' . (int)$mapeo['materia_id']] =
            (int)$mapeo['cantidad_niveles'] === 1 ? (int)$mapeo['curso_year_id'] : null;
    }

    $nivelesCurso = [];
    foreach ($pdo->query('SELECT id, `year` FROM curso_year')->fetchAll(PDO::FETCH_ASSOC) as $nivel) {
        $nivelesCurso[(int)$nivel['id']] = (string)$nivel['year'];
    }

    $agrupadas = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $yearIdFila = (int)$row['year_id'];
        $materiaIdFila = (int)$row['materia_id'];
        $cursoYearId = $cursoYearPorCiclo[$yearIdFila]
            ?? $cursoYearMateriaCiclo[$yearIdFila . ':' . $materiaIdFila]
            ?? null;
        $grado = $cursoYearId !== null ? ($nivelesCurso[$cursoYearId] ?? null) : null;
        $nivel = nivelNumericoCurso($grado);
        $key = $yearIdFila . ':' . ($cursoYearId ?? 'sin_nivel') . ':' . $materiaIdFila;
        if (!isset($agrupadas[$key])) {
            $agrupadas[$key] = [
                'year_id' => $yearIdFila,
                'year' => (int)$row['year_escolar'],
                'materia_id' => $materiaIdFila,
                'materia' => $row['materia'],
                'curso_year_id' => $cursoYearId,
                'grado' => $grado,
                'nivel' => $nivel,
                'nota' => null,
                'nota_valorativa' => null,
                'evaluada' => false,
                'tiene_intentos_ciclo_actual' => false,
            ];
        }
        if ($agrupadas[$key]['evaluada']) continue;

        $finalRaw = trim((string)($row['nota_final'] ?? ''));
        $nota = $finalRaw !== '' && is_numeric($finalRaw)
            ? (float)$finalRaw
            : (is_numeric($row['nota_numerica'] ?? null) ? (float)$row['nota_numerica'] : null);
        $valorativa = strtoupper(trim((string)($row['nota_valorativa'] ?? '')));
        if (!in_array($valorativa, ['TEP', 'TEA', 'TED'], true) && in_array(strtoupper($finalRaw), ['TEP', 'TEA', 'TED'], true)) {
            $valorativa = strtoupper($finalRaw);
        }

        if ($nota !== null || in_array($valorativa, ['TEP', 'TEA', 'TED'], true)) {
            $agrupadas[$key]['nota'] = $nota;
            $agrupadas[$key]['nota_valorativa'] = $valorativa ?: null;
            $agrupadas[$key]['evaluada'] = true;
        }
    }

    $pendientes = [];
    foreach ($agrupadas as $key => $fila) {
        $estadoPendiente = in_array($fila['nota_valorativa'], ['TEP', 'TED'], true);
        $aprobada = !$estadoPendiente && (($fila['nota'] !== null && $fila['nota'] >= 7)
            || $fila['nota_valorativa'] === 'TEA');
        $noAprobada = $estadoPendiente || (($fila['nota'] !== null && $fila['nota'] < 7)
            && $fila['nota_valorativa'] !== 'TEA');
        if ($noAprobada && !$aprobada) {
            $fila['nota'] = $fila['nota'] === null
                ? ($fila['nota_valorativa'] ?? '—')
                : rtrim(rtrim(number_format($fila['nota'], 2, '.', ''), '0'), '.');
            $fila['intentos'] = [];
            $fila['resuelta'] = false;
            $fila['seguimiento_id'] = null;
            $fila['clasificacion'] = 'sin_clasificar';
            $fila['estado_recursada'] = 'pendiente_inscripcion';
            $fila['curso_recursada_id'] = null;
            $fila['year_recursada_id'] = null;
            $pendientes[$key] = $fila;
        }
    }

    if (!$pendientes) return [];

    $stmt = $pdo->prepare(
        "SELECT s.id AS seguimiento_id, s.materia_id, s.year_origen_id,
                s.year_seguimiento_id, ys.`year` AS year_seguimiento,
                s.clasificacion, s.estado_recursada, s.curso_recursada_id,
                s.year_recursada_id, i.instancia, i.estado, i.nota,
                i.nota_valorativa, i.observaciones, i.fecha
         FROM alumno_materia_seguimiento s
         JOIN year_escolar ys ON ys.id = s.year_seguimiento_id
         LEFT JOIN alumno_materia_intensificacion i ON i.seguimiento_id = s.id
         WHERE s.alumno_dni = ? AND ys.`year` <= ?
         ORDER BY ys.`year` DESC, FIELD(i.instancia, 'diciembre', 'febrero', 'marzo') DESC, i.fecha DESC"
    );
    $stmt->execute([$alumnoDni, $yearActual]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $coincidencias = array_keys(array_filter(
            $pendientes,
            static fn($materia) => $materia['year_id'] === (int)$row['year_origen_id']
                && $materia['materia_id'] === (int)$row['materia_id']
        ));
        if (count($coincidencias) !== 1) continue;
        $key = $coincidencias[0];

        if ($row['estado'] === 'aprobada') {
            $pendientes[$key]['resuelta'] = true;
            $pendientes[$key]['resuelta_por'] = 'intensificacion';
            $pendientes[$key]['resuelta_en'] = (int)$row['year_seguimiento'];
        }
        if ((int)$row['year_seguimiento_id'] === $yearActualId) {
            $pendientes[$key]['seguimiento_id'] = (int)$row['seguimiento_id'];
            $pendientes[$key]['clasificacion'] = $row['clasificacion'];
            $pendientes[$key]['estado_recursada'] = $row['estado_recursada'] ?? 'pendiente_inscripcion';
            $pendientes[$key]['curso_recursada_id'] = $row['curso_recursada_id'] !== null
                ? (int)$row['curso_recursada_id']
                : null;
            $pendientes[$key]['year_recursada_id'] = $row['year_recursada_id'] !== null
                ? (int)$row['year_recursada_id']
                : null;
            if ($row['instancia'] !== null) {
                $pendientes[$key]['tiene_intentos_ciclo_actual'] = true;
            }
        }
        if ($row['instancia'] !== null) {
            $pendientes[$key]['intentos'][] = [
                'year' => (int)$row['year_seguimiento'],
                'year_id' => (int)$row['year_seguimiento_id'],
                'instancia' => $row['instancia'],
                'estado' => $row['estado'],
                'nota' => $row['nota'],
                'nota_valorativa' => $row['nota_valorativa'],
                'observaciones' => $row['observaciones'],
            ];
        }
    }

    $stmt = $pdo->prepare(
        "SELECT nd.materia_id, y.id AS year_id, y.`year` AS year_escolar,
                nd.nota_final, nd.nota_numerica, nd.nota_valorativa
         FROM notas_detalle nd
         JOIN year_escolar y ON y.id = nd.year_escolar_id
         WHERE nd.alumno_dni = ?
           AND y.`year` > ? AND y.`year` <= ?
           AND nd.cuatrimestre = '2'
         ORDER BY y.`year` DESC"
    );
    $stmt->execute([$alumnoDni, (int)min(array_column($pendientes, 'year')), $yearActual]);
    $cierresAnuales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pendientes as &$fila) {
        foreach ($cierresAnuales as $cierre) {
            if ((int)$cierre['materia_id'] !== $fila['materia_id'] || (int)$cierre['year_escolar'] <= $fila['year']) {
                continue;
            }
            $cursoYearCierreId = $cursoYearPorCiclo[(int)$cierre['year_id']]
                ?? $cursoYearMateriaCiclo[(int)$cierre['year_id'] . ':' . (int)$cierre['materia_id']]
                ?? null;
            if ($fila['curso_year_id'] === null || $cursoYearCierreId === null || $cursoYearCierreId !== $fila['curso_year_id']) {
                continue;
            }
            $finalRaw = trim((string)($cierre['nota_final'] ?? ''));
            $notaCierre = $finalRaw !== '' && is_numeric($finalRaw)
                ? (float)$finalRaw
                : (is_numeric($cierre['nota_numerica'] ?? null) ? (float)$cierre['nota_numerica'] : null);
            $valorativaCierre = strtoupper(trim((string)($cierre['nota_valorativa'] ?? '')));
            $estadoPendiente = in_array($valorativaCierre, ['TEP', 'TED'], true);
            if (!$estadoPendiente && (($notaCierre !== null && $notaCierre >= 7) || $valorativaCierre === 'TEA')) {
                $fila['resuelta'] = true;
                $fila['resuelta_por'] = 'recursada';
                $fila['resuelta_en'] = (int)$cierre['year_escolar'];
                break;
            }
        }
    }
    unset($fila);

    return array_values($pendientes);
}

