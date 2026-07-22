<?php
// api/categorias.php - VERSIÓN CORREGIDA
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/db.php';

// Verificar autenticación
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Permitir solicitudes desde el mismo origen
header('Access-Control-Allow-Origin: http://localhost:8081');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($method === 'OPTIONS') {
    exit;
}

switch($method) {
    case 'GET':
        $result = $db->query("
            SELECT c.*, COUNT(p.id) as total_productos
            FROM categorias c
            LEFT JOIN productos p ON p.categoria_id = c.id
            GROUP BY c.id
            ORDER BY c.nombre ASC
        ");
        
        $categorias = [];
        while($row = $result->fetch_assoc()) {
            $categorias[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $categorias]);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if(empty($data['nombre'])) {
            echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']);
            exit;
        }
        
        // PREPARAR VARIABLES - TODAS DEBEN SER STRINGS
        $nombre = trim($data['nombre']);
        $descripcion = isset($data['descripcion']) ? trim($data['descripcion']) : '';
        
        // Verificar si ya existe
        $stmt = $db->prepare("SELECT id FROM categorias WHERE nombre = ?");
        $stmt->bind_param("s", $nombre);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo json_encode([
                'success' => true,
                'message' => 'La categoría ya existe',
                'id' => $row['id'],
                'exists' => true
            ]);
            exit;
        }
        
        // CREAR NUEVA CATEGORÍA - CON VARIABLES PREPARADAS
        $stmt = $db->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)");
        $stmt->bind_param("ss", $nombre, $descripcion);
        
        if($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Categoría creada correctamente',
                'id' => $db->insert_id,
                'exists' => false
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Error al crear: ' . $stmt->error
            ]);
        }
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id']);
        
        // Verificar si tiene productos
        $result = $db->query("SELECT COUNT(*) as total FROM productos WHERE categoria_id = $id");
        $total = $result->fetch_assoc()['total'];
        
        if($total > 0) {
            echo json_encode([
                'success' => false,
                'message' => "No se puede eliminar: la categoría tiene $total productos asociados"
            ]);
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM categorias WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Categoría eliminada correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
        }
        break;
}
?>