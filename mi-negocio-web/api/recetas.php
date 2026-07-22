<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/config.php';
require_once '../includes/db.php';

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// ============================================================
// FUNCIÓN PARA RESPONDER CON ERROR
// ============================================================
function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

switch($method) {
    case 'GET':
        if(isset($_GET['id'])) {
            $id = intval($_GET['id']);
            
            // Obtener receta
            $result = $db->query("
                SELECT r.*, p.nombre as producto_nombre 
                FROM recetas r
                LEFT JOIN productos p ON r.producto_id = p.id
                WHERE r.id = $id AND r.eliminado = 0
            ");
            
            if(!$result || $result->num_rows === 0) {
                sendError('Receta no encontrada', 404);
            }
            
            $receta = $result->fetch_assoc();
            
            // Obtener ingredientes
            $ingredientes = $db->query("
                SELECT ri.*, p.nombre as ingrediente_nombre, p.stock, p.unidad_base
                FROM receta_ingredientes ri
                LEFT JOIN productos p ON ri.ingrediente_id = p.id
                WHERE ri.receta_id = $id
            ");
            
            $receta['ingredientes'] = [];
            if($ingredientes) {
                while($row = $ingredientes->fetch_assoc()) {
                    $receta['ingredientes'][] = $row;
                }
            }
            
            echo json_encode($receta);
        } else {
            // Listar todas las recetas
            $result = $db->query("
                SELECT r.*, p.nombre as producto_nombre 
                FROM recetas r
                LEFT JOIN productos p ON r.producto_id = p.id
                WHERE r.eliminado = 0 AND r.activo = 1
                ORDER BY r.nombre ASC
            ");
            
            $recetas = [];
            if($result) {
                while($row = $result->fetch_assoc()) {
                    $recetas[] = $row;
                }
            }
            echo json_encode($recetas);
        }
        break;

    case 'POST':
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if(!$data) {
            sendError('Datos inválidos. JSON mal formado: ' . json_last_error_msg());
        }
        
        // Validar datos básicos
        if(!isset($data['nombre']) || empty(trim($data['nombre']))) {
            sendError('El nombre de la receta es obligatorio');
        }
        
        // ✅ CORREGIDO: usar real_escape_string en lugar de escape()
        $nombre = $db->real_escape_string(trim($data['nombre']));
        $descripcion = $db->real_escape_string($data['descripcion'] ?? '');
        $producto_id = isset($data['producto_id']) && $data['producto_id'] ? intval($data['producto_id']) : null;
        $rendimiento = floatval($data['rendimiento'] ?? 1);
        $unidad_medida = $db->real_escape_string($data['unidad_medida'] ?? 'racion');
        $tiempo_preparacion = intval($data['tiempo_preparacion'] ?? 0);
        $instrucciones = $db->real_escape_string($data['instrucciones'] ?? '');
        $margen_ganancia = floatval($data['margen_ganancia'] ?? 0);
        $ingredientes = $data['ingredientes'] ?? [];
        
        // Validar rendimiento
        if($rendimiento <= 0) {
            sendError('El rendimiento debe ser mayor a 0');
        }
        
        // Calcular costos
        $costo_total = 0;
        foreach($ingredientes as $ing) {
            $ingrediente_id = intval($ing['ingrediente_id'] ?? 0);
            $cantidad = floatval($ing['cantidad'] ?? 0);
            $merma = floatval($ing['merma'] ?? 0);
            
            if($ingrediente_id > 0 && $cantidad > 0) {
                $prod_result = $db->query("
                    SELECT precio_compra, contenido_unidad 
                    FROM productos 
                    WHERE id = $ingrediente_id
                ");
                $producto = $prod_result->fetch_assoc();
                
                if($producto) {
                    $precio_compra = floatval($producto['precio_compra']);
                    $contenido_unidad = floatval($producto['contenido_unidad'] ?? 1);
                    
                    if($contenido_unidad > 0) {
                        $costo_porcion = ($cantidad / $contenido_unidad) * $precio_compra;
                        $costo_total += $costo_porcion * (1 + $merma / 100);
                    }
                }
            }
        }
        
        $costo_unitario = $rendimiento > 0 ? $costo_total / $rendimiento : 0;
        $precio_venta_sugerido = ($margen_ganancia > 0 && $costo_unitario > 0) ? $costo_unitario / (1 - ($margen_ganancia / 100)) : 0;
        
        // Guardar receta
        if(isset($data['id']) && $data['id'] > 0) {
            // ACTUALIZAR
            $id = intval($data['id']);
            $stmt = $db->prepare("
                UPDATE recetas SET 
                    nombre = ?,
                    descripcion = ?,
                    producto_id = ?,
                    rendimiento = ?,
                    unidad_medida = ?,
                    tiempo_preparacion = ?,
                    instrucciones = ?,
                    costo_total_produccion = ?,
                    costo_unitario_produccion = ?,
                    costo_total = ?,
                    precio_venta_sugerido = ?,
                    margen_ganancia = ?
                WHERE id = ?
            ");
            
            if(!$stmt) {
                sendError('Error en prepare: ' . $db->error);
            }
            
            $stmt->bind_param(
                "ssiisisddsddi",
                $nombre,
                $descripcion,
                $producto_id,
                $rendimiento,
                $unidad_medida,
                $tiempo_preparacion,
                $instrucciones,
                $costo_total,
                $costo_unitario,
                $costo_total,
                $precio_venta_sugerido,
                $margen_ganancia,
                $id
            );
        } else {
            // CREAR NUEVA
            $stmt = $db->prepare("
                INSERT INTO recetas (
                    nombre, 
                    descripcion, 
                    producto_id, 
                    rendimiento, 
                    unidad_medida,
                    tiempo_preparacion,
                    instrucciones,
                    costo_total_produccion,
                    costo_unitario_produccion,
                    costo_total,
                    precio_venta_sugerido,
                    margen_ganancia,
                    activo
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            if(!$stmt) {
                sendError('Error en prepare: ' . $db->error);
            }
            
            $stmt->bind_param(
                "ssiisisddsdd",
                $nombre,
                $descripcion,
                $producto_id,
                $rendimiento,
                $unidad_medida,
                $tiempo_preparacion,
                $instrucciones,
                $costo_total,
                $costo_unitario,
                $costo_total,
                $precio_venta_sugerido,
                $margen_ganancia
            );
        }
        
        if(!$stmt->execute()) {
            sendError('Error al guardar: ' . $stmt->error);
        }
        
        $receta_id = isset($data['id']) && $data['id'] > 0 ? $data['id'] : $db->insert_id;
        
        // Guardar ingredientes
        if(isset($data['id']) && $data['id'] > 0) {
            $db->query("DELETE FROM receta_ingredientes WHERE receta_id = $receta_id");
        }
        
        foreach($ingredientes as $ing) {
            $ingrediente_id = intval($ing['ingrediente_id'] ?? 0);
            $cantidad = floatval($ing['cantidad'] ?? 0);
            $unidad = $db->real_escape_string($ing['unidad'] ?? 'g');
            $merma = floatval($ing['merma'] ?? 0);
            
            if($ingrediente_id > 0 && $cantidad > 0) {
                // Calcular costo para este ingrediente
                $prod_result = $db->query("
                    SELECT precio_compra, contenido_unidad 
                    FROM productos 
                    WHERE id = $ingrediente_id
                ");
                $producto = $prod_result->fetch_assoc();
                
                if($producto) {
                    $precio_compra = floatval($producto['precio_compra']);
                    $contenido_unidad = floatval($producto['contenido_unidad'] ?? 1);
                    
                    if($contenido_unidad > 0) {
                        $costo_porcion = ($cantidad / $contenido_unidad) * $precio_compra;
                        $costo_total_ing = $costo_porcion * (1 + $merma / 100);
                    } else {
                        $costo_porcion = 0;
                        $costo_total_ing = 0;
                    }
                } else {
                    $costo_porcion = 0;
                    $costo_total_ing = 0;
                }
                
                $stmt_ing = $db->prepare("
                    INSERT INTO receta_ingredientes (
                        receta_id,
                        ingrediente_id,
                        cantidad,
                        unidad,
                        costo_porcion,
                        costo_unitario,
                        precio_unitario,
                        costo_total,
                        merma
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt_ing->bind_param(
                    "iidsddddd",
                    $receta_id,
                    $ingrediente_id,
                    $cantidad,
                    $unidad,
                    $costo_porcion,
                    $costo_porcion,
                    $costo_porcion,
                    $costo_total_ing,
                    $merma
                );
                $stmt_ing->execute();
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Receta guardada correctamente',
            'id' => $receta_id,
            'costo_total' => $costo_total,
            'costo_unitario' => $costo_unitario,
            'precio_venta_sugerido' => $precio_venta_sugerido
        ]);
        break;

    case 'PATCH':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if(!isset($data['action']) || $data['action'] !== 'producir') {
            sendError('Acción no válida');
        }
        
        $receta_id = intval($data['receta_id'] ?? 0);
        if($receta_id <= 0) {
            sendError('ID de receta inválido');
        }
        
        // Obtener receta
        $result = $db->query("
            SELECT r.*, p.nombre as producto_nombre, p.unidad_base as producto_unidad_base, p.stock as producto_stock
            FROM recetas r
            LEFT JOIN productos p ON r.producto_id = p.id
            WHERE r.id = $receta_id AND r.activo = 1 AND (r.eliminado = 0 OR r.eliminado IS NULL)
        ");
        $receta = $result->fetch_assoc();
        
        if(!$receta) {
            sendError('Receta no encontrada', 404);
        }
        
        if(empty($receta['producto_id'])) {
            sendError('Esta receta no tiene un producto final asociado');
        }
        
        $rendimiento = floatval($receta['rendimiento'] ?? 1);
        
        // Obtener ingredientes
        $ingredientes = $db->query("
            SELECT ri.*, p.nombre as ingrediente_nombre, p.stock, p.unidad_base
            FROM receta_ingredientes ri
            LEFT JOIN productos p ON ri.ingrediente_id = p.id
            WHERE ri.receta_id = $receta_id
        ");
        
        if($ingredientes->num_rows === 0) {
            sendError('La receta no tiene ingredientes');
        }
        
        $db->begin_transaction();
        
        try {
            // Descontar ingredientes
            while($ing = $ingredientes->fetch_assoc()) {
                $cantidad_necesaria = floatval($ing['cantidad']);
                $stock_disponible = floatval($ing['stock'] ?? 0);
                
                if($stock_disponible < $cantidad_necesaria) {
                    throw new Exception("Stock insuficiente de {$ing['ingrediente_nombre']}. Disponible: $stock_disponible, Necesario: $cantidad_necesaria");
                }
                
                $stmt = $db->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
                $stmt->bind_param("di", $cantidad_necesaria, $ing['ingrediente_id']);
                $stmt->execute();
            }
            
            // Aumentar stock del producto final
            $producto_id = $receta['producto_id'];
            $cantidad_producida = $rendimiento;
            
            $stmt = $db->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
            $stmt->bind_param("di", $cantidad_producida, $producto_id);
            $stmt->execute();
            
            // Obtener stock actualizado
            $result = $db->query("SELECT stock FROM productos WHERE id = $producto_id");
            $stock_actual = $result->fetch_assoc()['stock'];
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => '✅ Producción completada',
                'cantidad_producida' => $cantidad_producida,
                'producto_id' => $producto_id,
                'producto_nombre' => $receta['producto_nombre'],
                'stock_actual' => $stock_actual,
                'unidad_base' => $receta['producto_unidad_base'] ?? 'unidad'
            ]);
            
        } catch (Exception $e) {
            $db->rollback();
            sendError('Error en producción: ' . $e->getMessage());
        }
        break;

    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        
        if($id <= 0) {
            sendError('ID inválido');
        }
        
        $stmt = $db->prepare("UPDATE recetas SET eliminado = 1, fecha_eliminacion = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Receta eliminada correctamente']);
        } else {
            sendError('Error al eliminar: ' . $stmt->error);
        }
        break;

    default:
        sendError('Método no permitido', 405);
}
?>