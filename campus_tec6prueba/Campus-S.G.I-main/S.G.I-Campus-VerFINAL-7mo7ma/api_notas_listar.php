<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/config.php';
require __DIR__.'/auth.php';

requireLogin();
$rol = currentRole();
if (!in_array($rol, ['profesor','preceptor','directivo','admin','root'], true)) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}
$pdo = db();

// año lectivo activo
$yearRow = $pdo->query("SELECT id FROM year_escolar ORDER BY `year` DESC LIMIT 1")->fetch();
$year_id = (int)($yearRow['id'] ?? 0);
if (!$year_id) { echo json_encode(['ok'=>false,'msg'=>'No hay año lectivo']); exit; }

$curso_id = (int)($_GET['curso_id'] ?? 0);
if ($curso_id <= 0) { echo json_encode(['ok'=>true,'rows'=>[]]); exit; }

if (!in_array($rol, ['directivo','admin','root'], true)) {
    $dniSesion = (int)($_SESSION['dni'] ?? 0);
    if ($rol === 'preceptor') {
        $stAcc = $pdo->prepare("SELECT 1 FROM preceptor_curso WHERE preceptor_dni=? AND curso_id=? AND year_escolar_id=? LIMIT 1");
        $stAcc->execute([$dniSesion,$curso_id,$year_id]);
    } else { // profesor
        $stAcc = $pdo->prepare("SELECT 1 FROM docente_materia_curso dmc JOIN curso_materia cm ON cm.id=dmc.curso_materia_id WHERE dmc.maestro_dni=? AND cm.curso_id=? AND cm.year_escolar_id=? LIMIT 1");
        $stAcc->execute([$dniSesion,$curso_id,$year_id]);
    }
    if (!$stAcc->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'msg'=>'No tenés acceso a este curso']);
        exit;
    }
}

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
