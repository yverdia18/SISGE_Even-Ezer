<?php
// test_dashboard.php - Prueba simple
session_start();
echo "Sesión iniciada: " . print_r($_SESSION, true);
echo "<br>Usuario: " . ($_SESSION['user_name'] ?? 'No logueado');
?>