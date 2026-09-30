<?php
// Suprimir notices para que el JSON no se rompa con warnings de PHP
error_reporting(0);
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
requireAnyRole(ROLES_ASISTENCIA);
header('Content-Type: application/json');

// Fecha: parámetro GET o hoy
$fechaParam = $_GET['fecha'] ?? '';
$fecha = ($fechaParam && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaParam))
    ? $fechaParam
    : date('Y-m-d');

$pdo = db();
$rol = currentRole();
$yearId = currentYearId($pdo);
$filtroPreceptor = '';
$paramsFecha = [$fecha];
if ($rol === 'preceptor') {
    $filtroPreceptor = ' AND EXISTS (SELECT 1 FROM asignado_alumno aa2 JOIN preceptor_curso pc ON pc.curso_id=aa2.curso_id AND pc.year_escolar_id=aa2.year_escolar_id WHERE aa2.alumno_dni=a.alumno_dni AND aa2.year_escolar_id=? AND aa2.estado=\'activo\' AND pc.preceptor_dni=?)';
    $paramsFecha[] = $yearId;
    $paramsFecha[] = (int)$_SESSION['dni'];
}

$stmtTotal = $pdo->prepare(
    "SELECT
        COALESCE(SUM(dia.presentes), 0) AS presentes,
        COALESCE(SUM(CASE WHEN dia.tiene_presente=1 AND dia.ausentes>0 THEN 0.5 ELSE dia.ausentes END), 0) AS ausentes,
        COALESCE(SUM(dia.tardanzas), 0) AS tardanzas,
        COALESCE(SUM(dia.justificados), 0) AS justificados,
        COALESCE(SUM(dia.turnos_registrados), 0) AS total
     FROM (
        SELECT a.alumno_dni, a.fecha,
            SUM(a.estado = 'presente') AS presentes,
            SUM(a.estado = 'ausente') AS ausentes,
            SUM(a.estado = 'tarde') AS tardanzas,
            SUM(a.estado = 'justificado') AS justificados,
            MAX(a.estado = 'presente') AS tiene_presente,
            COUNT(*) AS turnos_registrados
        FROM asistencia a
        WHERE a.fecha = ? $filtroPreceptor
        GROUP BY a.alumno_dni, a.fecha
     ) dia"
);
$stmtTotal->execute($paramsFecha);
$general = $stmtTotal->fetch();

$stmtCursos = $pdo->prepare(
    "SELECT
        CONCAT(cy.year, ' ', cd.division,
               IF(m.nombre IS NULL, '', CONCAT(' - ', m.nombre))) AS curso,
        COALESCE(SUM(dia.presentes), 0) AS presentes,
        COALESCE(SUM(CASE WHEN dia.tiene_presente=1 AND dia.ausentes>0 THEN 0.5 ELSE dia.ausentes END), 0) AS ausentes,
        COALESCE(SUM(dia.tardanzas), 0) AS tardanzas,
        COALESCE(SUM(dia.justificados), 0) AS justificados,
        COALESCE(SUM(dia.turnos_registrados), 0) AS total
     FROM (
        SELECT aa.curso_id, a.alumno_dni, a.fecha,
            SUM(a.estado = 'presente') AS presentes,
            SUM(a.estado = 'ausente') AS ausentes,
            SUM(a.estado = 'tarde') AS tardanzas,
            SUM(a.estado = 'justificado') AS justificados,
            MAX(a.estado = 'presente') AS tiene_presente,
            COUNT(*) AS turnos_registrados
        FROM asistencia a
        JOIN asignado_alumno aa ON aa.alumno_dni = a.alumno_dni
            AND aa.year_escolar_id = ?
            AND aa.estado = 'activo'
        WHERE a.fecha = ? $filtroPreceptor
        GROUP BY aa.curso_id, a.alumno_dni, a.fecha
     ) dia
     JOIN curso c             ON c.id = dia.curso_id
     JOIN curso_year cy       ON cy.id = c.curso_year_id
     JOIN curso_division cd   ON cd.id = c.curso_division_id
     LEFT JOIN modalidad m    ON m.id  = c.modalidad_id
     GROUP BY c.id
     ORDER BY cy.id, cd.id"
);
$stmtCursos->execute(array_merge([$yearId, $fecha], array_slice($paramsFecha, 1)));
$cursos = $stmtCursos->fetchAll();

echo json_encode([
    'fecha'   => date('d/m/Y', strtotime($fecha)),
    'turno'   => 'general',
    'general' => $general,
    'cursos'  => $cursos,
]);
