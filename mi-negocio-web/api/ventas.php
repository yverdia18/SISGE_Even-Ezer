<?php
// api/ventas.php - API DE VENTAS CON CONVERSIONES DE UNIDADES
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
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($method === 'OPTIONS') {
    exit;
}

switch($method) {
    case 'GET':
        if(isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $result = $db->query("
                SELECT v.*, c.nombre as cliente_nombre, c.apellidos as cliente_apellidos,
                    u.nombre as usuario_nombre
                FROM ventas v
                LEFT JOIN clientes c ON v.cliente_id = c.id
                LEFT JOIN usuarios u ON v.usuario_id = u.id
                WHERE v.id = $id
            ");
            $venta = $result->fetch_assoc();
            
            if($venta) {
                $detalles = $db->query("
                    SELECT vd.*, p.nombre as producto_nombre
                    FROM venta_detalles vd
                    LEFT JOIN productos p ON vd.producto_id = p.id
                    WHERE vd.venta_id = $id
                ");
                $venta['detalles'] = [];
                while($row = $detalles->fetch_assoc()) {
                    $venta['detalles'][] = $row;
                }
                echo json_encode(['success' => true, 'data' => $venta]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Venta no encontrada']);
            }
        } else {
            $result = $db->query("
                SELECT v.*, c.nombre as cliente_nombre, c.apellidos as cliente_apellidos,
                    u.nombre as usuario_nombre
                FROM ventas v
                LEFT JOIN clientes c ON v.cliente_id = c.id
                LEFT JOIN usuarios u ON v.usuario_id = u.id
                ORDER BY v.fecha DESC
                LIMIT 100
            ");
            $ventas = [];
            while($row = $result->fetch_assoc()) {
                $ventas[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $ventas]);
        }
        break;
        
    /* ============================================================
    MARCADOR: [CONVERSION-VENTA]
    Ubicación: api/ventas.php - POST - Venta con conversión
    Buscar: "CONVERSION-VENTA"
    ============================================================ */
    case 'POST':
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if(empty($data['detalles']) || count($data['detalles']) === 0) {
            echo json_encode(['success' => false, 'message' => 'Agregue al menos un producto']);
            exit;
        }
        
        $db->begin_transaction();
        
        try {
            $total = 0;
            $ganancia_total = 0;
            
            foreach($data['detalles'] as $detalle) {
                $total += $detalle['subtotal'];
                $ganancia_total += ($detalle['precio_unitario'] - $detalle['costo_unitario']) * $detalle['cantidad'];
            }
            
            // Insertar venta
            $fecha = !empty($data['fecha']) ? $data['fecha'] : date('Y-m-d H:i:s');
            $cliente_id = !empty($data['cliente_id']) ? intval($data['cliente_id']) : null;
            $estado = isset($data['estado']) ? $data['estado'] : 'pagada';
            $observaciones = isset($data['observaciones']) ? trim($data['observaciones']) : '';
            
            $stmt = $db->prepare("
                INSERT INTO ventas (cliente_id, fecha, total, ganancia, estado, observaciones, usuario_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "isddsis",
                $cliente_id,
                $fecha,
                $total,
                $ganancia_total,
                $estado,
                $observaciones,
                $_SESSION['user_id']
            );
            
            if(!$stmt->execute()) {
                throw new Exception("Error al guardar venta: " . $stmt->error);
            }
            
            $venta_id = $db->insert_id;
            
            foreach($data['detalles'] as $detalle) {
                /* ============================================================
                MARCADOR: [CONVERSION-VENTA-LOOP]
                Ubicación: api/ventas.php - POST - Bucle de conversión
                Buscar: "CONVERSION-VENTA-LOOP"
                ============================================================ */
                // Obtener datos del producto
                $stmt = $db->prepare("
                    SELECT id, nombre, unidad_base, contenido_unidad, stock 
                    FROM productos 
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $detalle['producto_id']);
                $stmt->execute();
                $producto = $stmt->get_result()->fetch_assoc();
                
                if(!$producto) {
                    throw new Exception("Producto no encontrado");
                }
                
                $unidad_base = $producto['unidad_base'] ?? 'g';
                $contenido_unidad = $producto['contenido_unidad'] ?? 1;
                $unidad_venta = $detalle['unidad'] ?? $unidad_base;
                $cantidad_vendida = $detalle['cantidad'];
                
                // Convertir a unidad base
                if($unidad_venta != $unidad_base) {
                    $stmt_factor = $db->prepare("
                        SELECT factor FROM unidades_conversion 
                        WHERE unidad_origen = ? AND unidad_destino = ?
                    ");
                    $stmt_factor->bind_param("ss", $unidad_venta, $unidad_base);
                    $stmt_factor->execute();
                    $result_factor = $stmt_factor->get_result();
                    
                    if($row = $result_factor->fetch_assoc()) {
                        $factor = $row['factor'];
                        $cantidad_a_descontar = $cantidad_vendida * $factor;
                    } else {
                        $cantidad_a_descontar = $cantidad_vendida * $contenido_unidad;
                    }
                } else {
                    $cantidad_a_descontar = $cantidad_vendida;
                }
                
                // Verificar stock
                if($producto['stock'] < $cantidad_a_descontar) {
                    throw new Exception(
                        "Stock insuficiente de {$producto['nombre']}. " .
                        "Disponible: {$producto['stock']} {$unidad_base}, " .
                        "Necesario: $cantidad_a_descontar {$unidad_base}"
                    );
                }
                
                // Descontar stock
                $db->query("
                    UPDATE productos 
                    SET stock = stock - $cantidad_a_descontar 
                    WHERE id = {$detalle['producto_id']}
                ");
                
                // Insertar detalle
                $subtotal = $detalle['cantidad'] * $detalle['precio_unitario'];
                $ganancia = ($detalle['precio_unitario'] - $detalle['costo_unitario']) * $detalle['cantidad'];
                $producto_nombre_escaped = $db->escape_string($producto['nombre']);
                
                $sql_detalle = "
                    INSERT INTO venta_detalles (
                        venta_id, producto_id, producto_nombre, cantidad,
                        precio_unitario, subtotal, ganancia, unidad_venta
                    ) VALUES (
                        $venta_id,
                        {$detalle['producto_id']},
                        '$producto_nombre_escaped',
                        $cantidad_vendida,
                        {$detalle['precio_unitario']},
                        $subtotal,
                        $ganancia,
                        '$unidad_venta'
                    )
                ";
                
                $db->query($sql_detalle);
            }
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Venta registrada correctamente',
                'id' => $venta_id,
                'total' => $total,
                'ganancia' => $ganancia_total
            ]);
            
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id']);
        
        $result = $db->query("SELECT estado FROM ventas WHERE id = $id");
        if($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Venta no encontrada']);
            exit;
        }
        
        $venta = $result->fetch_assoc();
        if($venta['estado'] === 'cancelada') {
            echo json_encode(['success' => false, 'message' => 'La venta ya está cancelada']);
            exit;
        }
        
        $db->begin_transaction();
        try {
            // Restaurar stock (incluyendo subproductos)
            $detalles = $db->query("SELECT producto_id, cantidad FROM venta_detalles WHERE venta_id = $id");
            while($detalle = $detalles->fetch_assoc()) {
                $producto_id = $detalle['producto_id'];
                $cantidad = $detalle['cantidad'];
                
                $check = $db->query("SELECT tiene_composicion FROM productos WHERE id = $producto_id");
                $es_compuesto = $check->fetch_assoc()['tiene_composicion'] ?? 0;
                
                if($es_compuesto) {
                    $subproductos = $db->query("
                        SELECT subproducto_id, cantidad 
                        FROM productos_compuestos 
                        WHERE producto_compuesto_id = $producto_id
                    ");
                    while($sub = $subproductos->fetch_assoc()) {
                        $cantidad_sub = $sub['cantidad'] * $cantidad;
                        $stmt = $db->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
                        $stmt->bind_param("di", $cantidad_sub, $sub['subproducto_id']);
                        $stmt->execute();
                    }
                } else {
                    $stmt = $db->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
                    $stmt->bind_param("di", $cantidad, $producto_id);
                    $stmt->execute();
                }
            }
            
            // Cancelar venta
            $stmt = $db->prepare("UPDATE ventas SET estado = 'cancelada' WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Venta cancelada correctamente']);
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => 'Error al cancelar: ' . $e->getMessage()]);
        }
        break;
}
?>