<?php
require __DIR__.'/config.php';
$pdo = db();

$stmt = $pdo->query("SELECT * FROM foro ORDER BY fecha DESC");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($posts);
