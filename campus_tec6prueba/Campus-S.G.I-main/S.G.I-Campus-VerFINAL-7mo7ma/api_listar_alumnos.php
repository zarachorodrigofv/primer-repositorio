<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/config.php';
require __DIR__.'/auth.php';

requireLogin();
$rol = currentRole();
if (!in_array($rol, ROLES_LISTADO, true)) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}
$pdo = db();

// ========= Ciclo solicitado; por defecto, el más reciente =========
$year_id = (int)($_GET['year_id'] ?? 0);
if ($year_id > 0) {
  $stYear = $pdo->prepare("SELECT id FROM year_escolar WHERE id = ? LIMIT 1");
  $stYear->execute([$year_id]);
  $year_id = (int)($stYear->fetchColumn() ?: 0);
} else {
  $year_id = (int)$pdo->query("SELECT id FROM year_escolar ORDER BY `year` DESC LIMIT 1")->fetchColumn();
}
if (!$year_id) {
  echo json_encode(['ok'=>false, 'msg'=>'Ciclo lectivo no válido o no configurado']);
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
    aa.curso_id   AS curso_id,
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
  if ($rol === 'preceptor' && !preceptorTieneCurso($pdo, (int)$_SESSION['dni'], $curso_id, $year_id)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'msg'=>'No tenÃ©s acceso a este curso']);
    exit;
  }
  $sql .= " AND aa.curso_id = :curso";
  $params[':curso'] = $curso_id;
}

if ($rol === 'preceptor' && $curso_id <= 0) {
  $sql .= " AND EXISTS (SELECT 1 FROM preceptor_curso pc WHERE pc.preceptor_dni = :preceptor AND pc.curso_id = aa.curso_id AND pc.year_escolar_id = aa.year_escolar_id)";
  $params[':preceptor'] = (int)$_SESSION['dni'];
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
