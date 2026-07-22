<?php
// api/clientes.php
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

switch($method) {
    case 'GET':
        // Obtener cliente específico
        if(isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $result = $db->query("
                SELECT c.*, 
                       (SELECT COUNT(*) FROM ventas v WHERE v.cliente_id = c.id) as total_compras,
                       (SELECT SUM(total) FROM ventas v WHERE v.cliente_id = c.id) as total_gastado
                FROM clientes c 
                WHERE c.id = $id
            ");
            $cliente = $result->fetch_assoc();
            
            if($cliente) {
                // Obtener historial de ventas
                $ventas = $db->query("
                    SELECT v.*, 
                           (SELECT COUNT(*) FROM venta_detalles vd WHERE vd.venta_id = v.id) as total_productos
                    FROM ventas v 
                    WHERE v.cliente_id = $id 
                    ORDER BY v.fecha DESC 
                    LIMIT 10
                ");
                $cliente['ventas'] = [];
                while($row = $ventas->fetch_assoc()) {
                    $cliente['ventas'][] = $row;
                }
                
                echo json_encode(['success' => true, 'data' => $cliente]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
            }
        } else {
            // Listar todos los clientes
            $result = $db->query("
                SELECT c.*, 
                       (SELECT COUNT(*) FROM ventas v WHERE v.cliente_id = c.id) as total_compras
                FROM clientes c 
                ORDER BY c.nombre ASC
            ");
            $clientes = [];
            while($row = $result->fetch_assoc()) {
                $clientes[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $clientes]);
        }
        break;
        
    case 'POST':
        // Crear o actualizar cliente
        $data = json_decode(file_get_contents('php://input'), true);
        
        if(empty($data['nombre'])) {
            echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']);
            exit;
        }
        
        if(isset($data['id']) && $data['id'] > 0) {
            // Actualizar cliente existente
            $stmt = $db->prepare("
                UPDATE clientes SET 
                    nombre = ?,
                    apellidos = ?,
                    telefono = ?,
                    telefono2 = ?,
                    email = ?,
                    cuenta_bancaria = ?,
                    banco = ?,
                    area_trabajo = ?,
                    puesto = ?,
                    direccion = ?,
                    direccion2 = ?,
                    ciudad = ?,
                    estado = ?,
                    codigo_postal = ?,
                    notas = ?,
                    redes_sociales = ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                "ssssssssssssssssi",
                $data['nombre'],
                $data['apellidos'] ?? '',
                $data['telefono'] ?? '',
                $data['telefono2'] ?? '',
                $data['email'] ?? '',
                $data['cuenta_bancaria'] ?? '',
                $data['banco'] ?? '',
                $data['area_trabajo'] ?? '',
                $data['puesto'] ?? '',
                $data['direccion'] ?? '',
                $data['direccion2'] ?? '',
                $data['ciudad'] ?? '',
                $data['estado'] ?? '',
                $data['codigo_postal'] ?? '',
                $data['notas'] ?? '',
                $data['redes_sociales'] ?? '',
                $data['id']
            );
        } else {
            // Crear nuevo cliente
            $stmt = $db->prepare("
                INSERT INTO clientes (
                    nombre, apellidos, telefono, telefono2, email, 
                    cuenta_bancaria, banco, area_trabajo, puesto,
                    direccion, direccion2, ciudad, estado, codigo_postal,
                    notas, redes_sociales
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "ssssssssssssssss",
                $data['nombre'],
                $data['apellidos'] ?? '',
                $data['telefono'] ?? '',
                $data['telefono2'] ?? '',
                $data['email'] ?? '',
                $data['cuenta_bancaria'] ?? '',
                $data['banco'] ?? '',
                $data['area_trabajo'] ?? '',
                $data['puesto'] ?? '',
                $data['direccion'] ?? '',
                $data['direccion2'] ?? '',
                $data['ciudad'] ?? '',
                $data['estado'] ?? '',
                $data['codigo_postal'] ?? '',
                $data['notas'] ?? '',
                $data['redes_sociales'] ?? ''
            );
        }
        
        if($stmt->execute()) {
            $id = $data['id'] ?? $db->insert_id;
            echo json_encode([
                'success' => true, 
                'message' => 'Cliente guardado correctamente',
                'id' => $id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $stmt->error]);
        }
        break;
        
    case 'DELETE':
        // Eliminar cliente
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id']);
        
        $stmt = $db->prepare("DELETE FROM clientes WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Cliente eliminado']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
        }
        break;
}
?>