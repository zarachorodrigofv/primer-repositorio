<?php
require __DIR__ . '/auth.php';
requireAnyRole(['root']);
require __DIR__ . '/config.php';
$pdo = db();
echo "OK - conexión disponible.";
