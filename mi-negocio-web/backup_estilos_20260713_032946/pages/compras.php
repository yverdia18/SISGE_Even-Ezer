<?php
// pages/compras.php - VERSIÓN COMPLETA CON PAGOS MÚLTIPLES Y DESCUENTOS
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar autenticación
if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$page = 'compras';
$page_title = 'Compras';

$db = Database::getInstance()->getConnection();

// Obtener compras
$compras = $db->query("
    SELECT c.*, u.nombre as usuario_nombre
    FROM compras c
    LEFT JOIN usuarios u ON c.usuario_id = u.id
    ORDER BY c.fecha DESC
    LIMIT 50
");

// Obtener proveedores (clientes que son proveedores)
$proveedores = $db->query("
    SELECT id, nombre, apellidos, telefono, email 
    FROM clientes 
    WHERE es_proveedor = 1 
    ORDER BY nombre ASC
");

// Obtener productos para el detalle
$productos = $db->query("
    SELECT id, nombre, precio_compra, stock 
    FROM productos 
    WHERE tipo_producto = 'simple'
    ORDER BY nombre ASC
");

// Obtener tarjetas de pago
$tarjetas = $db->query("
    SELECT * FROM tarjetas_pago 
    WHERE activo = 1 
    ORDER BY propietario ASC
");

// Estadísticas de compras
$stats = $db->query("
    SELECT 
        COUNT(*) as total_compras,
        SUM(total) as total_invertido,
        AVG(total) as promedio_compra
    FROM compras
")->fetch_assoc();

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 fw-bold mb-1">📦 Compras</h1>
        <p class="text-secondary">Registro de compras a proveedores</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCompra">
        <i class="fas fa-plus me-2"></i> Nueva Compra
    </button>
</div>

<!-- Tarjetas de estadísticas -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-secondary">Total Compras</h6>
                <h3 class="fw-bold"><?php echo $stats['total_compras'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-secondary">Total Invertido</h6>
                <h3 class="fw-bold text-success">
                    $<?php echo number_format($stats['total_invertido'] ?? 0, 2); ?>
                </h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-secondary">Promedio por Compra</h6>
                <h3 class="fw-bold text-primary">
                    $<?php echo number_format($stats['promedio_compra'] ?? 0, 2); ?>
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
            <input type="text" id="buscarCompra" class="form-control" placeholder="Buscar por proveedor o factura...">
        </div>
    </div>
    <div class="col-md-3">
        <select id="filtroEstado" class="form-select">
            <option value="">Todos los estados</option>
            <option value="pendiente">Pendiente</option>
            <option value="pagada">Pagada</option>
            <option value="parcial">Parcial</option>
            <option value="cancelada">Cancelada</option>
        </select>
    </div>
</div>

<!-- Tabla de compras -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="tablaCompras">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Proveedor</th>
                        <th>Tipo</th>
                        <th>Factura</th>
                        <th>Fecha</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($compra = $compras->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $compra['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($compra['proveedor_nombre'] ?? 'N/A'); ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $compra['tipo_compra'] == 'mayorista' ? 'primary' : 'secondary'; ?>">
                                <?php echo ucfirst($compra['tipo_compra'] ?? 'Minorista'); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($compra['numero_factura'] ?? '-'); ?></td>
                        <td><?php echo formatDate($compra['fecha']); ?></td>
                        <td class="fw-bold text-success">
                            $<?php echo number_format($compra['total'], 2); ?>
                        </td>
                        <td>
                            <?php
                            $estado_colors = [
                                'pendiente' => 'warning',
                                'pagada' => 'success',
                                'parcial' => 'info',
                                'cancelada' => 'danger'
                            ];
                            $color = $estado_colors[$compra['estado']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $color; ?>">
                                <?php echo ucfirst($compra['estado'] ?? 'Pendiente'); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary btn-ver" data-id="<?php echo $compra['id']; ?>" title="Ver detalle">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if($compra['estado'] != 'pagada' && $compra['estado'] != 'cancelada'): ?>
                                <button class="btn btn-outline-success btn-pagar"                            data-id="<?php echo $compra['id']; ?>" 
                                        title="Registrar pago">
                                    <i class="fas fa-money-bill-wave"></i>
                                </button>
                                <?php endif; ?>
                                <!-- Botón eliminar en la tabla de compras -->
                                <button class="btn btn-outline-danger btn-eliminar" 
                                        data-id="<?php echo $compra['id']; ?>" 
                                        title="Mover a papelera">
                                    <i class="fas fa-trash"></i>
                                </button>
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
<!-- MODAL PARA NUEVA COMPRA -->
<!-- ============================================= -->
<div class="modal fade" id="modalCompra" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form id="formCompra">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-shopping-cart me-2"></i> Nueva Compra
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="compra_id" name="id">
                    <input type="hidden" id="metodo_pago" name="metodo_pago" value="pendiente">
                    <input type="hidden" id="tarjeta_id" name="tarjeta_id" value="">
                    <input type="hidden" id="tipo_pago_linea" name="tipo_pago_linea" value="">
                    
                    <!-- ========== DATOS DE LA COMPRA ========== -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Proveedor *</label>
                            <div class="input-group">
                                <select class="form-select" id="proveedor_id" name="proveedor_id" required>
                                    <option value="">Seleccionar proveedor</option>
                                    <?php 
                                    $proveedores->data_seek(0);
                                    while($prov = $proveedores->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $prov['id']; ?>">
                                        <?php echo htmlspecialchars($prov['nombre'] . ' ' . ($prov['apellidos'] ?? '')); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                                <button class="btn btn-outline-primary" type="button" onclick="abrirNuevoProveedor()">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Tipo de Compra</label>
                            <select class="form-select" id="tipo_compra" name="tipo_compra">
                                <option value="minorista">🛒 Compra Minorista</option>
                                <option value="mayorista">📦 Compra al por Mayor</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3" id="campo_factura" style="display:none;">
                            <label class="form-label fw-semibold">Número de Factura</label>
                            <input type="text" class="form-control" id="numero_factura" name="numero_factura" placeholder="Factura del proveedor">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Fecha de Compra</label>
                            <input type="datetime-local" class="form-control" id="fecha" name="fecha">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Fecha de Entrega</label>
                            <input type="date" class="form-control" id="fecha_entrega" name="fecha_entrega">
                        </div>
                    </div>

                    <!-- ========== PRODUCTOS ========== -->
                    <hr>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">📦 Productos</h6>
                        <div>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="agregarProducto()">
                                <i class="fas fa-plus me-1"></i> Agregar Producto
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="abrirNuevoProducto()">
                                <i class="fas fa-box me-1"></i> Nuevo Producto
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered" id="tablaProductosCompra">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th style="width:100px;">Cantidad</th>
                                    <th style="width:130px;">Precio Unitario</th>
                                    <th style="width:120px;">Subtotal</th>
                                    <th style="width:80px;">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="detallesCompra">
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-3">
                                        <i class="fas fa-plus-circle me-2"></i>
                                        Haz clic en "Agregar Producto" para comenzar
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end fw-bold">Subtotal:</td>
                                    <td class="text-end fw-bold" id="subtotal_total">$0.00</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end fw-bold text-success h5">Total:</td>
                                    <td class="text-end fw-bold text-success h5" id="total_compra">$0.00</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Observaciones</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="2" placeholder="Notas adicionales..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Guardar Compra
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL PARA NUEVO PROVEEDOR -->
<!-- ============================================= -->
<div class="modal fade" id="modalNuevoProveedor" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i> Nuevo Proveedor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold text-danger">Nombre *</label>
                    <input type="text" class="form-control" id="nuevo_proveedor_nombre" placeholder="Nombre del proveedor">
                    <small class="text-muted">Este campo es obligatorio</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Apellidos</label>
                    <input type="text" class="form-control" id="nuevo_proveedor_apellidos" placeholder="Apellidos (opcional)">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Teléfono</label>
                    <input type="text" class="form-control" id="nuevo_proveedor_telefono" placeholder="55 1234 5678 (opcional)">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" class="form-control" id="nuevo_proveedor_email" placeholder="proveedor@email.com (opcional)">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Área de Trabajo</label>
                    <input type="text" class="form-control" id="nuevo_proveedor_area" placeholder="Distribución, Panadería... (opcional)">
                </div>
                <input type="hidden" id="nuevo_proveedor_tipo" value="distribuidor">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarNuevoProveedor()">
                    <i class="fas fa-save me-2"></i> Guardar Proveedor
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL PARA NUEVA TARJETA -->
<!-- ============================================= -->
<div class="modal fade" id="modalNuevaTarjeta" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-credit-card me-2"></i> Nueva Tarjeta de Pago
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold text-danger">Propietario *</label>
                    <input type="text" class="form-control" id="nueva_tarjeta_propietario" placeholder="Nombre del propietario">
                    <small class="text-muted">Este campo es obligatorio</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold text-danger">Número de Cuenta *</label>
                    <input type="text" class="form-control" id="nueva_tarjeta_numero" placeholder="Número de cuenta">
                    <small class="text-muted">Este campo es obligatorio</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Moneda</label>
                    <select class="form-select" id="nueva_tarjeta_moneda">
                        <option value="CUP">CUP</option>
                        <option value="USD">USD</option>
                        <option value="MLC">MLC</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Teléfono</label>
                    <input type="text" class="form-control" id="nueva_tarjeta_telefono" placeholder="Teléfono (opcional)">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Banco</label>
                    <input type="text" class="form-control" id="nueva_tarjeta_banco" placeholder="Banco (opcional)">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarNuevaTarjeta()">
                    <i class="fas fa-save me-2"></i> Guardar Tarjeta
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL PARA NUEVO PRODUCTO (DESDE COMPRAS) -->
<!-- ============================================= -->
<div class="modal fade" id="modalNuevoProductoCompra" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-box me-2"></i> Nuevo Producto
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label fw-semibold">Nombre *</label>
                        <input type="text" class="form-control" id="nuevo_producto_nombre" placeholder="Nombre del producto">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Categoría</label>
                        <div class="input-group">
                            <select class="form-select" id="nuevo_producto_categoria">
                                <option value="">Sin categoría</option>
                                <?php 
                                $categorias = $db->query("SELECT * FROM categorias ORDER BY nombre ASC");
                                while($cat = $categorias->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                                <?php endwhile; ?>
                            </select>
                            <button class="btn btn-outline-primary" type="button" onclick="abrirNuevaCategoriaCompra()">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Descripción</label>
                    <textarea class="form-control" id="nuevo_producto_descripcion" rows="2"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Precio Compra</label>
                        <input type="number" step="0.01" class="form-control" id="nuevo_producto_precio_compra" value="0.00">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Precio Venta</label>
                        <input type="number" step="0.01" class="form-control" id="nuevo_producto_precio_venta" value="0.00">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Stock Inicial</label>
                        <input type="number" class="form-control" id="nuevo_producto_stock" value="0">
                    </div>
                </div>
                <input type="hidden" id="nuevo_producto_tipo" value="simple">
                <input type="hidden" id="nuevo_producto_es_ingrediente" value="1">
                <input type="hidden" id="nuevo_producto_es_final" value="1">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarNuevoProductoCompra()">
                    <i class="fas fa-save me-2"></i> Guardar Producto
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL PARA NUEVA CATEGORÍA (DESDE COMPRAS) -->
<!-- ============================================= -->
<div class="modal fade" id="modalNuevaCategoriaCompra" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-tag me-2"></i> Nueva Categoría
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nombre *</label>
                    <input type="text" class="form-control" id="nueva_categoria_compra_nombre" placeholder="Ej: Panadería">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Descripción</label>
                    <textarea class="form-control" id="nueva_categoria_compra_descripcion" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarNuevaCategoriaCompra()">
                    <i class="fas fa-save me-2"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL PARA VER DETALLE DE COMPRA -->
<!-- ============================================= -->
<div class="modal fade" id="modalVerCompra" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-receipt me-2"></i> Detalle de Compra
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleCompra">
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
<!-- MODAL PARA REGISTRAR PAGO -->
<!-- ============================================= -->
<div class="modal fade" id="modalPago" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-money-bill-wave me-2"></i> Registrar Pagos
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPago">
                <div class="modal-body">
                    <input type="hidden" id="pago_compra_id">
                    
                    <!-- Resumen de la compra -->
                    <div class="card bg-light mb-3">
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-4">
                                    <span class="text-secondary">Total</span>
                                    <h4 id="pago_total" class="text-primary">$0.00</h4>
                                </div>
                                <div class="col-4">
                                    <span class="text-secondary">Pagado</span>
                                    <h4 id="pago_pagado" class="text-success">$0.00</h4>
                                </div>
                                <div class="col-4">
                                    <span class="text-secondary">Pendiente</span>
                                    <h4 id="pago_pendiente" class="text-danger">$0.00</h4>
                                </div>
                            </div>
                            <div class="row text-center mt-2">
                                <div class="col-12">
                                    <div class="progress" style="height: 10px;">
                                        <div id="pago_progress" class="progress-bar bg-success" style="width: 0%;" role="progressbar"></div>
                                    </div>
                                    <small class="text-muted" id="pago_porcentaje">0% pagado</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pagos registrados -->
                    <div id="pagos_registrados">
                        <h6 class="fw-bold mb-3">📋 Pagos Registrados</h6>
                        <div id="lista_pagos" class="mb-3">
                            <p class="text-secondary text-center py-2">No hay pagos registrados aún</p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Agregar nuevo pago -->
                    <div id="nuevo_pago_container">
                        <h6 class="fw-bold mb-3">
                            <i class="fas fa-plus-circle me-2"></i> Agregar Método de Pago
                        </h6>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-semibold">Método de Pago *</label>
                                <select class="form-select" id="nuevo_pago_metodo">
                                    <option value="efectivo">💵 Efectivo</option>
                                    <option value="transferencia">🏦 Transferencia</option>
                                    <option value="pago_linea">📱 Pago en Línea</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-semibold">Monto a Pagar *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" class="form-control" id="nuevo_pago_monto" placeholder="0.00">
                                </div>
                                <small class="text-muted">Monto que pagas al proveedor</small>
                            </div>
                            <div class="col-md-3 mb-3" id="campo_nuevo_plataforma" style="display:none;">
                                <label class="form-label fw-semibold">Plataforma</label>
                                <select class="form-select" id="nuevo_pago_plataforma">
                                    <option value="">Seleccionar...</option>
                                    <option value="transfermovil">📱 Transfermóvil</option>
                                    <option value="enzona">💳 Enzona</option>
                                </select>
                                <small class="text-muted" id="plataforma_bonificacion_texto">Bonificación: 3% o 6% (solo Pago en Línea)</small>
                            </div>
                            <div class="col-md-2 mb-3" id="campo_nuevo_tarjeta" style="display:none;">
                                <label class="form-label fw-semibold">Tarjeta</label>
                                <select class="form-select" id="nuevo_pago_tarjeta">
                                    <option value="">Seleccionar...</option>
                                    <?php 
                                    $tarjetas->data_seek(0);
                                    while($tarjeta = $tarjetas->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $tarjeta['id']; ?>">
                                        <?php echo htmlspecialchars($tarjeta['propietario'] . ' - ' . $tarjeta['numero_cuenta']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Referencia</label>
                                <input type="text" class="form-control" id="nuevo_pago_referencia" placeholder="Número de referencia (opcional)">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Observaciones</label>
                                <input type="text" class="form-control" id="nuevo_pago_observaciones" placeholder="Notas (opcional)">
                            </div>
                        </div>
                        
                        <!-- Alerta de bonificación -->
                        <div class="alert alert-success d-none" id="alerta_bonificacion">
                            <i class="fas fa-gift me-2"></i>
                            <span id="mensaje_bonificacion"></span>
                            <input type="hidden" id="descuento_porcentaje_pago" value="0">
                            <input type="hidden" id="descuento_monto_pago" value="0">
                        </div>
                        
                        <button type="button" class="btn btn-outline-primary" onclick="agregarPagoALista()">
                            <i class="fas fa-plus me-2"></i> Agregar Pago
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btn_registrar_pagos">
                        <i class="fas fa-check me-2"></i> Registrar Todos los Pagos
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$page_scripts = '
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../assets/js/compras.js"></script>
';
include '../layout.php';
?>