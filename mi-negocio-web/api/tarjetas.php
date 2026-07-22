<?php
// api/tarjetas.php - VERSIÓN COMPLETA Y CORREGIDA
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/db.php';

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

header('Access-Control-Allow-Origin: http://localhost:8080');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($method === 'OPTIONS') {
    exit;
}

switch($method) {
    case 'GET':
        $result = $db->query("SELECT * FROM tarjetas_pago WHERE activo = 1 ORDER BY propietario ASC");
        $tarjetas = [];
        while($row = $result->fetch_assoc()) {
            $tarjetas[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $tarjetas]);
        break;
        
    case 'POST':
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if(empty($data['propietario']) || empty($data['numero_cuenta'])) {
            echo json_encode(['success' => false, 'message' => 'Propietario y número de cuenta son obligatorios']);
            exit;
        }
        
        $propietario = trim($data['propietario']);
        $numero_cuenta = trim($data['numero_cuenta']);
        $moneda = isset($data['moneda']) ? $data['moneda'] : 'CUP';
        $telefono = isset($data['telefono']) ? trim($data['telefono']) : '';
        $banco = isset($data['banco']) ? trim($data['banco']) : '';
        
        $stmt = $db->prepare("
            INSERT INTO tarjetas_pago (propietario, numero_cuenta, moneda, telefono, banco) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssss", $propietario, $numero_cuenta, $moneda, $telefono, $banco);
        
        if($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'id' => $db->insert_id,
                'message' => 'Tarjeta creada correctamente'
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Error al crear: ' . $stmt->error
            ]);
        }
        break;
}
?>