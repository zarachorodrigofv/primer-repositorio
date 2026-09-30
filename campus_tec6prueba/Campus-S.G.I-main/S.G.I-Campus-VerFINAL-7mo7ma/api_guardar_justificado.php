<?php
require_once __DIR__ . '/auth.php';
requireAnyRole(ROLES_ASISTENCIA);
requireCsrf();
header('Content-Type: application/json');

$alumno_dni  = isset($_POST['alumno_dni'])  ? (int)$_POST['alumno_dni']  : 0;
$fecha       = isset($_POST['fecha'])       ? trim($_POST['fecha'])       : '';
$motivo      = isset($_POST['motivo'])      ? trim($_POST['motivo'])      : '';
$turno       = isset($_POST['turno'])       ? trim($_POST['turno'])       : '';

// Validaciones básicas
if (!$alumno_dni || !$fecha || $motivo === '' || !in_array($turno, ['mañana','tarde','vespertino'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Datos incompletos']);
    exit;
}

// Validar formato de fecha
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    echo json_encode(['ok' => false, 'error' => 'Fecha inválida']);
    exit;
}

$conn = new mysqli("localhost", "root", "", "campus");
if ($conn->connect_error) {
    echo json_encode(['ok' => false, 'error' => 'Error de conexión']);
    exit;
}
$conn->set_charset("utf8mb4");
// Si es preceptor, el alumno debe pertenecer a uno de sus cursos.
$rolSesion = currentRole();
if ($rolSesion === 'preceptor') {
    $dniPrec = (int)($_SESSION['dni'] ?? 0);
    $st = $conn->prepare("
        SELECT 1
        FROM asignado_alumno aa
        JOIN preceptor_curso pc
          ON pc.curso_id = aa.curso_id
         AND pc.year_escolar_id = aa.year_escolar_id
        WHERE aa.alumno_dni = ?
          AND aa.year_escolar_id = (SELECT id FROM year_escolar ORDER BY `year` DESC LIMIT 1)
          AND aa.estado='activo'
          AND pc.preceptor_dni = ?
        LIMIT 1
    ");
    $st->bind_param("ii", $alumno_dni, $dniPrec);
    $st->execute();
    if ($st->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'No tenés acceso a este alumno.']);
        exit;
    }
    $st->close();
}


// El alumno debe estar inscripto en ese turno del curso durante el año activo.
$yearId = 0;
$stYear = $conn->query("SELECT id FROM year_escolar ORDER BY `year` DESC LIMIT 1");
if ($yr = $stYear->fetch_assoc()) $yearId = (int)$yr['id'];
$st = $conn->prepare("SELECT aat.curso_id, ct.id AS turno_id
    FROM asignado_alumno_turno aat
    JOIN curso_turno ct ON ct.id = aat.turno_id
    WHERE aat.alumno_dni=? AND aat.year_escolar_id=? AND ct.turno=? LIMIT 1");
$st->bind_param("iis", $alumno_dni, $yearId, $turno);
$st->execute();
$asig = $st->get_result()->fetch_assoc();
$st->close();
if (!$asig) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'El alumno no está asignado a ese turno.']);
    exit;
}
$turnoId = (int)$asig['turno_id'];

if ($rolSesion === 'preceptor') {
    $dniPrec = (int)($_SESSION['dni'] ?? 0);
    $st = $conn->prepare("SELECT 1 FROM preceptor_curso pc
        WHERE pc.preceptor_dni=? AND pc.curso_id=? AND pc.year_escolar_id=? LIMIT 1");
    $st->bind_param("iii", $dniPrec, $asig['curso_id'], $yearId);
    $st->execute();
    if ($st->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'No tenés acceso a este curso.']);
        exit;
    }
    $st->close();
}

$stmt = $conn->prepare("UPDATE asistencia SET motivo_justificado = ?
     WHERE alumno_dni = ? AND fecha = ? AND turno_id = ? AND estado = 'justificado'");
$stmt->bind_param("sisi", $motivo, $alumno_dni, $fecha, $turnoId);
$stmt->execute();
$filas = $stmt->affected_rows;
$stmt->close();

if ($filas === 0) {
    $check = $conn->prepare("SELECT id FROM asistencia WHERE alumno_dni = ? AND fecha = ? AND turno_id = ?");
    $check->bind_param("isi", $alumno_dni, $fecha, $turnoId);
    $check->execute();
    $check->store_result();
    $existe = $check->num_rows > 0;
    $check->close();

    if ($existe) {
        $upd = $conn->prepare("UPDATE asistencia SET estado = 'justificado', motivo_justificado = ? WHERE alumno_dni = ? AND fecha = ? AND turno_id = ?");
        $upd->bind_param("sisi", $motivo, $alumno_dni, $fecha, $turnoId);
        $upd->execute();
        $upd->close();
    } else {
        $ins = $conn->prepare("INSERT INTO asistencia (alumno_dni, fecha, turno_id, estado, motivo_justificado) VALUES (?, ?, ?, 'justificado', ?) ON DUPLICATE KEY UPDATE estado = 'justificado', motivo_justificado = VALUES(motivo_justificado)");
        $ins->bind_param("isis", $alumno_dni, $fecha, $turnoId, $motivo);
        $ins->execute();
        $ins->close();
    }
}

$conn->close();
echo json_encode(['ok' => true]);
