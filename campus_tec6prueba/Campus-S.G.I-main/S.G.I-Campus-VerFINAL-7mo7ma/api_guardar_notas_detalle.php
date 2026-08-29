<?php
// api_guardar_notas_detalle.php
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/config.php';
require __DIR__.'/auth.php';

requireLogin();
requireCsrf();
$rol = strtolower($_SESSION['rol'] ?? '');
if (!in_array($rol, ['profesor','preceptor','directivo','admin','root'], true)) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}

$pdo = db();

// Año lectivo activo
$yearRow = $pdo->query("SELECT id FROM year_escolar ORDER BY `year` DESC LIMIT 1")->fetch();
$year_id = (int)($yearRow['id'] ?? 0);
if (!$year_id) {
  echo json_encode(['ok'=>false,'msg'=>'Sin año lectivo']);
  exit;
}

$raw     = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!$payload) {
  echo json_encode(['ok'=>false,'msg'=>'JSON inválido']);
  exit;
}

$materia_id = (int)($payload['materia_id'] ?? 0);
$data       = $payload['data'] ?? null;
if ($materia_id<=0 || !is_array($data)) {
  echo json_encode(['ok'=>false,'msg'=>'Faltan materia_id o data']);
  exit;
}

// Si es profesor, verifico que esa materia esté asignada en este año lectivo
if ($rol === 'profesor') {
    $dniProfe = (int)($_SESSION['dni'] ?? 0);
    $st = $pdo->prepare("
      SELECT 1
      FROM asignado_profesor ap
      JOIN materias_year my ON my.id = ap.materias_year_id
      WHERE ap.maestro_dni = :dni
        AND my.materias_id = :mat
        AND my.year_escolar_id = :year
      LIMIT 1
    ");
    $st->execute([
        ':dni'  => $dniProfe,
        ':mat'  => $materia_id,
        ':year' => $year_id
    ]);
    if (!$st->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'msg'=>'Esta materia no está asignada a este profesor en el año lectivo actual']);
        exit;
    }
}
try {
  $pdo->beginTransaction();

  // AHORA incluimos nota_final en el INSERT
  $sql = "
    INSERT INTO notas_detalle (
      alumno_dni,
      materia_id,
      year_escolar_id,
      cuatrimestre,
      nota_valorativa,
      nota_numerica,
      nota_final,
      intens_diciembre,
      intens_febrero,
      intens_marzo,
      observaciones
    )
    VALUES (:dni, :mat, :year, :c, :val, :num, :final, :intens_dic, :intens_feb, :intens_mar, :obs)
    ON DUPLICATE KEY UPDATE
      nota_valorativa = VALUES(nota_valorativa),
      nota_numerica   = VALUES(nota_numerica),
      nota_final      = VALUES(nota_final),
      intens_diciembre = VALUES(intens_diciembre),
      intens_febrero   = VALUES(intens_febrero),
      intens_marzo     = VALUES(intens_marzo),
      observaciones    = VALUES(observaciones)
  ";
  $stmt = $pdo->prepare($sql);

  foreach ($data as $row) {
    $dni = (int)($row['dni'] ?? 0);
    if ($dni <= 0) continue;

    // Datos del front
    $c1_val = $row['c1_val'] ?? null;
    $c2_val = $row['c2_val'] ?? null;

    $c1_raw = $row['c1_num'] ?? '';
    $c2_raw = $row['c2_num'] ?? '';

    $obs    = $row['obs'] ?? null;
    $intensDic = $row['intens_diciembre'] ?? null;
    $intensFeb = $row['intens_febrero'] ?? null;
    $intensMar = $row['intens_marzo'] ?? null;

    // Normalizo numéricas
    $c1_num = ($c1_raw === '' ? null : (float)$c1_raw);
    $c2_num = ($c2_raw === '' ? null : (float)$c2_raw);

    // Calcular nota final solo si tengo las dos
    $final = null;
    if ($c1_num !== null && $c2_num !== null) {
      // promedio redondeando PARA ABAJO
      $final = floor(($c1_num + $c2_num) / 2);
    }

    // Seguridad por alumno/curso:
    // el preceptor solo puede calificar alumnos de sus cursos y la materia de ese curso.
    if ($rol === 'preceptor') {
      $dniPrec = (int)($_SESSION['dni'] ?? 0);
      $stAcc = $pdo->prepare("
        SELECT 1
        FROM asignado_alumno aa
        JOIN preceptor_curso pc
          ON pc.curso_id = aa.curso_id
         AND pc.year_escolar_id = aa.year_escolar_id
        JOIN curso_materia cm
          ON cm.curso_id = aa.curso_id
         AND cm.year_escolar_id = aa.year_escolar_id
         AND cm.materia_id = :mat
        WHERE aa.alumno_dni = :alumno
          AND aa.year_escolar_id = :year
          AND aa.estado = 'activo'
          AND pc.preceptor_dni = :prec
        LIMIT 1
      ");
      $stAcc->execute([
        ':alumno'=>$dni,
        ':year'=>$year_id,
        ':mat'=>$materia_id,
        ':prec'=>$dniPrec
      ]);
      if (!$stAcc->fetchColumn()) {
        continue;
      }
    }

    if ($rol === 'profesor') {
      $dniProfe = (int)($_SESSION['dni'] ?? 0);
      $stAcc = $pdo->prepare("
        SELECT 1
        FROM asignado_alumno aa
        JOIN curso_materia cm
          ON cm.curso_id = aa.curso_id
         AND cm.year_escolar_id = aa.year_escolar_id
         AND cm.materia_id = :mat
        JOIN docente_materia_curso dmc
          ON dmc.curso_materia_id = cm.id
         AND dmc.maestro_dni = :profe
        WHERE aa.alumno_dni = :alumno
          AND aa.year_escolar_id = :year
          AND aa.estado = 'activo'
        LIMIT 1
      ");
      $stAcc->execute([
        ':alumno'=>$dni,
        ':year'=>$year_id,
        ':mat'=>$materia_id,
        ':profe'=>$dniProfe
      ]);
      if (!$stAcc->fetchColumn()) {
        continue;
      }
    }

    // 1º cuatrimestre (nota_final NULL aquí)
    $stmt->execute([
      ':dni'   => $dni,
      ':mat'   => $materia_id,
      ':year'  => $year_id,
      ':c'     => '1',
      ':val'   => $c1_val,
      ':num'   => $c1_num,
      ':final' => null,
      ':intens_dic' => $intensDic,
      ':intens_feb' => $intensFeb,
      ':intens_mar' => $intensMar,
      ':obs'   => $obs,
    ]);

    // 2º cuatrimestre (nota_final va en esta fila)
    $stmt->execute([
      ':dni'   => $dni,
      ':mat'   => $materia_id,
      ':year'  => $year_id,
      ':c'     => '2',
      ':val'   => $c2_val,
      ':num'   => $c2_num,
      ':final' => $final,
      ':intens_dic' => $intensDic,
      ':intens_feb' => $intensFeb,
      ':intens_mar' => $intensMar,
      ':obs'   => $obs,
    ]);
  }

  $pdo->commit();
  echo json_encode(['ok'=>true]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error al guardar: '.$e->getMessage()]);
}
