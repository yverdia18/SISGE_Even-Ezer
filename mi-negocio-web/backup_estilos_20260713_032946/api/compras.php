<?php
// api/compras.php - VERSIÓN COMPLETA CON PAGOS MÚLTIPLES Y DESCUENTOS
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
                // Obtener compra específica (incluyendo eliminadas si se pide)
                $id = intval($_GET['id']);
                $result = $db->query("
                    SELECT c.*, u.nombre as usuario_nombre
                    FROM compras c
                    LEFT JOIN usuarios u ON c.usuario_id = u.id
                    WHERE c.id = $id
                ");
            $compra = $result->fetch_assoc();
            
            if($compra) {
                $detalles = $db->query("
                    SELECT cd.*, p.nombre as producto_nombre
                    FROM compra_detalles cd
                    LEFT JOIN productos p ON cd.producto_id = p.id
                    WHERE cd.compra_id = $id
                ");
                $compra['detalles'] = [];
                while($row = $detalles->fetch_assoc()) {
                    $compra['detalles'][] = $row;
                }
                
                $pagos = $db->query("
                    SELECT * FROM pagos_compras
                    WHERE compra_id = $id
                    ORDER BY fecha_pago DESC
                ");
                $compra['pagos'] = [];
                while($row = $pagos->fetch_assoc()) {
                    $compra['pagos'][] = $row;
                }
                
                echo json_encode(['success' => true, 'data' => $compra]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Compra no encontrada']);
            }
        } else {
                // Listar compras (solo no eliminadas)
                $result = $db->query("
                    SELECT c.*, u.nombre as usuario_nombre
                    FROM compras c
                    LEFT JOIN usuarios u ON c.usuario_id = u.id
                    WHERE c.eliminado = 0 OR c.eliminado IS NULL
                    ORDER BY c.fecha DESC
                    LIMIT 100
                ");
            $compras = [];
            while($row = $result->fetch_assoc()) {
                $compras[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $compras]);
        }
        break;
        
    case 'POST':
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // Validaciones
        if(empty($data['proveedor_id'])) {
            echo json_encode(['success' => false, 'message' => 'El proveedor es obligatorio']);
            exit;
        }
        
        if(empty($data['detalles']) || count($data['detalles']) === 0) {
            echo json_encode(['success' => false, 'message' => 'Agregue al menos un producto']);
            exit;
        }
        
        $db->begin_transaction();
        
        try {
            // Calcular totales
            $subtotal = 0;
            $descuento_total = 0;
            
            foreach($data['detalles'] as $detalle) {
                $subtotal += $detalle['subtotal'];
                $descuento_total += isset($detalle['descuento']) ? floatval($detalle['descuento']) : 0;
            }
            
            $total = $subtotal - $descuento_total;
            
            // Obtener nombre del proveedor
            $stmt = $db->prepare("SELECT nombre, apellidos FROM clientes WHERE id = ?");
            $stmt->bind_param("i", $data['proveedor_id']);
            $stmt->execute();
            $proveedor = $stmt->get_result()->fetch_assoc();
            $proveedor_nombre = $proveedor['nombre'] . ' ' . ($proveedor['apellidos'] ?? '');
            
            // Preparar variables
            $proveedor_id = intval($data['proveedor_id']);
            $fecha = !empty($data['fecha']) ? $data['fecha'] : date('Y-m-d H:i:s');
            $fecha_entrega = isset($data['fecha_entrega']) && !empty($data['fecha_entrega']) ? $data['fecha_entrega'] : null;
            $numero_factura = isset($data['numero_factura']) ? trim($data['numero_factura']) : '';
            $tipo_compra = isset($data['tipo_compra']) ? $data['tipo_compra'] : 'minorista';
            $observaciones = isset($data['observaciones']) ? trim($data['observaciones']) : '';
            $usuario_id = $_SESSION['user_id'];
            
            // Insertar compra
            $sql = "
                INSERT INTO compras (
                    proveedor_id, proveedor_nombre, fecha, fecha_entrega,
                    numero_factura, tipo_compra, subtotal, descuento, total,
                    observaciones, usuario_id, estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->bind_param(
                "isssssdddsi",
                $proveedor_id,
                $proveedor_nombre,
                $fecha,
                $fecha_entrega,
                $numero_factura,
                $tipo_compra,
                $subtotal,
                $descuento_total,
                $total,
                $observaciones,
                $usuario_id
            );
            
            if(!$stmt->execute()) {
                throw new Exception("Error al guardar compra: " . $stmt->error);
            }
            
            $compra_id = $db->insert_id;
            
            // Insertar detalles
            foreach($data['detalles'] as $detalle) {
                $stmt = $db->prepare("SELECT nombre FROM productos WHERE id = ?");
                $stmt->bind_param("i", $detalle['producto_id']);
                $stmt->execute();
                $producto = $stmt->get_result()->fetch_assoc();
                $producto_nombre = $producto['nombre'] ?? 'Producto';
                
                $cantidad = intval($detalle['cantidad']);
                $precio_unitario = floatval($detalle['precio_unitario']);
                $subtotal_detalle = $cantidad * $precio_unitario;
                $descuento_detalle = isset($detalle['descuento']) ? floatval($detalle['descuento']) : 0;
                $total_detalle = $subtotal_detalle - $descuento_detalle;
                
                $stmt = $db->prepare("
                    INSERT INTO compra_detalles (
                        compra_id, producto_id, producto_nombre, cantidad,
                        precio_unitario, subtotal, descuento, total
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "iisidddd",
                    $compra_id,
                    $detalle['producto_id'],
                    $producto_nombre,
                    $cantidad,
                    $precio_unitario,
                    $subtotal_detalle,
                    $descuento_detalle,
                    $total_detalle
                );
                
                if(!$stmt->execute()) {
                    throw new Exception("Error al guardar detalle: " . $stmt->error);
                }
                
                // Actualizar stock
                $stmt = $db->prepare("
                    UPDATE productos SET 
                        stock = stock + ?,
                        ultimo_precio_compra = ?,
                        ultima_compra = CURDATE()
                    WHERE id = ?
                ");
                $stmt->bind_param("idi", $cantidad, $precio_unitario, $detalle['producto_id']);
                $stmt->execute();
            }
            
            // Registrar en historial
            $stmt = $db->prepare("
                INSERT INTO historial_compras (compra_id, usuario_id, accion, descripcion)
                VALUES (?, ?, 'creada', ?)
            ");
            $descripcion = "Compra creada con " . count($data['detalles']) . " productos. Total: $" . number_format($total, 2);
            $stmt->bind_param("iis", $compra_id, $_SESSION['user_id'], $descripcion);
            $stmt->execute();
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Compra guardada correctamente',
                'id' => $compra_id,
                'total' => $total,
                'subtotal' => $subtotal,
                'descuento' => $descuento_total
            ]);
            
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id']);
        
        // Verificar que la compra existe
        $result = $db->query("SELECT * FROM compras WHERE id = $id AND (eliminado = 0 OR eliminado IS NULL)");
        if($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Compra no encontrada']);
            exit;
        }
        
        $compra = $result->fetch_assoc();
        
        // No permitir eliminar compras ya pagadas (solo mover a papelera)
        // Si está pagada, se puede eliminar pero con advertencia (afecta ganancias)
        if($compra['estado'] === 'pagada') {
            echo json_encode([
                'success' => false, 
                'message' => '⚠️ Esta compra ya está pagada. Si la eliminas, afectará las ganancias y el stock.'
            ]);
            exit;
        }
        
        $db->begin_transaction();
        
        try {
            // 1. Guardar en papelera (con todos los datos)
            $datos_json = json_encode($compra);
            
            // Guardar también los detalles
            $detalles = $db->query("SELECT * FROM compra_detalles WHERE compra_id = $id");
            $detalles_data = [];
            while($row = $detalles->fetch_assoc()) {
                $detalles_data[] = $row;
            }
            
            $datos_completos = [
                'compra' => $compra,
                'detalles' => $detalles_data
            ];
            
            $stmt = $db->prepare("
                INSERT INTO papelera (tabla_origen, registro_id, datos, eliminado_por)
                VALUES ('compras', ?, ?, ?)
            ");
            $json_datos = json_encode($datos_completos);
            $stmt->bind_param("isi", $id, $json_datos, $_SESSION['user_id']);
            $stmt->execute();
            
            // 2. Marcar compra como eliminada (soft delete)
            $stmt = $db->prepare("
                UPDATE compras SET 
                    eliminado = 1, 
                    fecha_eliminacion = NOW(), 
                    eliminado_por = ?,
                    estado_anterior = estado
                WHERE id = ?
            ");
            $stmt->bind_param("ii", $_SESSION['user_id'], $id);
            $stmt->execute();
            
            // 3. Restaurar stock de los productos (revertir la compra)
            $detalles = $db->query("SELECT producto_id, cantidad FROM compra_detalles WHERE compra_id = $id");
            while($detalle = $detalles->fetch_assoc()) {
                $stmt = $db->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
                $stmt->bind_param("ii", $detalle['cantidad'], $detalle['producto_id']);
                $stmt->execute();
            }
            
            $db->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => '✅ Compra movida a la papelera. El stock ha sido ajustado.'
            ]);
            
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    case 'PATCH':
        $data = json_decode(file_get_contents('php://input'), true);
    
        if(isset($data['action']) && $data['action'] === 'pagar') {
            $compra_id = intval($data['compra_id']);
            $monto_pagado = floatval($data['monto']);
            $metodo_pago = isset($data['metodo_pago']) ? $data['metodo_pago'] : 'efectivo';
            $tarjeta_id = isset($data['tarjeta_id']) && !empty($data['tarjeta_id']) ? intval($data['tarjeta_id']) : null;
            $plataforma = isset($data['plataforma']) ? $data['plataforma'] : null;
            $referencia = isset($data['referencia']) ? $data['referencia'] : '';
            $observaciones = isset($data['observaciones']) ? $data['observaciones'] : '';
            
            // Descuento SOLO para Pago en Línea (Transferencia NO tiene descuento)
            $descuento_porcentaje = 0;
            $descuento_monto = 0;
            
            if($metodo_pago === 'pago_linea' && !empty($plataforma)) {
                if($plataforma === 'transfermovil') {
                    $descuento_porcentaje = 3;
                    $descuento_monto = ($monto_pagado * 3) / 100;
                } elseif($plataforma === 'enzona') {
                    $descuento_porcentaje = 6;
                    $descuento_monto = ($monto_pagado * 6) / 100;
                }
            }
            
            if($monto_pagado <= 0) {
                echo json_encode(['success' => false, 'message' => 'El monto debe ser mayor a 0']);
                exit;
            }
            
            $result = $db->query("SELECT total, estado FROM compras WHERE id = $compra_id");
            if($result->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Compra no encontrada']);
                exit;
            }
            $compra = $result->fetch_assoc();
            
            if($compra['estado'] === 'pagada') {
                echo json_encode(['success' => false, 'message' => 'La compra ya está completamente pagada']);
                exit;
            }
            
            $total_pagado_result = $db->query("SELECT COALESCE(SUM(monto_pagado), 0) as total FROM pagos_compras WHERE compra_id = $compra_id");
            $total_pagado = $total_pagado_result->fetch_assoc()['total'];
            $restante = $compra['total'] - $total_pagado;
            
            if($monto_pagado > $restante) {
                echo json_encode([
                    'success' => false, 
                    'message' => "El monto excede el saldo restante. Saldo pendiente: $" . number_format($restante, 2)
                ]);
                exit;
            }
            
            $db->begin_transaction();
            
            try {
                $stmt = $db->prepare("
                    INSERT INTO pagos_compras (compra_id, monto_pagado, descuento_monto, descuento_porcentaje, metodo_pago, tarjeta_id, plataforma, referencia, observaciones, usuario_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "idddsssssi",
                    $compra_id,
                    $monto_pagado,
                    $descuento_monto,
                    $descuento_porcentaje,
                    $metodo_pago,
                    $tarjeta_id,
                    $plataforma,
                    $referencia,
                    $observaciones,
                    $_SESSION['user_id']
                );
                
                if(!$stmt->execute()) {
                    throw new Exception("Error al registrar pago: " . $stmt->error);
                }
                
                $total_pagado += $monto_pagado;
                $nuevo_estado = $total_pagado >= $compra['total'] ? 'pagada' : 'parcial';
                
                $stmt = $db->prepare("UPDATE compras SET estado = ?, metodo_pago = ? WHERE id = ?");
                $stmt->bind_param("ssi", $nuevo_estado, $metodo_pago, $compra_id);
                $stmt->execute();
                
                $stmt = $db->prepare("
                    INSERT INTO historial_compras (compra_id, usuario_id, accion, descripcion)
                    VALUES (?, ?, 'pago', ?)
                ");
                
                $descripcion = "Pago registrado de $" . number_format($monto_pagado, 2) . 
                            " por " . $metodo_pago;
                
                if($descuento_monto > 0) {
                    $descripcion .= " (Bonificación bancaria: $" . number_format($descuento_monto, 2) . " - " . $descuento_porcentaje . "%)";
                }
                
                $descripcion .= ". Total pagado: $" . number_format($total_pagado, 2) . 
                            " de $" . number_format($compra['total'], 2);
                
                $stmt->bind_param("iis", $compra_id, $_SESSION['user_id'], $descripcion);
                $stmt->execute();
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => '✅ Pago registrado correctamente',
                    'estado' => $nuevo_estado,
                    'total_pagado' => $total_pagado,
                    'pendiente' => $compra['total'] - $total_pagado,
                    'descuento_aplicado' => $descuento_monto
                ]);
                
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode([
                    'success' => false, 
                    'message' => 'Error al registrar pago: ' . $e->getMessage()
                ]);
            }
        }
        break;
}
?>