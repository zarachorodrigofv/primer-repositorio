<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/config.php';
require __DIR__.'/auth.php';

requireLogin();
$pdo = db();

// año lectivo activo
$yearRow = $pdo->query("SELECT id FROM year_escolar ORDER BY `year` DESC LIMIT 1")->fetch();
$year_id = (int)($yearRow['id'] ?? 0);
if (!$year_id) { echo json_encode(['ok'=>false,'msg'=>'No hay año lectivo']); exit; }

$curso_id = (int)($_GET['curso_id'] ?? 0);
if ($curso_id <= 0) { echo json_encode(['ok'=>true,'rows'=>[]]); exit; }

// alumnos + notas
$sql = "
SELECT 
  aa.alumno_dni AS dni,
  u.nombre,
  n.vp_c1, n.int_c1, n.vp_c2, n.cierre_anual, n.int_dic, n.int_feb, n.amp_mar, n.informe_final
FROM asignado_alumno aa
JOIN usuarios u ON u.dni = aa.alumno_dni
LEFT JOIN notas n 
  ON n.alumno_dni = aa.alumno_dni AND n.year_escolar_id = :year
WHERE aa.year_escolar_id = :year AND aa.curso_id = :curso
ORDER BY u.nombre
";
$st = $pdo->prepare($sql);
$st->execute([':year'=>$year_id, ':curso'=>$curso_id]);
echo json_encode(['ok'=>true, 'rows'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
