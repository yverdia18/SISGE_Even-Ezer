<?php
// api/productos.php - VERSIÓN CORREGIDA (ERROR DE BIND_PARAM SOLUCIONADO)
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
                SELECT p.*, c.nombre as categoria_nombre
                FROM productos p
                LEFT JOIN categorias c ON p.categoria_id = c.id
                WHERE p.id = $id
            ");
            $producto = $result->fetch_assoc();
            
            if($producto) {
                $composicion = $db->query("
                    SELECT pc.*, p.nombre as subproducto_nombre
                    FROM productos_compuestos pc
                    LEFT JOIN productos p ON pc.subproducto_id = p.id
                    WHERE pc.producto_compuesto_id = $id
                ");
                $producto['composicion'] = [];
                while($row = $composicion->fetch_assoc()) {
                    $producto['composicion'][] = $row;
                }
                echo json_encode(['success' => true, 'data' => $producto]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
            }
        } else {
            if(isset($_GET['ingredientes']) && $_GET['ingredientes'] == 1) {
                $result = $db->query("
                    SELECT id, nombre, precio_compra, stock, unidad_base
                    FROM productos 
                    WHERE es_ingrediente = 1 AND tipo_producto = 'simple' AND (eliminado = 0 OR eliminado IS NULL)
                    ORDER BY nombre ASC
                ");
            } elseif(isset($_GET['venta']) && $_GET['venta'] == 1) {
                $result = $db->query("
                    SELECT 
                        id, 
                        nombre, 
                        precio_venta, 
                        stock, 
                        precio_compra as costo_promedio,
                        es_producto_final
                    FROM productos 
                    WHERE es_producto_final = 1 
                    AND stock > 0
                    AND tipo_producto != 'compuesto'
                    AND (eliminado = 0 OR eliminado IS NULL)
                    ORDER BY nombre ASC
                ");
            } else {
                $result = $db->query("
                    SELECT p.*, c.nombre as categoria_nombre,
                           (SELECT COUNT(*) FROM productos_compuestos pc WHERE pc.producto_compuesto_id = p.id) as tiene_composicion
                    FROM productos p
                    LEFT JOIN categorias c ON p.categoria_id = c.id
                    WHERE p.eliminado = 0 OR p.eliminado IS NULL
                    ORDER BY p.nombre ASC
                ");
            }
            
            $productos = [];
            while($row = $result->fetch_assoc()) {
                $productos[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $productos]);
        }
        break;
        
    case 'POST':
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if(empty($data['nombre'])) {
            echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']);
            exit;
        }
        
        // =============================================
        // PREPARAR VARIABLES
        // =============================================
        $nombre = trim($data['nombre']);
        $descripcion = isset($data['descripcion']) ? trim($data['descripcion']) : '';
        $categoria_id = !empty($data['categoria_id']) ? intval($data['categoria_id']) : null;
        $precio_compra = isset($data['tipo_producto']) && $data['tipo_producto'] == 'compuesto' ? 0 : floatval($data['precio_compra'] ?? 0);
        $precio_venta = floatval($data['precio_venta'] ?? 0);
        $stock = isset($data['tipo_producto']) && $data['tipo_producto'] == 'compuesto' ? 0 : intval($data['stock'] ?? 0);
        $stock_minimo = intval($data['stock_minimo'] ?? 5);
        $tipo_producto = $data['tipo_producto'] ?? 'simple';
        $es_ingrediente = intval($data['es_ingrediente'] ?? 1);
        $es_producto_final = intval($data['es_producto_final'] ?? 1);
        $tiene_composicion = isset($data['tiene_composicion']) ? intval($data['tiene_composicion']) : 0;
        $costo_produccion = isset($data['costo_produccion']) ? floatval($data['costo_produccion']) : 0;
        
        // =============================================
        // UNIDADES
        // =============================================
        $unidad_base = isset($data['unidad_base']) ? trim($data['unidad_base']) : 'g';
        $unidad_compra = isset($data['unidad_compra']) ? trim($data['unidad_compra']) : 'unidad';
        $contenido_unidad = isset($data['contenido_unidad']) ? floatval($data['contenido_unidad']) : 1.00;
        
        $categoria_id_bind = $categoria_id ?? 0;
        
        if(isset($data['id']) && $data['id'] > 0) {
            // =============================================
            // ACTUALIZAR PRODUCTO
            // =============================================
            $id = intval($data['id']);
            
            $sql = "
                UPDATE productos SET 
                    nombre = ?,
                    descripcion = ?,
                    categoria_id = ?,
                    precio_compra = ?,
                    precio_venta = ?,
                    stock = ?,
                    stock_minimo = ?,
                    tipo_producto = ?,
                    es_ingrediente = ?,
                    es_producto_final = ?,
                    tiene_composicion = ?,
                    costo_produccion = ?,
                    unidad_base = ?,
                    unidad_compra = ?,
                    contenido_unidad = ?
                WHERE id = ?
            ";
            
            $stmt = $db->prepare($sql);
            if(!$stmt) {
                echo json_encode(['success' => false, 'message' => 'Error en prepare: ' . $db->error]);
                exit;
            }
            
            // =============================================
            // 16 VARIABLES - 16 CARACTERES EN LA CADENA
            // =============================================
            $stmt->bind_param(
                "ssiddiisiiidssdi",  // 16 caracteres
                $nombre,
                $descripcion,
                $categoria_id_bind,
                $precio_compra,
                $precio_venta,
                $stock,
                $stock_minimo,
                $tipo_producto,
                $es_ingrediente,
                $es_producto_final,
                $tiene_composicion,
                $costo_produccion,
                $unidad_base,
                $unidad_compra,
                $contenido_unidad,
                $id
            );
            
        } else {
            // =============================================
            // CREAR PRODUCTO
            // =============================================
            $sql = "
                INSERT INTO productos (
                    nombre, descripcion, categoria_id, 
                    precio_compra, precio_venta, stock, stock_minimo,
                    tipo_producto, es_ingrediente, es_producto_final,
                    tiene_composicion, costo_produccion,
                    unidad_base, unidad_compra, contenido_unidad
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $db->prepare($sql);
            if(!$stmt) {
                echo json_encode(['success' => false, 'message' => 'Error en prepare: ' . $db->error]);
                exit;
            }
            
            // =============================================
            // 15 VARIABLES - 15 CARACTERES EN LA CADENA
            // =============================================
            $stmt->bind_param(
                "ssiddiisiiidssd",  // 15 caracteres
                $nombre,
                $descripcion,
                $categoria_id_bind,
                $precio_compra,
                $precio_venta,
                $stock,
                $stock_minimo,
                $tipo_producto,
                $es_ingrediente,
                $es_producto_final,
                $tiene_composicion,
                $costo_produccion,
                $unidad_base,
                $unidad_compra,
                $contenido_unidad
            );
        }
        
        if(!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $stmt->error]);
            exit;
        }
        
        $id = isset($data['id']) && $data['id'] > 0 ? $data['id'] : $db->insert_id;
        
        // =============================================
        // GUARDAR COMPOSICIÓN (si es compuesto)
        // =============================================
        if($tipo_producto === 'compuesto' && isset($data['subproductos']) && count($data['subproductos']) > 0) {
            $db->query("DELETE FROM productos_compuestos WHERE producto_compuesto_id = $id");
            
            foreach($data['subproductos'] as $sub) {
                $stmt2 = $db->prepare("
                    INSERT INTO productos_compuestos (producto_compuesto_id, subproducto_id, cantidad, unidad)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt2->bind_param("iids", $id, $sub['subproducto_id'], $sub['cantidad'], $sub['unidad'] ?? 'unidad');
                $stmt2->execute();
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Producto guardado correctamente',
            'id' => $id
        ]);
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id']);
        
        // Verificar dependencias
        $check = $db->query("
            SELECT COUNT(*) as total FROM compra_detalles WHERE producto_id = $id
            UNION
            SELECT COUNT(*) FROM venta_detalles WHERE producto_id = $id
            UNION
            SELECT COUNT(*) FROM receta_ingredientes WHERE ingrediente_id = $id
            UNION
            SELECT COUNT(*) FROM productos_compuestos WHERE subproducto_id = $id
        ");
        
        $usado = 0;
        while($row = $check->fetch_assoc()) {
            $usado += $row['total'];
        }
        
        if($usado > 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'No se puede eliminar: el producto está siendo usado'
            ]);
            exit;
        }
        
        // Soft delete
        $stmt = $db->prepare("UPDATE productos SET eliminado = 1, fecha_eliminacion = NOW(), eliminado_por = ? WHERE id = ?");
        $stmt->bind_param("ii", $_SESSION['user_id'], $id);
        $stmt->execute();
        
        // Guardar en papelera
        $datos = $db->query("SELECT * FROM productos WHERE id = $id")->fetch_assoc();
        $stmt2 = $db->prepare("
            INSERT INTO papelera (tabla_origen, registro_id, datos, eliminado_por)
            VALUES ('productos', ?, ?, ?)
        ");
        $json_datos = json_encode($datos);
        $stmt2->bind_param("isi", $id, $json_datos, $_SESSION['user_id']);
        $stmt2->execute();
        
        echo json_encode(['success' => true, 'message' => 'Producto movido a la papelera']);
        break;
}
?>