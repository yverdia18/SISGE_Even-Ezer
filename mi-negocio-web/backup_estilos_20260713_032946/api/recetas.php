<?php
// api/recetas.php - VERSIÓN CORREGIDA
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
header('Access-Control-Allow-Methods: GET, POST, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($method === 'OPTIONS') {
    exit;
}

switch($method) {
    case 'GET':
        if(isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $result = $db->query("
                SELECT r.*, p.nombre as producto_nombre
                FROM recetas r
                LEFT JOIN productos p ON r.producto_id = p.id
                WHERE r.id = $id AND r.activo = 1 AND (r.eliminado = 0 OR r.eliminado IS NULL)
            ");
            $receta = $result->fetch_assoc();
            
            if($receta) {
                $ingredientes = $db->query("
                    SELECT ri.*, p.nombre as ingrediente_nombre, p.stock as stock_disponible, p.unidad_base
                    FROM receta_ingredientes ri
                    LEFT JOIN productos p ON ri.ingrediente_id = p.id
                    WHERE ri.receta_id = $id
                ");
                $receta['ingredientes'] = [];
                while($row = $ingredientes->fetch_assoc()) {
                    $receta['ingredientes'][] = $row;
                }
                echo json_encode(['success' => true, 'data' => $receta]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Receta no encontrada']);
            }
        } else {
            $result = $db->query("
                SELECT r.*, p.nombre as producto_nombre
                FROM recetas r
                LEFT JOIN productos p ON r.producto_id = p.id
                WHERE r.activo = 1 AND (r.eliminado = 0 OR r.eliminado IS NULL)
                ORDER BY r.nombre ASC
            ");
            $recetas = [];
            while($row = $result->fetch_assoc()) {
                $recetas[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $recetas]);
        }
        break;
        
    case 'POST':
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if(empty($data['nombre'])) {
            echo json_encode(['success' => false, 'message' => 'El nombre de la receta es obligatorio']);
            exit;
        }
        
        if(empty($data['ingredientes']) || count($data['ingredientes']) === 0) {
            echo json_encode(['success' => false, 'message' => 'Agregue al menos un ingrediente']);
            exit;
        }
        
        $db->begin_transaction();
        
        try {
            // Calcular costo total
            $costo_total = 0;
            foreach($data['ingredientes'] as $ing) {
                $costo_total += $ing['costo_total'];
            }
            
            $margen = isset($data['margen_ganancia']) ? floatval($data['margen_ganancia']) : 50;
            $precio_sugerido = $costo_total * (1 + ($margen / 100));
            
            // Preparar variables
            $nombre = trim($data['nombre']);
            $descripcion = isset($data['descripcion']) ? trim($data['descripcion']) : '';
            $producto_id = !empty($data['producto_id']) ? intval($data['producto_id']) : null;
            $producto_id_bind = $producto_id ?? 0;
            $rendimiento = isset($data['rendimiento']) ? intval($data['rendimiento']) : 1;
            $unidad_medida = isset($data['unidad_medida']) ? $data['unidad_medida'] : 'racion';
            $tiempo_preparacion = isset($data['tiempo_preparacion']) ? intval($data['tiempo_preparacion']) : 0;
            $instrucciones = isset($data['instrucciones']) ? trim($data['instrucciones']) : '';
            $costo_total_dec = floatval($costo_total);
            $precio_sugerido_dec = floatval($precio_sugerido);
            $margen_ganancia = floatval($margen);
            $activo = 1;
            
            if(isset($data['id']) && $data['id'] > 0) {
                // =============================================
                // ACTUALIZAR
                // =============================================
                $id = intval($data['id']);
                
                $sql = "
                    UPDATE recetas SET 
                        nombre = ?,
                        descripcion = ?,
                        producto_id = ?,
                        rendimiento = ?,
                        unidad_medida = ?,
                        tiempo_preparacion = ?,
                        instrucciones = ?,
                        costo_total = ?,
                        precio_venta_sugerido = ?,
                        margen_ganancia = ?
                    WHERE id = ?
                ";
                
                $stmt = $db->prepare($sql);
                $stmt->bind_param(
                    "ssiisisdddi",
                    $nombre,
                    $descripcion,
                    $producto_id_bind,
                    $rendimiento,
                    $unidad_medida,
                    $tiempo_preparacion,
                    $instrucciones,
                    $costo_total_dec,
                    $precio_sugerido_dec,
                    $margen_ganancia,
                    $id
                );
                $stmt->execute();
                
                // =============================================
                // ELIMINAR INGREDIENTES ANTIGUOS - SIEMPRE
                // =============================================
                $db->query("DELETE FROM receta_ingredientes WHERE receta_id = $id");
                
            } else {
                // =============================================
                // CREAR
                // =============================================
                $sql = "
                    INSERT INTO recetas (
                        nombre, descripcion, producto_id, rendimiento, unidad_medida,
                        tiempo_preparacion, instrucciones, costo_total, precio_venta_sugerido,
                        margen_ganancia, activo
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                
                $stmt = $db->prepare($sql);
                $stmt->bind_param(
                    "ssiisisdddi",
                    $nombre,
                    $descripcion,
                    $producto_id_bind,
                    $rendimiento,
                    $unidad_medida,
                    $tiempo_preparacion,
                    $instrucciones,
                    $costo_total_dec,
                    $precio_sugerido_dec,
                    $margen_ganancia,
                    $activo
                );
                $stmt->execute();
                $id = $db->insert_id;
            }
            
            // =============================================
            // INSERTAR INGREDIENTES (NUEVOS)
            // =============================================
            foreach($data['ingredientes'] as $ing) {
                $ingrediente_id = intval($ing['ingrediente_id']);
                $cantidad = floatval($ing['cantidad']);
                $unidad = isset($ing['unidad']) ? $ing['unidad'] : 'g';
                $costo_unitario = floatval($ing['costo_unitario']);
                $costo_total_ing = floatval($ing['costo_total']);
                
                $stmt = $db->prepare("
                    INSERT INTO receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, costo_unitario, costo_total)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "iidssd",
                    $id,
                    $ingrediente_id,
                    $cantidad,
                    $unidad,
                    $costo_unitario,
                    $costo_total_ing
                );
                $stmt->execute();
            }
            
            // Actualizar producto final
            if($producto_id) {
                $stmt = $db->prepare("
                    UPDATE productos SET 
                        precio_venta = ?,
                        costo_produccion = ?,
                        tiene_receta = 1,
                        tipo_producto = 'compuesto'
                    WHERE id = ?
                ");
                $stmt->bind_param("ddi", $precio_sugerido_dec, $costo_total_dec, $producto_id);
                $stmt->execute();
            }
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Receta guardada correctamente',
                'id' => $id,
                'costo_total' => $costo_total,
                'precio_sugerido' => $precio_sugerido
            ]);
            
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id']);
        
        $stmt = $db->prepare("UPDATE recetas SET activo = 0, eliminado = 1, fecha_eliminacion = NOW(), eliminado_por = ? WHERE id = ?");
        $stmt->bind_param("ii", $_SESSION['user_id'], $id);
        $stmt->execute();
        
        $datos = $db->query("SELECT * FROM recetas WHERE id = $id")->fetch_assoc();
        $stmt2 = $db->prepare("
            INSERT INTO papelera (tabla_origen, registro_id, datos, eliminado_por)
            VALUES ('recetas', ?, ?, ?)
        ");
        $json_datos = json_encode($datos);
        $stmt2->bind_param("isi", $id, $json_datos, $_SESSION['user_id']);
        $stmt2->execute();
        
        echo json_encode(['success' => true, 'message' => 'Receta eliminada correctamente']);
        break;
        
    case 'PATCH':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if(isset($data['action']) && $data['action'] === 'producir') {
            $receta_id = intval($data['receta_id']);
            
            $result = $db->query("
                SELECT r.*, p.nombre as producto_nombre
                FROM recetas r
                LEFT JOIN productos p ON r.producto_id = p.id
                WHERE r.id = $receta_id AND r.activo = 1 AND (r.eliminado = 0 OR r.eliminado IS NULL)
            ");
            $receta = $result->fetch_assoc();
            
            if(!$receta) {
                echo json_encode(['success' => false, 'message' => 'Receta no encontrada']);
                exit;
            }
            
            $ingredientes = $db->query("
                SELECT ri.*, p.nombre as ingrediente_nombre, p.stock
                FROM receta_ingredientes ri
                LEFT JOIN productos p ON ri.ingrediente_id = p.id
                WHERE ri.receta_id = $receta_id
            ");
            
            $db->begin_transaction();
            
            try {
                $produccion = 1;
                $stock_actual = 0;
                
                while($ing = $ingredientes->fetch_assoc()) {
                    $necesario = $ing['cantidad'] * $produccion;
                    
                    if($ing['stock'] < $necesario) {
                        throw new Exception("Stock insuficiente de {$ing['ingrediente_nombre']}. Disponible: {$ing['stock']}, Necesario: $necesario");
                    }
                    
                    $stmt = $db->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
                    $stmt->bind_param("di", $necesario, $ing['ingrediente_id']);
                    $stmt->execute();
                }
                
                if(!empty($receta['producto_id'])) {
                    $stmt = $db->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
                    $stmt->bind_param("ii", $produccion, $receta['producto_id']);
                    $stmt->execute();
                    
                    $result = $db->query("SELECT stock FROM productos WHERE id = {$receta['producto_id']}");
                    $stock_actual = $result->fetch_assoc()['stock'];
                }
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Producción completada',
                    'cantidad_producida' => $produccion,
                    'stock_actual' => $stock_actual
                ]);
                
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        }
        break;
}
?>