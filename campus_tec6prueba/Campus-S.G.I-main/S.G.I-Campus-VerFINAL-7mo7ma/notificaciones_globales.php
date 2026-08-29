<?php
require_once __DIR__ . '/auth.php';
requirePage('mensajeria');
$usuario = $_SESSION['usuario'] ?? '';
$chatAbierto = $_GET['chat'] ?? '';

$conn = new mysqli("sql103.infinityfree.com", "if0_39451587", "Practicastec6", "if0_39451587_campus");
if ($conn->connect_error) {
    die(json_encode(['error' => 'Error de conexión']));
}

$stmt = $conn->prepare("
  SELECT id, remitente, fecha 
  FROM mensajes 
  WHERE destinatario = ? AND remitente != ? 
  ORDER BY fecha DESC 
  LIMIT 1
");
$stmt->bind_param("ss", $usuario, $chatAbierto);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'id' => $row['id'],
        'nuevo_remitente' => $row['remitente'],
        'fecha' => $row['fecha'],
    ]);
} else {
    echo json_encode([]);
}
