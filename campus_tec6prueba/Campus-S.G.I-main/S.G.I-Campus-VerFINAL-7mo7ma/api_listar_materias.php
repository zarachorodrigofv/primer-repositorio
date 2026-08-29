<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/config.php';
require __DIR__.'/auth.php';

requireLogin();
$pdo = db();

$rol = strtolower(currentRole() ?? '');
$dni = (int)($_SESSION['dni'] ?? 0);

$curso_id = (int)($_GET['curso_id'] ?? 0);
if ($curso_id <= 0) {
  echo json_encode(['ok'=>false,'msg'=>'Falta curso_id']);
  exit;
}

// Año lectivo activo
$yearRow = $pdo->query("SELECT id FROM year_escolar ORDER BY `year` DESC LIMIT 1")->fetch();
$year_id = (int)($yearRow['id'] ?? 0);
if (!$year_id) {
  echo json_encode(['ok'=>false,'msg'=>'Sin año lectivo']);
  exit;
}

$params = [':curso'=>$curso_id, ':year'=>$year_id];

if (in_array($rol, ['directivo','admin','root'], true)) {

  $sql = "SELECT m.id, m.nombre
          FROM curso_materia cm
          JOIN materias m ON m.id = cm.materia_id
          WHERE cm.curso_id = :curso
            AND cm.year_escolar_id = :year
          ORDER BY m.nombre";

} elseif ($rol === 'preceptor') {

  // Verificamos que el preceptor tenga ese curso asignado
  $st = $pdo->prepare("
    SELECT 1
    FROM preceptor_curso
    WHERE preceptor_dni = :dni
      AND curso_id      = :curso
      AND year_escolar_id = :year
    LIMIT 1
  ");
  $st->execute([':dni'=>$dni, ':curso'=>$curso_id, ':year'=>$year_id]);
  if (!$st->fetchColumn()) {
    echo json_encode(['ok'=>false,'msg'=>'No autorizado a este curso']);
    exit;
  }

  $sql = "SELECT m.id, m.nombre
          FROM curso_materia cm
          JOIN materias m ON m.id = cm.materia_id
          WHERE cm.curso_id = :curso
            AND cm.year_escolar_id = :year
          ORDER BY m.nombre";

} elseif ($rol === 'profesor') {

  // Solo materias que el profe dicta en ese curso
  $sql = "SELECT DISTINCT m.id, m.nombre
          FROM docente_materia_curso dmc
          JOIN curso_materia cm ON cm.id = dmc.curso_materia_id
          JOIN materias m       ON m.id = cm.materia_id
          WHERE cm.curso_id = :curso
            AND cm.year_escolar_id = :year
            AND dmc.maestro_dni = :dni
          ORDER BY m.nombre";
  $params[':dni'] = $dni;

} else {
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['ok'=>true,'materias'=>$rows]);
