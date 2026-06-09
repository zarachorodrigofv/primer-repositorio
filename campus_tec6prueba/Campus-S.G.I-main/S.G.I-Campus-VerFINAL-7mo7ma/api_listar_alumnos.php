<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/config.php';
require __DIR__.'/auth.php';

requireLogin();
$pdo = db();

// ========= Año lectivo activo (último) =========
$yearRow = $pdo->query("SELECT id FROM year_escolar ORDER BY `year` DESC LIMIT 1")->fetch();
$year_id = (int)($yearRow['id'] ?? 0);
if (!$year_id) {
  echo json_encode(['ok'=>false, 'msg'=>'No hay año lectivo configurado']);
  exit;
}

// ========= Detectar columnas reales en `alumnos` =========
function colExiste(PDO $pdo, $tabla, $col) {
  $st = $pdo->prepare("
    SELECT COUNT(*) c
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
  ");
  $st->execute([$tabla, $col]);
  return (int)$st->fetchColumn() > 0;
}

$pkAlumnos = colExiste($pdo, 'alumnos', 'alumno_dni') ? 'alumno_dni' : (colExiste($pdo, 'alumnos', 'dni') ? 'dni' : null);

$hasTel  = colExiste($pdo, 'alumnos', 'telefono');
$hasDir  = colExiste($pdo, 'alumnos', 'direccion');
$hasAus  = colExiste($pdo, 'alumnos', 'ausente');
$hasPre  = colExiste($pdo, 'alumnos', 'presente');

// Si no hay forma de vincular alumnos, seguimos sin romper (left join sin columnas)
$joinAlumnos = '';
$selTel = "'' AS telefono";
$selDir = "'' AS direccion";
$selAus = "'' AS ausente";
$selPre = "'' AS presente";

if ($pkAlumnos) {
  $joinAlumnos = "LEFT JOIN alumnos al ON al.`$pkAlumnos` = aa.alumno_dni";
  if ($hasTel) $selTel = "COALESCE(al.telefono,'')  AS telefono";
  if ($hasDir) $selDir = "COALESCE(al.direccion,'') AS direccion";
  if ($hasAus) $selAus = "COALESCE(al.ausente,'')   AS ausente";
  if ($hasPre) $selPre = "COALESCE(al.presente,'')  AS presente";
}

// ========= Filtro opcional por curso =========
$curso_id = (int)($_GET['curso_id'] ?? 0);

$sql = "
  SELECT 
    aa.alumno_dni AS dni,
    u.nombre      AS nombre,
    $selTel,
    $selDir,
    $selAus,
    $selPre
  FROM asignado_alumno aa
  JOIN usuarios u ON u.dni = aa.alumno_dni
  $joinAlumnos
  WHERE aa.year_escolar_id = :year
";
$params = [':year'=>$year_id];

if ($curso_id > 0) {
  $sql .= " AND aa.curso_id = :curso";
  $params[':curso'] = $curso_id;
}

$sql .= " ORDER BY u.nombre";

try {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode(['ok'=>true, 'alumnos'=>$rows]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'msgError'=>$e->getMessage()]);
}

