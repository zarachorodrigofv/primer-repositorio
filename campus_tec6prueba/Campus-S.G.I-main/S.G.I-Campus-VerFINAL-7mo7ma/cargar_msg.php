<?php
session_start();
$usuario = $_SESSION['usuario'];
$contacto = $_GET['con'] ?? '';

$conn = new mysqli("localhost", "root", "", "campus");
if ($conn->connect_error) {
  die("Conexión fallida: " . $conn->connect_error);
}

$stmt = $conn->prepare("SELECT * FROM mensajes WHERE (remitente = ? AND destinatario = ?) OR (remitente = ? AND destinatario = ?) ORDER BY fecha ASC");
$stmt->bind_param("ssss", $usuario, $contacto, $contacto, $usuario);
$stmt->execute();
$result = $stmt->get_result();
$mensajes = $result->fetch_all(MYSQLI_ASSOC);

// Generar HTML
$html = "";
$ultimo_id = 0;
$ultimo_remitente = "";

foreach ($mensajes as $i => $msg) {
  $clase = $msg['remitente'] === $usuario ? "derecha" : "izquierda";
  $html .= "<div class='burbuja {$clase}'>";
  $html .= nl2br(htmlspecialchars($msg['mensaje']));
  $html .= "<small>" . htmlspecialchars($msg['fecha']) . "</small>";
  $html .= "</div>";

  if ($i === 0) {
    $ultimo_id = $msg['id'];
    $ultimo_remitente = $msg['remitente'];
  }
}

echo json_encode([
  "html" => $html,
  "ultimo_id" => $ultimo_id,
  "ultimo_remitente" => $ultimo_remitente
]);
