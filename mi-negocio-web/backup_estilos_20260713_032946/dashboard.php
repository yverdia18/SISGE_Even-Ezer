<?php
// dashboard.php - Versión corregida
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Verificar autenticación
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page = 'dashboard';
$page_title = 'Dashboard';

$db = Database::getInstance()->getConnection();

// Estadísticas
$result = $db->query("SELECT COUNT(*) as total FROM productos");
$total_productos = $result->fetch_assoc()['total'] ?? 0;

$result = $db->query("SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as monto FROM ventas WHERE DATE(fecha) = CURDATE()");
$ventas_hoy = $result->fetch_assoc();
$total_ventas = $ventas_hoy['total'] ?? 0;
$monto_ventas = $ventas_hoy['monto'] ?? 0;

$result = $db->query("SELECT COUNT(*) as total FROM productos WHERE stock < 10");
$bajo_stock = $result->fetch_assoc()['total'] ?? 0;

$ultimas_ventas = $db->query("
    SELECT v.*, c.nombre as cliente 
    FROM ventas v 
    LEFT JOIN clientes c ON v.cliente_id = c.id 
    ORDER BY v.fecha DESC 
    LIMIT 5
");

// Productos más vendidos
$top_productos = $db->query("
    SELECT p.nombre, SUM(vd.cantidad) as total_vendido
    FROM venta_detalles vd
    JOIN productos p ON vd.producto_id = p.id
    GROUP BY vd.producto_id
    ORDER BY total_vendido DESC
    LIMIT 5
");

// Iniciar el buffer de salida
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 fw-bold mb-1">Dashboard</h1>
        <p class="text-secondary">Bienvenido, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?></p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" onclick="window.location.reload()">
            <i class="fas fa-sync-alt"></i>
        </button>
        <a href="logout.php" class="btn btn-danger">
            <i class="fas fa-sign-out-alt me-1"></i> Salir
        </a>
    </div>
</div>

<!-- Tarjetas de estadísticas -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-secondary mb-1">Productos</h6>
                        <h2 class="fw-bold mb-0"><?php echo $total_productos; ?></h2>
                    </div>
                    <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                        <i class="fas fa-box text-primary fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-secondary mb-1">Ventas Hoy</h6>
                        <h2 class="fw-bold mb-0"><?php echo $total_ventas; ?></h2>
                        <small class="text-success">$<?php echo number_format($monto_ventas, 2); ?></small>
                    </div>
                    <div class="bg-success bg-opacity-10 rounded-3 p-3">
                        <i class="fas fa-shopping-cart text-success fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-secondary mb-1">Stock Bajo</h6>
                        <h2 class="fw-bold mb-0 <?php echo $bajo_stock > 0 ? 'text-warning' : ''; ?>">
                            <?php echo $bajo_stock; ?>
                        </h2>
                    </div>
                    <div class="bg-warning bg-opacity-10 rounded-3 p-3">
                        <i class="fas fa-exclamation-triangle text-warning fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-secondary mb-1">Ganancia Estimada</h6>
                        <h2 class="fw-bold mb-0 text-success">
                            $<?php echo number_format($monto_ventas * 0.4, 2); ?>
                        </h2>
                    </div>
                    <div class="bg-info bg-opacity-10 rounded-3 p-3">
                        <i class="fas fa-dollar-sign text-info fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Gráficos y tablas -->
<div class="row g-4">
    <!-- Últimas ventas -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-3">
                <h5 class="fw-bold mb-0">
                    <i class="fas fa-clock me-2 text-primary"></i> Últimas Ventas
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cliente</th>
                                <th>Total</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($ultimas_ventas && $ultimas_ventas->num_rows > 0): ?>
                                <?php while($venta = $ultimas_ventas->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $venta['id']; ?></td>
                                    <td><?php echo $venta['cliente'] ?? 'Cliente general'; ?></td>
                                    <td class="fw-bold text-success">
                                        $<?php echo number_format($venta['total'], 2); ?>
                                    </td>
                                    <td><?php echo formatDate($venta['fecha']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-secondary py-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        No hay ventas registradas
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Productos más vendidos -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-3">
                <h5 class="fw-bold mb-0">
                    <i class="fas fa-star me-2 text-warning"></i> Productos Más Vendidos
                </h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php if($top_productos && $top_productos->num_rows > 0): ?>
                        <?php while($producto = $top_productos->fetch_assoc()): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><?php echo htmlspecialchars($producto['nombre']); ?></span>
                            <span class="badge bg-primary rounded-pill">
                                <?php echo $producto['total_vendido']; ?> unidades
                            </span>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-secondary text-center py-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Aún no hay productos vendidos
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Acciones rápidas -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="fw-bold mb-3">
                    <i class="fas fa-bolt me-2 text-warning"></i> Acciones Rápidas
                </h5>
                <div class="d-flex flex-wrap gap-2">
                    <a href="pages/productos.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Nuevo Producto
                    </a>
                    <a href="pages/ventas.php" class="btn btn-success">
                        <i class="fas fa-cart-plus me-1"></i> Nueva Venta
                    </a>
                    <a href="pages/compras.php" class="btn btn-warning">
                        <i class="fas fa-truck me-1"></i> Nueva Compra
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Capturar el contenido del buffer
$content = ob_get_clean();

// Scripts específicos de la página
$page_scripts = '<script src="assets/js/dashboard.js"></script>';

// Incluir el layout
include 'layout.php';
?>