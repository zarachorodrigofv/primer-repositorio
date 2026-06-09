<?php
// helpers_academico.php
require_once 'config.php';
require_once 'auth.php';

/**
 * Devuelve el id del año escolar actual (el último cargado en year_escolar).
 */
function currentYearEscolarId(PDO $pdo): int {
    $stmt = $pdo->query("SELECT id FROM year_escolar ORDER BY year DESC LIMIT 1");
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : 1;
}

/**
 * Mapea una fila de modalidad a una clave interna: cb / maderero / maquinaria.
 * Ciclo Básico viene con modalidad_id NULL en la tabla curso.
 */
function mapModalidadKey(?int $modId, ?string $modNombre): ?string {
    $nombre = mb_strtolower($modNombre ?? '', 'UTF-8');

    if ($modId === null || strpos($nombre, 'básico') !== false) {
        return 'cb'; // Ciclo Básico
    }

    if (strpos($nombre, 'madera') !== false || strpos($nombre, 'mader') !== false) {
        return 'maderero'; // Técnico en Madera / Maderero
    }

    if (strpos($nombre, 'mecán') !== false || strpos($nombre, 'maquin') !== false) {
        return 'maquinaria'; // Técnico Mecánico / Maquinaria
    }

    return null; // modalidad que no nos interesa acá
}

/**
 * Etiquetas lindas para cada modalidad.
 */
function labelModalidad(string $key): string {
    switch ($key) {
        case 'cb':         return 'Ciclo Básico';
        case 'maderero':   return 'Técnico en Maderero';
        case 'maquinaria': return 'Técnico en Maquinaria';
        default:           return $key;
    }
}

/**
 * Devuelve array de modalidades que puede ver el usuario:
 * [ 'cb' => true, 'maderero' => true, ... ]
 */
function modalidadesPermitidasPorRol(string $rol, int $dni, int $yearId): array {
    $pdo = db();
    $modalidades = [];

    switch ($rol) {
        case 'profesor':
            $sql = "
                SELECT DISTINCT c.modalidad_id, mo.nombre
                FROM docente_materia_curso dmc
                JOIN curso_materia cm   ON dmc.curso_materia_id = cm.id
                JOIN curso c            ON cm.curso_id = c.id
                LEFT JOIN modalidad mo  ON c.modalidad_id = mo.id
                WHERE dmc.maestro_dni = :dni
                  AND cm.year_escolar_id = :year
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':dni' => $dni, ':year' => $yearId]);
            break;

        case 'preceptor':
            $sql = "
                SELECT DISTINCT c.modalidad_id, mo.nombre
                FROM preceptor_curso pc
                JOIN curso c           ON pc.curso_id = c.id
                LEFT JOIN modalidad mo ON c.modalidad_id = mo.id
                WHERE pc.preceptor_dni = :dni
                  AND pc.year_escolar_id = :year
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':dni' => $dni, ':year' => $yearId]);
            break;

        case 'alumno':
            $sql = "
                SELECT DISTINCT c.modalidad_id, mo.nombre
                FROM asignado_alumno aa
                JOIN curso c           ON aa.curso_id = c.id
                LEFT JOIN modalidad mo ON c.modalidad_id = mo.id
                WHERE aa.alumno_dni = :dni
                  AND aa.year_escolar_id = :year
                  AND aa.estado = 'activo'
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':dni' => $dni, ':year' => $yearId]);
            break;

        case 'familia':
            $sql = "
                SELECT DISTINCT c.modalidad_id, mo.nombre
                FROM familia_alumno fa
                JOIN asignado_alumno aa ON aa.alumno_dni = fa.alumno_dni
                JOIN curso c           ON aa.curso_id   = c.id
                LEFT JOIN modalidad mo ON c.modalidad_id = mo.id
                WHERE fa.familia_dni = :dni
                  AND aa.year_escolar_id = :year
                  AND aa.estado = 'activo'
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':dni' => $dni, ':year' => $yearId]);
            break;

        default: // directivo / admin: ve todo
            $sql = "SELECT DISTINCT c.modalidad_id, mo.nombre FROM curso c LEFT JOIN modalidad mo ON c.modalidad_id = mo.id";
            $stmt = $pdo->query($sql);
            break;
    }

    foreach ($stmt as $row) {
        $key = mapModalidadKey(
            isset($row['modalidad_id']) ? (int)$row['modalidad_id'] : null,
            $row['nombre'] ?? null
        );
        if ($key !== null) {
            $modalidades[$key] = true;
        }
    }

    return $modalidades;
}

