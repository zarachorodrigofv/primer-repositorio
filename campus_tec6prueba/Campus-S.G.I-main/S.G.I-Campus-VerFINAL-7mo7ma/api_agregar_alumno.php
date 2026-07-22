<?php
// api_agregar_alumno.php
header('Content-Type: application/json; charset=utf-8');

require __DIR__.'/config.php';
require __DIR__.'/auth.php';

requireLogin();
$rol = strtolower($_SESSION['rol'] ?? '');
if (!in_array($rol, ['preceptor','directivo'], true)) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}

$pdo = db();

// ========= lee POST =========
$curso_id  = (int)($_POST['curso_id'] ?? 0);
$nombre    = trim($_POST['nombre']    ?? '');
$dni_str   = trim($_POST['dni']       ?? '');
$telefono  = trim($_POST['telefono']  ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$ausente   = trim($_POST['ausente']   ?? '');
$presente  = trim($_POST['presente']  ?? '');

if ($curso_id <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'Elegí un curso (curso_id)']);
  exit;
}
if ($nombre === '' || $dni_str === '' || !ctype_digit($dni_str)) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'msg'=>'Nombre y DNI (numérico) son obligatorios']);
  exit;
}
$dni = (int)$dni_str;

// ========= año lectivo activo =========
$yearRow = $pdo->query("SELECT id FROM year_escolar ORDER BY `year` DESC LIMIT 1")->fetch();
$year_id = (int)($yearRow['id'] ?? 0);
if (!$year_id) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Configurá el año lectivo en year_escolar']);
  exit;
}

try {
  $pdo->beginTransaction();

  // 1) crear/actualizar usuario de alumno con contraseña temporal estándar
  $tempPassword = 'Fran123';
  $passHash = password_hash($tempPassword, PASSWORD_DEFAULT);
  $sqlUser = "
    INSERT INTO usuarios (dni, nombre, password, rol, password_changed)
    VALUES (:dni, :nombre, :pass, 'alumno', 0)
    ON DUPLICATE KEY UPDATE
      nombre = VALUES(nombre),
      password = VALUES(password),
      rol = 'alumno',
      password_changed = 0
  ";
  $pdo->prepare($sqlUser)->execute([
    ':dni'    => $dni,
    ':nombre' => $nombre,
    ':pass'   => $passHash
  ]);

  // 2) upsert en alumnos (OJO: columna PK es alumno_dni)
  //    Si 'alumno_dni' no es UNIQUE/PRIMARY, añadilo en la DB.
  $sqlAlu = "
   INSERT INTO alumnos (alumno_dni, telefono, direccion, ausente, presente)
    VALUES (:dni, :tel, :dir, :aus, :pre)
    ON DUPLICATE KEY UPDATE
      telefono  = VALUES(telefono),
      direccion = VALUES(direccion),
      ausente   = VALUES(ausente),
      presente  = VALUES(presente)
  ";
  $pdo->prepare($sqlAlu)->execute([
    ':dni' => $dni,
    ':tel' => $telefono,
    ':dir' => $direccion,
    ':aus' => $ausente,
    ':pre' => $presente
  ]);

  // 3) inscribir/activar en asignado_alumno
  $sqlInsc = "
    INSERT INTO asignado_alumno (alumno_dni, curso_id, year_escolar_id, estado, fecha_inscripcion)
    VALUES (:dni, :curso, :year, 'activo', CURRENT_DATE())
    ON DUPLICATE KEY UPDATE estado='activo', fecha_baja=NULL, motivo_baja=NULL";
  $pdo->prepare($sqlInsc)->execute([
    ':dni'   => $dni,
    ':curso' => $curso_id,
    ':year'  => $year_id
  ]);

  $pdo->commit();

  echo json_encode([
    'ok' => true,
    'temp_password' => $tempPassword,
    'alumno' => [
      'nombre'    => $nombre,
      'dni'       => (string)$dni_str,
      'telefono'  => $telefono,
      'direccion' => $direccion,
      'ausente'   => $ausente,
      'presente'  => $presente
    ]
  ]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error al guardar: '.$e->getMessage()]);
}
