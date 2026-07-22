<?php
// api/dashboard.php - API PARA DATOS DEL DASHBOARD
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/db.php';

// Verificar autenticación
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? 'stats';

switch($action) {
    case 'stats':
        // Estadísticas generales
        $result = $db->query("SELECT COUNT(*) as total FROM productos WHERE eliminado = 0");
        $total_productos = $result->fetch_assoc()['total'] ?? 0;
        
        $result = $db->query("SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as monto FROM ventas WHERE DATE(fecha) = CURDATE()");
        $ventas_hoy = $result->fetch_assoc();
        
        $result = $db->query("SELECT COUNT(*) as total FROM productos WHERE stock < 10 AND eliminado = 0");
        $stock_bajo = $result->fetch_assoc()['total'] ?? 0;
        
        $result = $db->query("SELECT COALESCE(SUM(total), 0) as total FROM ventas WHERE DATE(fecha) = CURDATE()");
        $ventas_total = $result->fetch_assoc()['total'] ?? 0;
        
        $ganancia = $ventas_total * 0.4;
        
        $result = $db->query("SELECT total FROM ventas ORDER BY fecha DESC LIMIT 1");
        $ultima_venta = $result->fetch_assoc()['total'] ?? 0;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'productos' => (int)$total_productos,
                'ventas_hoy' => (int)($ventas_hoy['total'] ?? 0),
                'monto_ventas' => (float)($ventas_hoy['monto'] ?? 0),
                'stock_bajo' => (int)$stock_bajo,
                'ganancia' => (float)$ganancia,
                'ultima_venta' => (float)$ultima_venta,
                'tendencia' => $ultima_venta > ($ventas_hoy['monto'] ?? 0) ? 'up' : 'down'
            ]
        ]);
        break;
        
    case 'ventas_semana':
        $result = $db->query("
            SELECT 
                DATE(fecha) as fecha,
                COUNT(*) as total_ventas,
                COALESCE(SUM(total), 0) as total_monto
            FROM ventas 
            WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(fecha)
            ORDER BY fecha ASC
        ");
        
        $fechas = [];
        $montos = [];
        
        while($row = $result->fetch_assoc()) {
            $fechas[] = date('d/m', strtotime($row['fecha']));
            $montos[] = (float)$row['total_monto'];
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'labels' => $fechas,
                'montos' => $montos
            ]
        ]);
        break;
        
    case 'top_productos':
        $result = $db->query("
            SELECT 
                p.nombre,
                SUM(vd.cantidad) as total_vendido,
                SUM(vd.subtotal) as total_ingresos
            FROM venta_detalles vd
            JOIN productos p ON vd.producto_id = p.id
            JOIN ventas v ON vd.venta_id = v.id
            WHERE v.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY vd.producto_id
            ORDER BY total_vendido DESC
            LIMIT 5
        ");
        
        $productos = [];
        while($row = $result->fetch_assoc()) {
            $productos[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $productos
        ]);
        break;
        
    case 'stock_bajo':
        $result = $db->query("
            SELECT 
                id,
                nombre,
                stock,
                stock_minimo,
                (stock_minimo - stock) as faltante,
                unidad_base
            FROM productos 
            WHERE stock < stock_minimo 
            AND eliminado = 0
            ORDER BY stock ASC
            LIMIT 10
        ");
        
        $productos = [];
        while($row = $result->fetch_assoc()) {
            $productos[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $productos
        ]);
        break;
        
    case 'ultimas_ventas':
        $result = $db->query("
            SELECT 
                v.id,
                v.total,
                v.fecha,
                c.nombre as cliente
            FROM ventas v
            LEFT JOIN clientes c ON v.cliente_id = c.id
            ORDER BY v.fecha DESC
            LIMIT 5
        ");
        
        $ventas = [];
        while($row = $result->fetch_assoc()) {
            $ventas[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $ventas
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
?>