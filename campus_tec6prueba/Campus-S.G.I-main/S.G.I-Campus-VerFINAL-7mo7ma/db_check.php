<?php
require __DIR__ . '/auth.php';
requireAnyRole(['root']);
require __DIR__ . '/config.php';

try {
    $pdo = db();
    echo "DB OK<br>\n";
    foreach ($pdo->query("SHOW TABLES") as $row) {
        echo htmlspecialchars($row[0]) . "<br>\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "DB ERROR";
}
