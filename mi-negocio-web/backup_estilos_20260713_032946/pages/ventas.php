<?php
// pages/ventas.php - VERSIÓN CORREGIDA
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar autenticación
if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$page = 'ventas';
$page_title = 'Ventas';

$db = Database::getInstance()->getConnection();

// Obtener clientes
$clientes = $db->query("SELECT id, nombre, apellidos, telefono FROM clientes ORDER BY nombre ASC");

// =============================================
// OBTENER PRODUCTOS QUE SE VENDEN (es_producto_final = 1)
// CORREGIDO: Eliminado 'activo' porque no existe en la tabla
// =============================================
$productos = $db->query("
    SELECT id, nombre, precio_venta_actual as precio_venta, stock, costo_promedio
    FROM productos 
    WHERE stock > 0 
    AND es_producto_final = 1
    ORDER BY nombre ASC
");

// Obtener últimas ventas
$ventas = $db->query("
    SELECT v.*, 
           c.nombre as cliente_nombre, 
           c.apellidos as cliente_apellidos,
           u.nombre as usuario_nombre
    FROM ventas v
    LEFT JOIN clientes c ON v.cliente_id = c.id
    LEFT JOIN usuarios u ON v.usuario_id = u.id
    ORDER BY v.fecha DESC
    LIMIT 50
");

// Estadísticas del día
$stats = $db->query("
    SELECT 
        COUNT(*) as total_ventas,
        SUM(total) as total_ingresos,
        SUM(ganancia) as total_ganancia
    FROM ventas 
    WHERE DATE(fecha) = CURDATE()
")->fetch_assoc();

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 fw-bold mb-1">🛒 Ventas</h1>
        <p class="text-secondary">Registro de ventas a clientes</p>
    </div>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalVenta">
        <i class="fas fa-plus me-2"></i> Nueva Venta
    </button>
</div>

<!-- Tarjetas de estadísticas -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-secondary">Ventas Hoy</h6>
                <h3 class="fw-bold"><?php echo $stats['total_ventas'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-secondary">Ingresos Hoy</h6>
                <h3 class="fw-bold text-success">
                    $<?php echo number_format($stats['total_ingresos'] ?? 0, 2); ?>
                </h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-secondary">Ganancia Hoy</h6>
                <h3 class="fw-bold text-primary">
                    $<?php echo number_format($stats['total_ganancia'] ?? 0, 2); ?>
                </h3>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" id="buscarVenta" class="form-control" placeholder="Buscar...">
        </div>
    </div>
    <div class="col-md-3">
        <select id="filtroEstadoVenta" class="form-select">
            <option value="">Todos los estados</option>
            <option value="pagada">Pagada</option>
            <option value="pendiente">Pendiente</option>
            <option value="cancelada">Cancelada</option>
        </select>
    </div>
</div>

<!-- Tabla de ventas -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="tablaVentas">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cliente</th>
                        <th>Productos</th>
                        <th>Total</th>
                        <th>Ganancia</th>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($venta = $ventas->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $venta['id']; ?></td>
                        <td>
                            <?php echo htmlspecialchars($venta['cliente_nombre'] ?? 'Cliente general'); ?>
                            <?php if(!empty($venta['cliente_apellidos'])): ?>
                                <?php echo htmlspecialchars($venta['cliente_apellidos']); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $detalles = $db->query("SELECT COUNT(*) as total FROM venta_detalles WHERE venta_id = {$venta['id']}");
                            $total_prod = $detalles->fetch_assoc()['total'];
                            echo $total_prod;
                            ?>
                        </td>
                        <td class="fw-bold text-success">
                            $<?php echo number_format($venta['total'], 2); ?>
                        </td>
                        <td class="fw-bold text-primary">
                            $<?php echo number_format($venta['ganancia'] ?? 0, 2); ?>
                        </td>
                        <td><?php echo formatDate($venta['fecha']); ?></td>
                        <td><?php echo htmlspecialchars($venta['usuario_nombre'] ?? 'N/A'); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary btn-ver" data-id="<?php echo $venta['id']; ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if($venta['estado'] != 'cancelada'): ?>
                                <button class="btn btn-outline-danger btn-cancelar" data-id="<?php echo $venta['id']; ?>">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL PARA NUEVA VENTA -->
<!-- ============================================= -->
<div class="modal fade" id="modalVenta" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form id="formVenta">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-shopping-cart me-2"></i> Nueva Venta
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="venta_id" name="id">
                    
                    <!-- Datos de la venta -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Cliente</label>
                            <div class="input-group">
                                <select class="form-select" id="cliente_id" name="cliente_id">
                                    <option value="">Cliente general</option>
                                    <?php 
                                    $clientes->data_seek(0);
                                    while($cliente = $clientes->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $cliente['id']; ?>">
                                        <?php echo htmlspecialchars($cliente['nombre'] . ' ' . ($cliente['apellidos'] ?? '')); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                                <button class="btn btn-outline-primary" type="button" onclick="abrirNuevoCliente()">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold">Fecha</label>
                            <input type="datetime-local" class="form-control" id="fecha_venta" name="fecha">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold">Estado</label>
                            <select class="form-select" id="estado_venta" name="estado">
                                <option value="pagada">Pagada</option>
                                <option value="pendiente">Pendiente</option>
                            </select>
                        </div>
                    </div>

                    <hr>
                    
                    <!-- Productos -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">📦 Productos</h6>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="agregarProductoVenta()">
                            <i class="fas fa-plus me-1"></i> Agregar Producto
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered" id="tablaProductosVenta">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th style="width:100px;">Cantidad</th>
                                    <th style="width:130px;">Precio Unitario</th>
                                    <th style="width:120px;">Subtotal</th>
                                    <th style="width:100px;">Ganancia</th>
                                    <th style="width:80px;">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="detallesVenta">
                                <tr>
                                    <td colspan="6" class="text-center text-secondary py-3">
                                        <i class="fas fa-plus-circle me-2"></i>
                                        Haz clic en "Agregar Producto" para comenzar
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end fw-bold">Total:</td>
                                    <td class="text-end fw-bold text-success h5" id="total_venta">$0.00</td>
                                    <td class="text-end fw-bold text-primary" id="ganancia_total">$0.00</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Observaciones</label>
                        <textarea class="form-control" id="observaciones_venta" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i> Registrar Venta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL PARA VER DETALLE DE VENTA -->
<!-- ============================================= -->
<div class="modal fade" id="modalVerVenta" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-receipt me-2"></i> Detalle de Venta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleVenta">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2 text-secondary">Cargando detalles...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL PARA NUEVO CLIENTE -->
<!-- ============================================= -->
<div class="modal fade" id="modalNuevoCliente" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i> Nuevo Cliente
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold text-danger">Nombre *</label>
                    <input type="text" class="form-control" id="nuevo_cliente_nombre">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Apellidos</label>
                    <input type="text" class="form-control" id="nuevo_cliente_apellidos">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Teléfono</label>
                    <input type="text" class="form-control" id="nuevo_cliente_telefono">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" class="form-control" id="nuevo_cliente_email">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarNuevoCliente()">
                    <i class="fas fa-save me-2"></i> Guardar Cliente
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$page_scripts = '
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../assets/js/ventas.js"></script>
';
include '../layout.php';
?>