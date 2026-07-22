<?php
// dashboard.php - Versión completa con gráficos
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

// Estadísticas básicas (para carga inicial)
$result = $db->query("SELECT COUNT(*) as total FROM productos WHERE eliminado = 0");
$total_productos = $result->fetch_assoc()['total'] ?? 0;

$result = $db->query("SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as monto FROM ventas WHERE DATE(fecha) = CURDATE()");
$ventas_hoy = $result->fetch_assoc();
$total_ventas = $ventas_hoy['total'] ?? 0;
$monto_ventas = $ventas_hoy['monto'] ?? 0;

$result = $db->query("SELECT COUNT(*) as total FROM productos WHERE stock < 10 AND eliminado = 0");
$bajo_stock = $result->fetch_assoc()['total'] ?? 0;

$ganancia = $monto_ventas * 0.4;

ob_start();
?>

<!-- ============================================= -->
<!-- CABECERA -->
<!-- ============================================= -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 fw-bold mb-1">📊 Dashboard</h1>
        <p class="text-secondary">Bienvenido, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?></p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" onclick="refrescarDashboard()">
            <i class="fas fa-sync-alt"></i> Refrescar
        </button>
        <span class="text-muted small align-self-center" id="ultima_actualizacion">Actualizado: ahora</span>
    </div>
</div>

<!-- ============================================= -->
<!-- TARJETAS DE ESTADÍSTICAS -->
<!-- ============================================= -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-secondary mb-1">Productos</h6>
                        <h2 class="fw-bold mb-0" id="statProductos"><?php echo $total_productos; ?></h2>
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
                        <h2 class="fw-bold mb-0" id="statVentasHoy"><?php echo $total_ventas; ?></h2>
                        <small class="text-success" id="statMontoVentas">$<?php echo number_format($monto_ventas, 2); ?></small>
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
                        <h2 class="fw-bold mb-0 <?php echo $bajo_stock > 0 ? 'text-warning' : 'text-success'; ?>" id="statStockBajo">
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
                        <h2 class="fw-bold mb-0 text-success" id="statGanancia">
                            $<?php echo number_format($ganancia, 2); ?>
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

<!-- ============================================= -->
<!-- GRÁFICOS -->
<!-- ============================================= -->
<div class="row g-4">
    <!-- Gráfico de ventas semanales -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">
                    <i class="fas fa-chart-bar me-2 text-primary"></i> Ventas (Últimos 7 días)
                </h5>
            </div>
            <div class="card-body">
                <canvas id="chartVentas" height="220"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Top productos -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 pt-3">
                <h5 class="fw-bold mb-0">
                    <i class="fas fa-trophy me-2 text-warning"></i> Top Productos
                </h5>
            </div>
            <div class="card-body" id="topProductosContainer">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- ÚLTIMAS VENTAS Y ALERTAS -->
<!-- ============================================= -->
<div class="row g-4 mt-2">
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
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cliente</th>
                                <th>Total</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody id="tablaUltimasVentas">
                            <tr>
                                <td colspan="4" class="text-center text-secondary py-3">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Alertas de stock bajo -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-3">
                <h5 class="fw-bold mb-0">
                    <i class="fas fa-exclamation-triangle me-2 text-warning"></i> Alertas de Stock Bajo
                </h5>
            </div>
            <div class="card-body" id="alertasStockContainer">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- ACCIONES RÁPIDAS -->
<!-- ============================================= -->
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
                    <a href="pages/recetas.php" class="btn btn-info">
                        <i class="fas fa-utensils me-1"></i> Nueva Receta
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

$page_scripts = '
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="assets/js/dashboard.js"></script>
';

include 'layout.php';
?>