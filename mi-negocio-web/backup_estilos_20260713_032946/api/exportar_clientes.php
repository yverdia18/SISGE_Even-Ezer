<?php
// api/exportar_clientes.php
require_once '../includes/config.php';
require_once '../includes/db.php';

// Verificar autenticación
if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// Obtener clientes
$result = $db->query("
    SELECT 
        id,
        nombre,
        apellidos,
        telefono,
        telefono2,
        email,
        cuenta_bancaria,
        banco,
        area_trabajo,
        puesto,
        direccion,
        ciudad,
        estado,
        codigo_postal,
        notas,
        created_at as fecha_registro
    FROM clientes 
    ORDER BY nombre ASC
");

// Configurar cabeceras para descargar CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="clientes_' . date('Y-m-d') . '.csv"');

// Crear archivo CSV
$output = fopen('php://output', 'w');
fputcsv($output, [
    'ID', 'Nombre', 'Apellidos', 'Teléfono', 'Teléfono 2', 
    'Email', 'Cuenta Bancaria', 'Banco', 'Área de Trabajo', 
    'Puesto', 'Dirección', 'Ciudad', 'Estado', 'Código Postal',
    'Notas', 'Fecha Registro'
]);

while($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
exit;
?>