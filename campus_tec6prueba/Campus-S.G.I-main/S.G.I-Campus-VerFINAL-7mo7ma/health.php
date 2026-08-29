<?php
require __DIR__ . '/auth.php';
requireAnyRole(['root','admin']);
header('Content-Type: text/plain; charset=utf-8');
echo "PHP OK -- version: " . PHP_VERSION;
