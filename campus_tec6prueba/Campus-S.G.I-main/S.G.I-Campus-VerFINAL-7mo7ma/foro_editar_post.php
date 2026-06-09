<?php
require __DIR__.'/config.php';
require __DIR__.'/auth.php';

requireLogin();
$pdo = db();

$rol = strtolower($_SESSION['rol'] ?? '');
$dni = $_SESSION['dni'] ?? '';

$id       = (int)($_POST['id'] ?? 0);
$titulo   = trim($_POST['titulo'] ?? '');
$contenido= trim($_POST['contenido'] ?? '');

if ($id <= 0 || $titulo === '' || $contenido === '') {
    http_response_code(400);
    echo "Datos inválidos";
    exit;
}

// Sólo autor o directivo
$stmt = $pdo->prepare("SELECT autor_dni FROM foro WHERE id = ?");
$stmt->execute([$id]);
$autor = $stmt->fetchColumn();

if (!$autor) {
    http_response_code(404);
    echo "No existe";
    exit;
}

if ($rol !== 'directivo' && $autor != $dni) {
    http_response_code(403);
    echo "No autorizado";
    exit;
}

$stmt = $pdo->prepare("
  UPDATE foro
  SET titulo = ?, contenido = ?, fecha_edicion = NOW(), editado = 1
  WHERE id = ?
");
$stmt->execute([$titulo, $contenido, $id]);

echo "ok";
