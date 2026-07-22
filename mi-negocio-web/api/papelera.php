<?php
// api/papelera.php - API DE PAPELERA
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/db.php';

// Verificar autenticación y rol
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if($_SESSION['user_rol'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Permisos insuficientes']);
    exit;
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

header('Access-Control-Allow-Origin: http://localhost:8081');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($method === 'OPTIONS') {
    exit;
}

switch($method) {
    case 'GET':
        $result = $db->query("
            SELECT p.*, u.nombre as eliminado_por_nombre
            FROM papelera p
            LEFT JOIN usuarios u ON p.eliminado_por = u.id
            WHERE p.restaurado = 0
            ORDER BY p.fecha_eliminacion DESC
            LIMIT 100
        ");
        $items = [];
        while($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $items]);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if(isset($data['action']) && $data['action'] === 'restaurar') {
            $id = intval($data['id']);
            
            $result = $db->query("SELECT * FROM papelera WHERE id = $id AND restaurado = 0");
            if($result->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Elemento no encontrado']);
                exit;
            }
            
            $item = $result->fetch_assoc();
            
            // Verificar que sea una compra
            if($item['tabla_origen'] !== 'compras') {
                echo json_encode(['success' => false, 'message' => 'Solo se pueden restaurar compras desde aquí']);
                exit;
            }
            
            $datos = json_decode($item['datos'], true);
            $compra = $datos['compra'] ?? [];
            $detalles = $datos['detalles'] ?? [];
            
            if(empty($compra)) {
                echo json_encode(['success' => false, 'message' => 'Datos de compra corruptos']);
                exit;
            }
            
            $db->begin_transaction();
            
            try {
                $registro_id = $item['registro_id'];
                
                // 1. Restaurar la compra (quitar soft delete)
                $stmt = $db->prepare("
                    UPDATE compras SET 
                        eliminado = 0,
                        fecha_eliminacion = NULL,
                        eliminado_por = NULL,
                        estado = estado_anterior,
                        estado_anterior = NULL
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $registro_id);
                $stmt->execute();
                
                // 2. Restaurar stock de los productos
                foreach($detalles as $detalle) {
                    $stmt = $db->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
                    $stmt->bind_param("ii", $detalle['cantidad'], $detalle['producto_id']);
                    $stmt->execute();
                }
                
                // 3. Marcar como restaurado en papelera
                $stmt = $db->prepare("UPDATE papelera SET restaurado = 1, fecha_restauracion = NOW() WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                
                $db->commit();
                
                echo json_encode([
                    'success' => true, 
                    'message' => '✅ Compra restaurada correctamente. El stock ha sido ajustado.'
                ]);
                
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
        }
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if(isset($data['action']) && $data['action'] === 'vaciar') {
            // Vaciar toda la papelera
            $db->query("DELETE FROM papelera WHERE restaurado = 0");
            echo json_encode(['success' => true, 'message' => 'Papelera vaciada correctamente']);
            
        } elseif(isset($data['id'])) {
            $id = intval($data['id']);
            
            $result = $db->query("SELECT * FROM papelera WHERE id = $id AND restaurado = 0");
            if($result->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Elemento no encontrado']);
                exit;
            }
            
            $item = $result->fetch_assoc();
            
            if($item['tabla_origen'] === 'compras') {
                // Eliminación definitiva de compra - LOS DATOS SE PIERDEN PERMANENTEMENTE
                $datos = json_decode($item['datos'], true);
                $compra = $datos['compra'] ?? [];
                $detalles = $datos['detalles'] ?? [];
                
                $db->begin_transaction();
                
                try {
                    // 1. Eliminar detalles de compra
                    $db->query("DELETE FROM compra_detalles WHERE compra_id = {$item['registro_id']}");
                    
                    // 2. Eliminar pagos asociados
                    $db->query("DELETE FROM pagos_compras WHERE compra_id = {$item['registro_id']}");
                    
                    // 3. Eliminar historial
                    $db->query("DELETE FROM historial_compras WHERE compra_id = {$item['registro_id']}");
                    
                    // 4. Eliminar la compra
                    $db->query("DELETE FROM compras WHERE id = {$item['registro_id']}");
                    
                    // 5. Eliminar de la papelera
                    $db->query("DELETE FROM papelera WHERE id = $id");
                    
                    $db->commit();
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => '✅ Compra eliminada definitivamente. Los datos NO se pueden recuperar.'
                    ]);
                    
                } catch (Exception $e) {
                    $db->rollback();
                    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                }
            } else {
                // Otros elementos
                $db->query("DELETE FROM papelera WHERE id = $id");
                echo json_encode(['success' => true, 'message' => 'Elemento eliminado definitivamente']);
            }
        }
        break;
}
?>