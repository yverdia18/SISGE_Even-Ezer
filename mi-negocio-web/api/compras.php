<?php
// api/compras.php - API DE COMPRAS CON CONVERSIONES DE UNIDADES
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
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($method === 'OPTIONS') {
    exit;
}

switch($method) {
    case 'GET':
        if(isset($_GET['id'])) {
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
                
                $historial = $db->query("
                    SELECT h.*, u.nombre as usuario_nombre
                    FROM compras_historial_ediciones h
                    LEFT JOIN usuarios u ON h.usuario_id = u.id
                    WHERE h.compra_id = $id
                    ORDER BY h.fecha_edicion DESC
                ");
                $compra['historial'] = [];
                while($row = $historial->fetch_assoc()) { 
                    if($row['datos_anteriores']) {
                        $row['datos_anteriores_decoded'] = json_decode($row['datos_anteriores'], true);
                    }
                    if($row['datos_nuevos']) {
                        $row['datos_nuevos_decoded'] = json_decode($row['datos_nuevos'], true);
                    }
                    $compra['historial'][] = $row; 
                }
                
                echo json_encode(['success' => true, 'data' => $compra]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Compra no encontrada']);
            }
        } else {
            $result = $db->query("
                SELECT c.*, u.nombre as usuario_nombre
                FROM compras c
                LEFT JOIN usuarios u ON c.usuario_id = u.id
                WHERE (c.eliminado = 0 OR c.eliminado IS NULL)
                ORDER BY c.fecha DESC LIMIT 100
            ");
            $compras = [];
            while($row = $result->fetch_assoc()) { $compras[] = $row; }
            echo json_encode(['success' => true, 'data' => $compras]);
        }
        break;
        
    /* ============================================================
       MARCADOR: [CONVERSION-COMPRA]
       Ubicación: api/compras.php - POST - Conversión de unidades
       ============================================================ */
    case 'POST':
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
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
            $total = 0;
            foreach($data['detalles'] as $detalle) { $total += $detalle['subtotal']; }
            
            $stmt = $db->prepare("SELECT nombre, apellidos FROM clientes WHERE id = ?");
            $stmt->bind_param("i", $data['proveedor_id']);
            $stmt->execute();
            $proveedor = $stmt->get_result()->fetch_assoc();
            $proveedor_nombre = $proveedor['nombre'] . ' ' . ($proveedor['apellidos'] ?? '');
            
            $proveedor_id = intval($data['proveedor_id']);
            $proveedor_nombre_escaped = $db->escape_string($proveedor_nombre);
            $fecha = !empty($data['fecha']) ? $db->escape_string($data['fecha']) : date('Y-m-d H:i:s');
            $fecha_entrega = isset($data['fecha_entrega']) && !empty($data['fecha_entrega']) ? "'" . $db->escape_string($data['fecha_entrega']) . "'" : "NULL";
            $numero_factura = isset($data['numero_factura']) ? "'" . $db->escape_string(trim($data['numero_factura'])) . "'" : "''";
            $tipo_compra = isset($data['tipo_compra']) ? "'" . $db->escape_string($data['tipo_compra']) . "'" : "'minorista'";
            $observaciones = isset($data['observaciones']) ? "'" . $db->escape_string(trim($data['observaciones'])) . "'" : "''";
            $usuario_id = $_SESSION['user_id'];
            
            $sql = "
                INSERT INTO compras (
                    proveedor_id, proveedor_nombre, fecha, fecha_entrega,
                    numero_factura, tipo_compra, total, observaciones, usuario_id,
                    estado
                ) VALUES (
                    $proveedor_id,
                    '$proveedor_nombre_escaped',
                    '$fecha',
                    $fecha_entrega,
                    $numero_factura,
                    $tipo_compra,
                    $total,
                    $observaciones,
                    $usuario_id,
                    'pendiente'
                )
            ";
            
            if(!$db->query($sql)) {
                throw new Exception("Error al guardar compra: " . $db->error);
            }
            
            $compra_id = $db->insert_id;
            
            /* ============================================================
               MARCADOR: [CONVERSION-COMPRA-LOOP]
               Ubicación: api/compras.php - POST - Bucle de conversión
               Buscar: "CONVERSION-COMPRA-LOOP"
               ============================================================ */
            foreach($data['detalles'] as $detalle) {
                // Obtener datos del producto
                $stmt = $db->prepare("
                    SELECT id, nombre, unidad_base, contenido_unidad, unidad_compra 
                    FROM productos 
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $detalle['producto_id']);
                $stmt->execute();
                $producto = $stmt->get_result()->fetch_assoc();
                
                if(!$producto) {
                    throw new Exception("Producto no encontrado: ID {$detalle['producto_id']}");
                }
                
                $producto_nombre = $producto['nombre'];
                $unidad_compra = $detalle['unidad'] ?? $producto['unidad_compra'] ?? 'unidad';
                $unidad_base = $producto['unidad_base'] ?? 'g';
                $contenido_unidad = $producto['contenido_unidad'] ?? 1;
                
                // Convertir cantidad a unidad base
                $cantidad_comprada = $detalle['cantidad'];
                
                if($unidad_compra != $unidad_base) {
                    $stmt_factor = $db->prepare("
                        SELECT factor FROM unidades_conversion 
                        WHERE unidad_origen = ? AND unidad_destino = ?
                    ");
                    $stmt_factor->bind_param("ss", $unidad_compra, $unidad_base);
                    $stmt_factor->execute();
                    $result_factor = $stmt_factor->get_result();
                    
                    if($row = $result_factor->fetch_assoc()) {
                        $factor = $row['factor'];
                        $cantidad_en_base = $cantidad_comprada * $factor;
                    } else {
                        $cantidad_en_base = $cantidad_comprada * $contenido_unidad;
                    }
                } else {
                    $cantidad_en_base = $cantidad_comprada;
                }
                
                // Guardar detalle
                $subtotal = $cantidad_comprada * $detalle['precio_unitario'];
                $producto_nombre_escaped = $db->escape_string($producto_nombre);
                
                $sql_detalle = "
                    INSERT INTO compra_detalles (
                        compra_id, producto_id, producto_nombre, cantidad,
                        precio_unitario, subtotal, unidad_compra, cantidad_en_base
                    ) VALUES (
                        $compra_id,
                        {$detalle['producto_id']},
                        '$producto_nombre_escaped',
                        $cantidad_comprada,
                        {$detalle['precio_unitario']},
                        $subtotal,
                        '$unidad_compra',
                        $cantidad_en_base
                    )
                ";
                
                if(!$db->query($sql_detalle)) {
                    throw new Exception("Error al guardar detalle: " . $db->error);
                }
                
                // Actualizar stock en unidad base
                $db->query("
                    UPDATE productos 
                    SET stock = stock + $cantidad_en_base 
                    WHERE id = {$detalle['producto_id']}
                ");
            }
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Compra guardada correctamente',
                'id' => $compra_id,
                'total' => $total
            ]);
            
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    case 'PUT':
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if(empty($data['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID de compra requerido']);
            exit;
        }
        
        if(empty($data['motivo'])) {
            echo json_encode(['success' => false, 'message' => 'El motivo de la edición es obligatorio']);
            exit;
        }
        
        if(empty($data['detalles']) || count($data['detalles']) === 0) {
            echo json_encode(['success' => false, 'message' => 'Agregue al menos un producto']);
            exit;
        }
        
        $compra_id = intval($data['id']);
        $result = $db->query("SELECT * FROM compras WHERE id = $compra_id AND (eliminado = 0 OR eliminado IS NULL)");
        if($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Compra no encontrada']);
            exit;
        }
        
        $compra_original = $result->fetch_assoc();
        $db->begin_transaction();
        
        try {
            $detalles_antiguos = $db->query("SELECT * FROM compra_detalles WHERE compra_id = $compra_id");
            $detalles_antiguos_data = [];
            while($row = $detalles_antiguos->fetch_assoc()) {
                $detalles_antiguos_data[] = $row;
            }
            
            foreach($detalles_antiguos_data as $detalle) {
                $db->query("UPDATE productos SET stock = stock - {$detalle['cantidad']} WHERE id = {$detalle['producto_id']}");
            }
            
            $stmt = $db->prepare("SELECT nombre, apellidos FROM clientes WHERE id = ?");
            $stmt->bind_param("i", $data['proveedor_id']);
            $stmt->execute();
            $proveedor = $stmt->get_result()->fetch_assoc();
            $proveedor_nombre = $proveedor['nombre'] . ' ' . ($proveedor['apellidos'] ?? '');
            
            $nuevo_total = 0;
            foreach($data['detalles'] as $detalle) {
                $nuevo_total += $detalle['subtotal'];
            }
            
            $proveedor_nombre_escaped = $db->escape_string($proveedor_nombre);
            $tipo_compra = $db->escape_string($data['tipo_compra']);
            $numero_factura = $db->escape_string($data['numero_factura']);
            $fecha = $db->escape_string($data['fecha']);
            $fecha_entrega = isset($data['fecha_entrega']) && !empty($data['fecha_entrega']) ? "'" . $db->escape_string($data['fecha_entrega']) . "'" : "NULL";
            $usuario_id = $_SESSION['user_id'];
            
            $sql = "
                UPDATE compras SET 
                    proveedor_id = {$data['proveedor_id']},
                    proveedor_nombre = '$proveedor_nombre_escaped',
                    tipo_compra = '$tipo_compra',
                    numero_factura = '$numero_factura',
                    fecha = '$fecha',
                    fecha_entrega = $fecha_entrega,
                    total = $nuevo_total,
                    editado_por = $usuario_id,
                    fecha_edicion = NOW()
                WHERE id = $compra_id
            ";
            
            if(!$db->query($sql)) {
                throw new Exception("Error al actualizar compra: " . $db->error);
            }
            
            $db->query("DELETE FROM compra_detalles WHERE compra_id = $compra_id");
            
            foreach($data['detalles'] as $detalle) {
                $stmt = $db->prepare("SELECT nombre FROM productos WHERE id = ?");
                $stmt->bind_param("i", $detalle['producto_id']);
                $stmt->execute();
                $producto = $stmt->get_result()->fetch_assoc();
                $producto_nombre = $producto['nombre'] ?? 'Producto';
                $subtotal = $detalle['cantidad'] * $detalle['precio_unitario'];
                $producto_nombre_escaped = $db->escape_string($producto_nombre);
                
                $sql_detalle = "
                    INSERT INTO compra_detalles (
                        compra_id, producto_id, producto_nombre, cantidad,
                        precio_unitario, subtotal
                    ) VALUES (
                        $compra_id,
                        {$detalle['producto_id']},
                        '$producto_nombre_escaped',
                        {$detalle['cantidad']},
                        {$detalle['precio_unitario']},
                        $subtotal
                    )
                ";
                
                if(!$db->query($sql_detalle)) {
                    throw new Exception("Error al guardar detalle: " . $db->error);
                }
                
                $db->query("UPDATE productos SET stock = stock + {$detalle['cantidad']} WHERE id = {$detalle['producto_id']}");
            }
            
            $datos_anteriores = json_encode([
                'compra' => $compra_original,
                'detalles' => $detalles_antiguos_data
            ]);
            
            $datos_nuevos = json_encode([
                'compra' => [
                    'proveedor_id' => $data['proveedor_id'],
                    'tipo_compra' => $data['tipo_compra'],
                    'numero_factura' => $data['numero_factura'],
                    'fecha' => $data['fecha'],
                    'fecha_entrega' => $data['fecha_entrega'],
                    'total' => $nuevo_total
                ],
                'detalles' => $data['detalles']
            ]);
            
            $stmt = $db->prepare("
                INSERT INTO compras_historial_ediciones (
                    compra_id, usuario_id, datos_anteriores, datos_nuevos, motivo
                ) VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "iisss",
                $compra_id,
                $_SESSION['user_id'],
                $datos_anteriores,
                $datos_nuevos,
                $data['motivo']
            );
            $stmt->execute();
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Compra editada correctamente',
                'total' => $nuevo_total,
                'productos' => count($data['detalles'])
            ]);
            
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id']);
        
        $result = $db->query("SELECT * FROM compras WHERE id = $id AND (eliminado = 0 OR eliminado IS NULL)");
        if($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Compra no encontrada']);
            exit;
        }
        
        $compra = $result->fetch_assoc();
        
        if($compra['estado'] === 'pagada') {
            echo json_encode(['success' => false, 'message' => '⚠️ Esta compra ya está pagada. No se puede eliminar.']);
            exit;
        }
        
        $db->begin_transaction();
        
        try {
            $detalles = $db->query("SELECT * FROM compra_detalles WHERE compra_id = $id");
            $detalles_data = [];
            while($row = $detalles->fetch_assoc()) { $detalles_data[] = $row; }
            
            $pagos = $db->query("SELECT * FROM pagos_compras WHERE compra_id = $id");
            $pagos_data = [];
            while($row = $pagos->fetch_assoc()) { $pagos_data[] = $row; }
            
            $datos_completos = [
                'compra' => $compra,
                'detalles' => $detalles_data,
                'pagos' => $pagos_data,
                'fecha_eliminacion' => date('Y-m-d H:i:s'),
                'eliminado_por_nombre' => $_SESSION['user_name'] ?? 'Administrador'
            ];
            
            $stmt = $db->prepare("INSERT INTO papelera (tabla_origen, registro_id, datos, eliminado_por) VALUES ('compras', ?, ?, ?)");
            $json_datos = json_encode($datos_completos, JSON_UNESCAPED_UNICODE);
            $stmt->bind_param("isi", $id, $json_datos, $_SESSION['user_id']);
            $stmt->execute();
            
            $stmt = $db->prepare("UPDATE compras SET eliminado = 1, fecha_eliminacion = NOW(), eliminado_por = ?, estado_anterior = estado WHERE id = ?");
            $stmt->bind_param("ii", $_SESSION['user_id'], $id);
            $stmt->execute();
            
            $detalles = $db->query("SELECT producto_id, cantidad FROM compra_detalles WHERE compra_id = $id");
            while($detalle = $detalles->fetch_assoc()) {
                $db->query("UPDATE productos SET stock = stock - {$detalle['cantidad']} WHERE id = {$detalle['producto_id']}");
            }
            
            $db->commit();
            
            echo json_encode(['success' => true, 'message' => '✅ Compra movida a la papelera. El stock ha sido ajustado.']);
            
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    case 'PATCH':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if(isset($data['action']) && $data['action'] === 'pagar') {
            $compra_id = intval($data['compra_id']);
            $monto = floatval($data['monto']);
            $metodo_pago = isset($data['metodo_pago']) ? $data['metodo_pago'] : 'efectivo';
            $tarjeta_id = isset($data['tarjeta_id']) && !empty($data['tarjeta_id']) ? intval($data['tarjeta_id']) : null;
            $plataforma = isset($data['plataforma']) ? $data['plataforma'] : null;
            $referencia = isset($data['referencia']) ? $data['referencia'] : '';
            $observaciones = isset($data['observaciones']) ? $data['observaciones'] : '';
            
            if($monto <= 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => '❌ El monto debe ser mayor a 0',
                    'detalle' => 'Ingresa un monto válido para realizar el pago.',
                    'codigo' => 'MONTO_INVALIDO'
                ]);
                exit;
            }
            
            $result = $db->query("SELECT total, estado FROM compras WHERE id = $compra_id");
            if($result->num_rows === 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => '❌ Compra no encontrada',
                    'detalle' => 'La compra que intentas pagar no existe.',
                    'codigo' => 'COMPRA_NO_ENCONTRADA'
                ]);
                exit;
            }
            $compra = $result->fetch_assoc();
            
            if($compra['estado'] === 'pagada') {
                echo json_encode([
                    'success' => false, 
                    'message' => '❌ La compra ya está completamente pagada',
                    'detalle' => 'Esta compra ya ha sido pagada en su totalidad.',
                    'codigo' => 'COMPRA_YA_PAGADA'
                ]);
                exit;
            }
            
            if($compra['estado'] === 'cancelada') {
                echo json_encode([
                    'success' => false, 
                    'message' => '❌ La compra está cancelada',
                    'detalle' => 'No se pueden registrar pagos para una compra cancelada.',
                    'codigo' => 'COMPRA_CANCELADA'
                ]);
                exit;
            }
            
            // =============================================
            // CORREGIDO: monto_pagado en lugar de monto
            // =============================================
            $total_pagado_result = $db->query("SELECT COALESCE(SUM(monto_pagado), 0) as total FROM pagos_compras WHERE compra_id = $compra_id");
            $total_pagado = $total_pagado_result->fetch_assoc()['total'];
            $saldo_pendiente = $compra['total'] - $total_pagado;
            
            if($monto > $saldo_pendiente) {
                echo json_encode([
                    'success' => false, 
                    'message' => '❌ El monto excede el saldo pendiente',
                    'detalle' => "Saldo pendiente: $" . number_format($saldo_pendiente, 2) . " | Monto ingresado: $" . number_format($monto, 2),
                    'codigo' => 'MONTO_EXCEDE_SALDO',
                    'saldo_pendiente' => $saldo_pendiente
                ]);
                exit;
            }
            
            if($metodo_pago === 'pago_linea' && empty($plataforma)) {
                echo json_encode([
                    'success' => false, 
                    'message' => '❌ Selecciona una plataforma de pago',
                    'detalle' => 'Para pagos en línea, debes seleccionar Transfermóvil o Enzona.',
                    'codigo' => 'PLATAFORMA_REQUERIDA'
                ]);
                exit;
            }
            
            if(($metodo_pago === 'transferencia' || $metodo_pago === 'pago_linea') && empty($tarjeta_id)) {
                echo json_encode([
                    'success' => false, 
                    'message' => '❌ Selecciona una tarjeta de pago',
                    'detalle' => 'Para pagos con transferencia o en línea, debes seleccionar una tarjeta.',
                    'codigo' => 'TARJETA_REQUERIDA'
                ]);
                exit;
            }
            
            if(!empty($tarjeta_id)) {
                $check_tarjeta = $db->query("SELECT id FROM tarjetas_pago WHERE id = $tarjeta_id AND activo = 1");
                if($check_tarjeta->num_rows === 0) {
                    echo json_encode([
                        'success' => false, 
                        'message' => '❌ Tarjeta no válida',
                        'detalle' => 'La tarjeta seleccionada no existe o está inactiva.',
                        'codigo' => 'TARJETA_INVALIDA'
                    ]);
                    exit;
                }
            }
            
            $db->begin_transaction();
            
            try {
                // =============================================
                // CORREGIDO: monto_pagado en lugar de monto
                // =============================================
                $stmt = $db->prepare("
                    INSERT INTO pagos_compras (
                        compra_id, monto_pagado, metodo_pago, referencia, observaciones, usuario_id
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "idsssi",
                    $compra_id,
                    $monto,
                    $metodo_pago,
                    $referencia,
                    $observaciones,
                    $_SESSION['user_id']
                );
                $stmt->execute();
                
                $total_pagado += $monto;
                $saldo_pendiente = $compra['total'] - $total_pagado;
                
                $nuevo_estado = $saldo_pendiente <= 0 ? 'pagada' : 'parcial';
                
                $stmt = $db->prepare("UPDATE compras SET estado = ?, metodo_pago = ? WHERE id = ?");
                $stmt->bind_param("ssi", $nuevo_estado, $metodo_pago, $compra_id);
                $stmt->execute();
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => '✅ Pago registrado correctamente',
                    'estado' => $nuevo_estado,
                    'total_pagado' => $total_pagado,
                    'pendiente' => $saldo_pendiente,
                    'mensaje_pendiente' => $saldo_pendiente > 0 ? "Saldo pendiente: $" . number_format($saldo_pendiente, 2) : "Compra completamente pagada"
                ]);
                
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode([
                    'success' => false, 
                    'message' => '❌ Error al registrar pago',
                    'detalle' => 'Error interno: ' . $e->getMessage(),
                    'codigo' => 'ERROR_INTERNO'
                ]);
            }
        }
        break;
}
?>