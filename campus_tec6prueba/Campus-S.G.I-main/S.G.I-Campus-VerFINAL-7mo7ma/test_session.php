<?php
require __DIR__ . '/auth.php';
requireAnyRole(['root']);
header('Content-Type: text/plain; charset=utf-8');
print_r($_SESSION);
