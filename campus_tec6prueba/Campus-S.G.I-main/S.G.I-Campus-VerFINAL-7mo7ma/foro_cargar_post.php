<?php
require __DIR__.'/config.php';
require __DIR__.'/auth.php';
requirePage('foro');
$pdo = db();

// Endpoint heredado sin consumidor actual. Nunca expone publicaciones fuera
// del foro autenticado; el filtrado completo de destinos permanece en foro.php.
$stmt = $pdo->query("SELECT * FROM foro WHERE destino_tipo='general' ORDER BY fecha DESC");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($posts);
