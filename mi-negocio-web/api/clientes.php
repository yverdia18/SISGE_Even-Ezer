<?php
// api/clientes.php - VERSIÓN COMPLETA Y CORREGIDA
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/db.php';

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

header('Access-Control-Allow-Origin: http://localhost:8081');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($method === 'OPTIONS') {
    exit;
}

switch($method) {
    case 'GET':
        if(isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $result = $db->query("SELECT * FROM clientes WHERE id = $id");
            echo json_encode(['success' => true, 'data' => $result->fetch_assoc()]);
        } else {
            $result = $db->query("SELECT * FROM clientes ORDER BY nombre ASC");
            $clientes = [];
            while($row = $result->fetch_assoc()) {
                $clientes[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $clientes]);
        }
        break;
        
    case 'POST':
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if(empty($data['nombre'])) {
            echo json_encode(['success' => false, 'message' => 'El nombre del proveedor es obligatorio']);
            exit;
        }
        
        $nombre = trim($data['nombre']);
        $apellidos = isset($data['apellidos']) ? trim($data['apellidos']) : '';
        $telefono = isset($data['telefono']) ? trim($data['telefono']) : '';
        $email = isset($data['email']) ? trim($data['email']) : '';
        $area_trabajo = isset($data['area_trabajo']) ? trim($data['area_trabajo']) : '';
        $es_proveedor = 1;
        $tipo_proveedor = isset($data['tipo_proveedor']) ? trim($data['tipo_proveedor']) : 'distribuidor';
        
        // Verificar si ya existe
        $stmt = $db->prepare("SELECT id FROM clientes WHERE nombre = ? AND apellidos = ?");
        $stmt->bind_param("ss", $nombre, $apellidos);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo json_encode([
                'success' => true,
                'message' => 'Proveedor ya existe',
                'id' => $row['id'],
                'exists' => true
            ]);
            exit;
        }
        
        $stmt = $db->prepare("
            INSERT INTO clientes (nombre, apellidos, telefono, email, area_trabajo, es_proveedor, tipo_proveedor) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "sssssis",
            $nombre,
            $apellidos,
            $telefono,
            $email,
            $area_trabajo,
            $es_proveedor,
            $tipo_proveedor
        );
        
        if($stmt->execute()) {
            $id = $db->insert_id;
            echo json_encode([
                'success' => true,
                'message' => 'Proveedor creado correctamente',
                'id' => $id
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Error al guardar: ' . $stmt->error
            ]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        break;
}
?>