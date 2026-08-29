<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/config.php';
require __DIR__.'/auth.php';

requireLogin();

// API heredada sin contexto de materia/curso: se conserva como endpoint
// explícitamente inactivo para impedir cargas no autorizables.
http_response_code(410);
echo json_encode(['ok'=>false, 'msg'=>'API de notas heredada deshabilitada. UsÃ¡ la carga actual por materia.']);
exit;

$rol = strtolower($_SESSION['rol'] ?? '');
if (!in_array($rol, ['preceptor','directivo','admin','root'], true)) {
  http_response_code(403);
  echo json_encode(['ok'=>false, 'msg'=>'No autorizado']);
  exit;
}

$pdo = db();

// payload
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!$payload || !isset($payload['year']) || !isset($payload['data']) || !is_array($payload['data'])) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'msg'=>'JSON inválido']);
  exit;
}

// year_id
$year = (int)$payload['year'];
$stY = $pdo->prepare("SELECT id FROM year_escolar WHERE `year` = ?");
$stY->execute([$year]);
$year_id = (int)($stY->fetch()['id'] ?? 0);
if (!$year_id) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'msg'=>'Año lectivo no configurado']);
  exit;
}

try {
  $pdo->beginTransaction();
  $sql = "
    INSERT INTO notas (alumno_dni, year_escolar_id, vp_c1, int_c1, vp_c2, cierre_anual, int_dic, int_feb, amp_mar, informe_final)
    VALUES (:dni, :year_id, :vp_c1, :int_c1, :vp_c2, :cierre_anual, :int_dic, :int_feb, :amp_mar, :informe_final)
    ON DUPLICATE KEY UPDATE
      vp_c1 = VALUES(vp_c1),
      int_c1 = VALUES(int_c1),
      vp_c2 = VALUES(vp_c2),
      cierre_anual = VALUES(cierre_anual),
      int_dic = VALUES(int_dic),
      int_feb = VALUES(int_feb),
      amp_mar = VALUES(amp_mar),
      informe_final = VALUES(informe_final)
  ";
  $st = $pdo->prepare($sql);

  foreach ($payload['data'] as $row) {
    $dni = isset($row['dni']) ? (int)$row['dni'] : 0;
    if ($dni <= 0) continue;

    $st->execute([
      ':dni'          => $dni,
      ':year_id'      => $year_id,
      ':vp_c1'        => $row['vp_c1'] ?? null,
      ':int_c1'       => $row['int_c1'] ?? null,
      ':vp_c2'        => $row['vp_c2'] ?? null,
      ':cierre_anual' => ($row['cierre_anual'] === '' ? null : $row['cierre_anual']),
      ':int_dic'      => $row['int_dic'] ?? null,
      ':int_feb'      => $row['int_feb'] ?? null,
      ':amp_mar'      => $row['amp_mar'] ?? null,
      ':informe_final'=> $row['informe_final'] ?? null
    ]);
  }
  $pdo->commit();
  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false, 'msg'=>'Error al guardar: '.$e->getMessage()]);
}
