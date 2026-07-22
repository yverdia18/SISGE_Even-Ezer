<?php
// test_api.php
echo "1. Probando conexión a la base de datos...<br>";
require_once 'includes/config.php';
require_once 'includes/db.php';

$db = Database::getInstance()->getConnection();
echo "✅ Conexión exitosa<br><br>";

echo "2. Probando API de productos...<br>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8081/mi-negocio-web/api/productos.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

echo "Respuesta del API: <br>";
echo "<pre>";
print_r(json_decode($response, true));
echo "</pre>";
?>  