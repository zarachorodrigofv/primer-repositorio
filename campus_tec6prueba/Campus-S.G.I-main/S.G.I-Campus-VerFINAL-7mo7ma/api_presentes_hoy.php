<?php
// Suprimir notices para que el JSON no se rompa con warnings de PHP
error_reporting(0);
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['preceptor','directivo'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso']);
    exit;
}

header('Content-Type: application/json');

// Fecha: parámetro GET o hoy
$fechaParam = $_GET['fecha'] ?? '';
$fecha = ($fechaParam && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaParam))
    ? $fechaParam
    : date('Y-m-d');

$pdo = db();

$stmtTotal = $pdo->prepare(
    "SELECT
        COUNT(CASE WHEN a.estado = 'presente'    THEN 1 END) AS presentes,
        COUNT(CASE WHEN a.estado = 'ausente'     THEN 1 END) AS ausentes,
        COUNT(CASE WHEN a.estado = 'tarde'       THEN 1 END) AS tardanzas,
        COUNT(CASE WHEN a.estado = 'justificado' THEN 1 END) AS justificados,
        COUNT(*) AS total
     FROM asistencia a
     WHERE a.fecha = ?"
);
$stmtTotal->execute([$fecha]);
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
     GROUP BY c.id
     ORDER BY cy.id, cd.id"
);
$stmtCursos->execute([$fecha]);
$cursos = $stmtCursos->fetchAll();

echo json_encode([
    'fecha'   => date('d/m/Y', strtotime($fecha)),
    'general' => $general,
    'cursos'  => $cursos,
]);