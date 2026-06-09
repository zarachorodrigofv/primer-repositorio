<?php
$conn = new mysqli("sql103.infinityfree.com", "if0_39451587", "Practicastec6", "if0_39451587_campus");

if ($conn->connect_error) {
    die("❌ Error de conexión: " . $conn->connect_error);
}
echo "✅ Conectado correctamente a la base de datos.";
?>