/**
 * Devuelve todos los cursos que puede ver el usuario en una modalidad concreta.
 * Retorna array de filas: id, year, division.
 */
function cursosPorRolYModalidad(string $rol, int $dni, int $yearId, string $modalidadKey): array {
    $pdo = db();

    // Mapeo modalidadKey -> condición sobre c.modalidad_id
    $whereMod = '';
    $params   = [':dni' => $dni, ':year' => $yearId];

    if ($modalidadKey === 'cb') {
        $whereMod = ' AND c.modalidad_id IS NULL ';
    } else {
        // acomodá ids según tus modalidades reales
        $modId = null;
        if ($modalidadKey === 'maderero') {
            $modId = 4; // Técnico en Madera
        } elseif ($modalidadKey === 'maquinaria') {
            $modId = 5; // Técnico Mecánico / Maquinaria
        }
        if ($modId === null) {
            return [];
        }
        $whereMod = ' AND c.modalidad_id = :modId ';
        $params[':modId'] = $modId;
    }

    switch ($rol) {
        case 'profesor':
            $sql = "
                SELECT DISTINCT c.id, cy.year, cd.division
                FROM docente_materia_curso dmc
                JOIN curso_materia cm ON dmc.curso_materia_id = cm.id
                JOIN curso c         ON cm.curso_id = c.id
                JOIN curso_year cy   ON c.curso_year_id = cy.id
                JOIN curso_division cd ON c.curso_division_id = cd.id
                WHERE dmc.maestro_dni = :dni
                  AND cm.year_escolar_id = :year
                  $whereMod
                ORDER BY cy.id, cd.id
            ";
            break;

        case 'preceptor':
            $sql = "
                SELECT DISTINCT c.id, cy.year, cd.division
                FROM preceptor_curso pc
                JOIN curso c       ON pc.curso_id = c.id
                JOIN curso_year cy ON c.curso_year_id = cy.id
                JOIN curso_division cd ON c.curso_division_id = cd.id
                WHERE pc.preceptor_dni = :dni
                  AND pc.year_escolar_id = :year
                  $whereMod
                ORDER BY cy.id, cd.id
            ";
            break;

        case 'alumno':
            $sql = "
                SELECT DISTINCT c.id, cy.year, cd.division
                FROM asignado_alumno aa
                JOIN curso c       ON aa.curso_id = c.id
                JOIN curso_year cy ON c.curso_year_id = cy.id
                JOIN curso_division cd ON c.curso_division_id = cd.id
                WHERE aa.alumno_dni = :dni
                  AND aa.year_escolar_id = :year
                  AND aa.estado = 'activo'
                  $whereMod
                ORDER BY cy.id, cd.id
            ";
            break;

        case 'familia':
            $sql = "
                SELECT DISTINCT c.id, cy.year, cd.division
                FROM familia_alumno fa
                JOIN asignado_alumno aa ON aa.alumno_dni = fa.alumno_dni
                JOIN curso c       ON aa.curso_id = c.id
                JOIN curso_year cy ON c.curso_year_id = cy.id
                JOIN curso_division cd ON c.curso_division_id = cd.id
                WHERE fa.familia_dni = :dni
                  AND aa.year_escolar_id = :year
                  AND aa.estado = 'activo'
                  $whereMod
                ORDER BY cy.id, cd.id
            ";
            break;

        default: // directivo
            $sql = "
                SELECT DISTINCT c.id, cy.year, cd.division
                FROM curso c
                JOIN curso_year cy ON c.curso_year_id = cy.id
                JOIN curso_division cd ON c.curso_division_id = cd.id
                WHERE 1=1
                  $whereMod
                ORDER BY cy.id, cd.id
            ";
            // para directivo no necesitamos :dni ni :year, pero los params extra son ignorados si no se usan
            unset($params[':dni'], $params[':year']);
            break;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Verifica si el usuario tiene acceso a un curso concreto.
 */
function usuarioTieneAccesoACurso(string $rol, int $dni, int $cursoId, int $yearId): bool {
    $pdo = db();
    $params = [':dni' => $dni, ':curso' => $cursoId, ':year' => $yearId];

    switch ($rol) {
        case 'profesor':
            $sql = "
                SELECT 1
                FROM docente_materia_curso dmc
                JOIN curso_materia cm ON dmc.curso_materia_id = cm.id
                WHERE dmc.maestro_dni = :dni
                  AND cm.curso_id = :curso
                  AND cm.year_escolar_id = :year
                LIMIT 1
            ";
            break;

        case 'preceptor':
            $sql = "
                SELECT 1
                FROM preceptor_curso
                WHERE preceptor_dni = :dni
                  AND curso_id = :curso
                  AND year_escolar_id = :year
                LIMIT 1
            ";
            break;

        case 'alumno':
            $sql = "
                SELECT 1
                FROM asignado_alumno
                WHERE alumno_dni = :dni
                  AND curso_id = :curso
                  AND year_escolar_id = :year
                  AND estado = 'activo'
                LIMIT 1
            ";
            break;

        case 'familia':
            $sql = "
                SELECT 1
                FROM familia_alumno fa
                JOIN asignado_alumno aa ON aa.alumno_dni = fa.alumno_dni
                WHERE fa.familia_dni = :dni
                  AND aa.curso_id = :curso
                  AND aa.year_escolar_id = :year
                  AND aa.estado = 'activo'
                LIMIT 1
            ";
            break;

        default: // directivo
            return true;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

/**
 * Materias que el usuario puede ver en un curso.
 * Profesor: solo las suyas. Otros: todas las materias del curso.
 */
function materiasDeCursoParaUsuario(string $rol, int $dni, int $cursoId, int $yearId): array {
    $pdo = db();
    $params = [':curso' => $cursoId, ':year' => $yearId];

    if ($rol === 'profesor') {
        $sql = "
            SELECT DISTINCT m.id, m.nombre
            FROM docente_materia_curso dmc
            JOIN curso_materia cm ON dmc.curso_materia_id = cm.id
            JOIN materias m       ON cm.materia_id        = m.id
            WHERE dmc.maestro_dni = :dni
              AND cm.curso_id = :curso
              AND cm.year_escolar_id = :year
            ORDER BY m.nombre
        ";
        $params[':dni'] = $dni;
    } else {
        $sql = "
            SELECT DISTINCT m.id, m.nombre
            FROM curso_materia cm
            JOIN materias m ON cm.materia_id = m.id
            WHERE cm.curso_id = :curso
              AND cm.year_escolar_id = :year
            ORDER BY m.nombre
        ";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Devuelve información básica de un curso (para encabezados).
 */
function infoCurso(int $cursoId): ?array {
    $pdo = db();
    $sql = "
        SELECT c.id, cy.year, cd.division, c.modalidad_id, mo.nombre AS modalidad_nombre
        FROM curso c
        JOIN curso_year cy ON c.curso_year_id = cy.id
        JOIN curso_division cd ON c.curso_division_id = cd.id
        LEFT JOIN modalidad mo ON c.modalidad_id = mo.id
        WHERE c.id = :id
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $cursoId]);
    $row = $stmt->fetch();
    return $row ?: null;
}
