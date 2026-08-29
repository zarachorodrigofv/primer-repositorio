<?php
require __DIR__.'/config.php';
require __DIR__.'/auth.php';
requireLogin();
$rol = currentRole();
if (!in_array($rol, ROLES_FORO, true)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
    exit;
}

$pdo = db();
$rol = $_SESSION['rol'] ?? '';
$dni = $_SESSION['dni'] ?? 0;

$result = [];

if (in_array($rol, ['directivo','admin','root'], true)) {
    // Todos los cursos
    $sql = "SELECT c.id,
                   CONCAT(cy.year,' ', cd.division,
                          IF(mo.nombre IS NULL,'', CONCAT(' - ', mo.nombre))) AS nombre
            FROM curso c
            JOIN curso_year cy     ON cy.id = c.curso_year_id
            JOIN curso_division cd ON cd.id = c.curso_division_id
            LEFT JOIN modalidad mo ON mo.id = c.modalidad_id
            ORDER BY cy.id, cd.id";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($rol === 'preceptor') {
    // Solo cursos del preceptor (preceptor_curso)
    $sql = "SELECT DISTINCT c.id,
                   CONCAT(cy.year,' ', cd.division,
                          IF(mo.nombre IS NULL,'', CONCAT(' - ', mo.nombre))) AS nombre
            FROM preceptor_curso pc
            JOIN curso c           ON c.id = pc.curso_id
            JOIN curso_year cy     ON cy.id = c.curso_year_id
            JOIN curso_division cd ON cd.id = c.curso_division_id
            LEFT JOIN modalidad mo ON mo.id = c.modalidad_id
            WHERE pc.preceptor_dni = ?
            ORDER BY cy.id, cd.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dni]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($rol === 'profesor') {
    // Cursos donde el profe da clase (docente_materia_curso -> curso_materia -> curso)
    $sql = "SELECT DISTINCT c.id,
                   CONCAT(cy.year,' ', cd.division,
                          IF(mo.nombre IS NULL,'', CONCAT(' - ', mo.nombre))) AS nombre
            FROM docente_materia_curso dmc
            JOIN curso_materia cm   ON cm.id = dmc.curso_materia_id
            JOIN curso c            ON c.id = cm.curso_id
            JOIN curso_year cy      ON cy.id = c.curso_year_id
            JOIN curso_division cd  ON cd.id = c.curso_division_id
            LEFT JOIN modalidad mo  ON mo.id = c.modalidad_id
            WHERE dmc.maestro_dni = ?
            ORDER BY cy.id, cd.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dni]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);
