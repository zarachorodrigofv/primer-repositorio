<?php
require_once __DIR__ . '/auth.php';
requireAnyRole(ROLES_ASISTENCIA);
requireCsrf();
header('Content-Type: application/json');

$alumno_dni  = isset($_POST['alumno_dni'])  ? (int)$_POST['alumno_dni']  : 0;
$fecha       = isset($_POST['fecha'])       ? trim($_POST['fecha'])       : '';
$motivo      = isset($_POST['motivo'])      ? trim($_POST['motivo'])      : '';

// Validaciones básicas
if (!$alumno_dni || !$fecha || $motivo === '') {
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


// Actualizar el campo motivo_justificado en el registro que ya existe
// (el estado 'justificado' ya fue guardado por el formulario principal)
$stmt = $conn->prepare(
    "UPDATE asistencia
     SET motivo_justificado = ?
     WHERE alumno_dni = ? AND fecha = ? AND estado = 'justificado'"
);
$stmt->bind_param("sis", $motivo, $alumno_dni, $fecha);
$stmt->execute();
$filas = $stmt->affected_rows;
$stmt->close();

// Si no existía el registro aún (guardó el justificado pero no guardó el form todavía),
// hacer un INSERT con estado justificado y el motivo ya incluido
if ($filas === 0) {
    // Verificar si existe algún registro para ese alumno/fecha
    $check = $conn->prepare("SELECT id FROM asistencia WHERE alumno_dni = ? AND fecha = ?");
    $check->bind_param("is", $alumno_dni, $fecha);
    $check->execute();
    $check->store_result();
    $existe = $check->num_rows > 0;
    $check->close();

    if ($existe) {
        // Existe pero no es justificado — actualizar estado Y motivo
        $upd = $conn->prepare(
            "UPDATE asistencia SET estado = 'justificado', motivo_justificado = ?
             WHERE alumno_dni = ? AND fecha = ?"
        );
        $upd->bind_param("sis", $motivo, $alumno_dni, $fecha);
        $upd->execute();
        $upd->close();
    } else {
        // No existe — insertar nuevo registro
        $ins = $conn->prepare(
            "INSERT INTO asistencia (alumno_dni, fecha, estado, motivo_justificado)
             VALUES (?, ?, 'justificado', ?)"
        );
        $ins->bind_param("iss", $alumno_dni, $fecha, $motivo);
        $ins->execute();
        $ins->close();
    }
}

$conn->close();
echo json_encode(['ok' => true]);
