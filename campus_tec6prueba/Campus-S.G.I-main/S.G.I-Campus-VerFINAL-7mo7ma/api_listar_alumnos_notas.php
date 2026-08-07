<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/config.php';
require __DIR__.'/auth.php';

requireLogin();
$pdo = db();

$rol = strtolower(currentRole() ?? '');
$dni = (int)($_SESSION['dni'] ?? 0);

// ========= Año lectivo activo =========
$yearRow = $pdo->query("SELECT id, `year` FROM year_escolar ORDER BY `year` DESC LIMIT 1")->fetch();
$year_id = (int)($yearRow['id'] ?? 0);
if (!$year_id) {
  echo json_encode(['ok'=>false, 'msg'=>'No hay año lectivo configurado']);
  exit;
}

// ========= Parámetros =========
$curso_id   = (int)($_GET['curso_id']   ?? 0);
$materia_id = (int)($_GET['materia_id'] ?? 0);

if ($curso_id <= 0 || $materia_id <= 0) {
  echo json_encode(['ok'=>false, 'msg'=>'Faltan curso_id o materia_id']);
  exit;
}

// ========= Cursos permitidos según rol =========
$cursoIdsPermitidos = null; // null = sin restricción (directivo)

if ($rol === 'directivo') {
  // ve todo
  $cursoIdsPermitidos = null;

} elseif ($rol === 'preceptor') {
  // cursos donde es preceptor este año
  $sql = "SELECT pc.curso_id
          FROM preceptor_curso pc
          WHERE pc.preceptor_dni = :dni
            AND pc.year_escolar_id = :year";
  $st = $pdo->prepare($sql);
  $st->execute([':dni' => $dni, ':year' => $year_id]);
  $cursoIdsPermitidos = $st->fetchAll(PDO::FETCH_COLUMN);
  $cursoIdsPermitidos = array_map('intval', $cursoIdsPermitidos);

} elseif ($rol === 'profesor') {
  // cursos donde dicta materias este año
  $sql = "SELECT DISTINCT c.id
          FROM docente_materia_curso dmc
          JOIN curso_materia cm ON cm.id = dmc.curso_materia_id
          JOIN curso c          ON c.id = cm.curso_id
          WHERE dmc.maestro_dni    = :dni
            AND cm.year_escolar_id = :year";
  $st = $pdo->prepare($sql);
  $st->execute([':dni' => $dni, ':year' => $year_id]);
  $cursoIdsPermitidos = $st->fetchAll(PDO::FETCH_COLUMN);
  $cursoIdsPermitidos = array_map('intval', $cursoIdsPermitidos);

  // OPCIONAL extra: asegurarse que esa materia está asignada a ese profe en ese curso
  // (seguridad extra, pero idealmente ya lo filtra api_listar_materias.php)
} else {
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}

// Si no hay cursos permitidos (y no es directivo), devolver vacío
if ($rol !== 'directivo' && empty($cursoIdsPermitidos)) {
  echo json_encode(['ok'=>true,'alumnos'=>[]]);
  exit;
}

// Si el curso no es permitido, devolver vacío
if ($rol !== 'directivo' && !in_array($curso_id, $cursoIdsPermitidos, true)) {
  echo json_encode(['ok'=>true,'alumnos'=>[]]);
  exit;
}

// ========= Traer alumnos + notas por materia =========
//  - C1 = cuatrimestre '1'
//  - C2 = cuatrimestre '2'
//  - nota_final = viene de la fila de C2
$sql = "
  SELECT 
    aa.alumno_dni AS dni,
    u.nombre      AS nombre,

    -- C1
    n1.nota_valorativa AS c1_val,
    n1.nota_numerica   AS c1_num,

    -- C2
    n2.nota_valorativa AS c2_val,
    n2.nota_numerica   AS c2_num,

    -- Final (guardada en el cuatrimestre 2)
    n2.nota_final      AS final_num,

    -- Intensificaciones
    COALESCE(n2.intens_diciembre, n1.intens_diciembre) AS intens_diciembre,
    COALESCE(n2.intens_febrero, n1.intens_febrero) AS intens_febrero,
    COALESCE(n2.intens_marzo, n1.intens_marzo) AS intens_marzo,

    CASE
      WHEN COALESCE(n2.nota_final, n1.nota_final, 0) >= 6 THEN 0
      ELSE 1
    END AS debe_recursar,

    CASE
      WHEN COALESCE(n2.nota_final, n1.nota_final, 0) >= 6 THEN ''
      ELSE 'Debe 1 materia, tiene que recursarla'
    END AS mensaje_recursar,

    -- Observaciones (priorizo las de C2, si no hay tomo las de C1)
    COALESCE(n2.observaciones, n1.observaciones) AS obs

  FROM asignado_alumno aa
  JOIN usuarios u ON u.dni = aa.alumno_dni

  LEFT JOIN notas_detalle n1
    ON n1.alumno_dni     = aa.alumno_dni
   AND n1.materia_id     = :mat
   AND n1.year_escolar_id= :year
   AND n1.cuatrimestre   = '1'

  LEFT JOIN notas_detalle n2
    ON n2.alumno_dni     = aa.alumno_dni
   AND n2.materia_id     = :mat
   AND n2.year_escolar_id= :year
   AND n2.cuatrimestre   = '2'

  WHERE aa.year_escolar_id = :year
    AND aa.curso_id        = :curso
    AND aa.estado          = 'activo'

  ORDER BY u.nombre
";

$params = [
  ':year'  => $year_id,
  ':mat'   => $materia_id,
  ':curso' => $curso_id,
];

try {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode(['ok'=>true, 'alumnos'=>$rows]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'msg'=>'Error SQL: '.$e->getMessage()]);
}
