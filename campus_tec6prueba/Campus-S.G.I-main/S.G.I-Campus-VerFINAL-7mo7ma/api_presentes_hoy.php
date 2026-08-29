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
        COUNT(CASE WHEN a.estado = 'presente'    THEN 1 END) AS presentes,
        COUNT(CASE WHEN a.estado = 'ausente'     THEN 1 END) AS ausentes,
        COUNT(CASE WHEN a.estado = 'tarde'       THEN 1 END) AS tardanzas,
        COUNT(CASE WHEN a.estado = 'justificado' THEN 1 END) AS justificados,
        COUNT(*) AS total
     FROM asistencia a
     WHERE a.fecha = ? $filtroPreceptor"
);
$stmtTotal->execute($paramsFecha);
$general = $stmtTotal->fetch();

$stmtCursos = $pdo->prepare(
    "SELECT
        CONCAT(cy.year, ' ', cd.division,
               IF(m.nombre IS NULL, '', CONCAT(' - ', m.nombre))) AS curso,
        COUNT(CASE WHEN a.estado = 'presente'    THEN 1 END) AS presentes,
        COUNT(CASE WHEN a.estado = 'ausente'     THEN 1 END) AS ausentes,
        COUNT(CASE WHEN a.estado = 'tarde'       THEN 1 END) AS tardanzas,
        COUNT(CASE WHEN a.estado = 'justificado' THEN 1 END) AS justificados,
        COUNT(*) AS total
     FROM asistencia a
     JOIN asignado_alumno aa ON aa.alumno_dni = a.alumno_dni
     JOIN curso c             ON c.id = aa.curso_id
     JOIN curso_year cy       ON cy.id = c.curso_year_id
     JOIN curso_division cd   ON cd.id = c.curso_division_id
     LEFT JOIN modalidad m    ON m.id  = c.modalidad_id
     WHERE a.fecha = ?
       AND aa.estado = 'activo'
       $filtroPreceptor
     GROUP BY c.id
     ORDER BY cy.id, cd.id"
);
$stmtCursos->execute($paramsFecha);
$cursos = $stmtCursos->fetchAll();

echo json_encode([
    'fecha'   => date('d/m/Y', strtotime($fecha)),
    'general' => $general,
    'cursos'  => $cursos,
]);
