<?php
// pages/compras.php - MÓDULO DE COMPRAS COMPLETO CON UNIDADES
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$page = 'compras';
$page_title = 'Compras';

$db = Database::getInstance()->getConnection();

// Obtener compras (no eliminadas)
$compras = $db->query("
    SELECT c.*, u.nombre as usuario_nombre
    FROM compras c
    LEFT JOIN usuarios u ON c.usuario_id = u.id
    WHERE (c.eliminado = 0 OR c.eliminado IS NULL)
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

// Obtener productos
$productos = $db->query("
    SELECT id, nombre, precio_compra, stock, unidad_base, unidad_compra, contenido_unidad
    FROM productos 
    WHERE tipo_producto = 'simple'
    ORDER BY nombre ASC
");

// Obtener categorías para nuevo producto
$categorias = $db->query("SELECT * FROM categorias ORDER BY nombre ASC");

// Obtener tarjetas de pago
$tarjetas = $db->query("
    SELECT * FROM tarjetas_pago 
    WHERE activo = 1 
    ORDER BY propietario ASC
");

// Estadísticas
$stats = $db->query("
    SELECT 
        COUNT(*) as total_compras,
        SUM(total) as total_invertido,
        AVG(total) as promedio_compra
    FROM compras
    WHERE (eliminado = 0 OR eliminado IS NULL)
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
                        <td><strong><?php echo htmlspecialchars($compra['proveedor_nombre'] ?? 'N/A'); ?></strong></td>
                        <td>
                            <span class="badge bg-<?php echo $compra['tipo_compra'] == 'mayorista' ? 'primary' : 'secondary'; ?>">
                                <?php echo ucfirst($compra['tipo_compra'] ?? 'Minorista'); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($compra['numero_factura'] ?? '-'); ?></td>
                        <td><?php echo formatDate($compra['fecha']); ?></td>
                        <td class="fw-bold text-success">$<?php echo number_format($compra['total'], 2); ?></td>
                        <td>
                            <?php
                            $estado_colors = ['pendiente' => 'warning', 'pagada' => 'success', 'parcial' => 'info', 'cancelada' => 'danger'];
                            $color = $estado_colors[$compra['estado']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $color; ?>">
                                <?php echo ucfirst($compra['estado'] ?? 'Pendiente'); ?>
                            </span>
                            <?php if($compra['editado_por']): ?>
                                <span class="badge bg-warning text-dark ms-1" title="Editada"><i class="fas fa-edit"></i></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary btn-ver" data-id="<?php echo $compra['id']; ?>" title="Ver detalle"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-outline-warning btn-editar-compra" data-id="<?php echo $compra['id']; ?>" title="Editar compra"><i class="fas fa-edit"></i></button>
                                <?php if($compra['estado'] != 'pagada' && $compra['estado'] != 'cancelada'): ?>
                                <button class="btn btn-outline-success btn-pagar" data-id="<?php echo $compra['id']; ?>" title="Registrar pago"><i class="fas fa-money-bill-wave"></i></button>
                                <?php endif; ?>
                                <button class="btn btn-outline-danger btn-eliminar" data-id="<?php echo $compra['id']; ?>" title="Eliminar"><i class="fas fa-trash"></i></button>
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
                    <h5 class="modal-title"><i class="fas fa-shopping-cart me-2"></i> Nueva Compra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="alertas_compra"></div>
                    
                    <input type="hidden" id="compra_id" name="id">
                    <input type="hidden" id="metodo_pago" name="metodo_pago" value="pendiente">
                    <input type="hidden" id="tarjeta_id" name="tarjeta_id" value="">
                    <input type="hidden" id="tipo_pago_linea" name="tipo_pago_linea" value="">
                    
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
                                <button class="btn btn-outline-primary" type="button" onclick="abrirNuevoProveedor()"><i class="fas fa-plus"></i></button>
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
                                    <th style="width:100px;">Unidad</th>
                                    <th style="width:130px;">Precio Unitario</th>
                                    <th style="width:120px;">Subtotal</th>
                                    <th style="width:80px;">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="detallesCompra">
                                <tr>
                                    <td colspan="6" class="text-center text-secondary py-3">
                                        <i class="fas fa-plus-circle me-2"></i> Haz clic en "Agregar Producto"
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end fw-bold">Total:</td>
                                    <td class="text-end fw-bold text-success h5" id="total_compra">$0.00</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Observaciones</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i> Guardar Compra</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL PARA EDITAR COMPRA -->
<!-- ============================================= -->
<div class="modal fade" id="modalEditarCompra" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Editar Compra #<span id="editar_compra_numero"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarCompra">
                <div class="modal-body">
                    <input type="hidden" id="editar_compra_id" name="id">
                    
                    <div class="alert alert-warning d-none" id="editar_alerta_pagada">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>⚠️ Esta compra ya está pagada.</strong> 
                        Al editarla, se ajustará el stock automáticamente y se registrará en el historial.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Proveedor *</label>
                            <select class="form-select" id="editar_proveedor_id" name="proveedor_id" required>
                                <option value="">Seleccionar proveedor</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Tipo de Compra</label>
                            <select class="form-select" id="editar_tipo_compra" name="tipo_compra">
                                <option value="minorista">🛒 Compra Minorista</option>
                                <option value="mayorista">📦 Compra al por Mayor</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3" id="editar_campo_factura" style="display:none;">
                            <label class="form-label fw-semibold">Número de Factura</label>
                            <input type="text" class="form-control" id="editar_numero_factura" placeholder="Factura del proveedor">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Fecha de Compra</label>
                            <input type="datetime-local" class="form-control" id="editar_fecha" name="fecha">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Fecha de Entrega</label>
                            <input type="date" class="form-control" id="editar_fecha_entrega" name="fecha_entrega">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Motivo de la edición</label>
                        <textarea class="form-control" id="editar_motivo" rows="2" placeholder="Explica por qué estás editando esta compra..." required></textarea>
                        <small class="text-muted">Este motivo quedará registrado en el historial</small>
                    </div>

                    <hr>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">📦 Productos</h6>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="agregarProductoEditar()">
                            <i class="fas fa-plus me-1"></i> Agregar Producto
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered" id="tablaProductosEditar">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th style="width:100px;">Cantidad</th>
                                    <th style="width:100px;">Unidad</th>
                                    <th style="width:130px;">Precio Unitario</th>
                                    <th style="width:120px;">Subtotal</th>
                                    <th style="width:80px;">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="detallesEditar">
                                <tr>
                                    <td colspan="6" class="text-center text-secondary py-3">
                                        <i class="fas fa-spinner fa-spin me-2"></i> Cargando productos...
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end fw-bold">Total:</td>
                                    <td class="text-end fw-bold text-success h5" id="editar_total">$0.00</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save me-2"></i> Guardar Cambios</button>
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
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i> Nuevo Proveedor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label fw-semibold text-danger">Nombre *</label><input type="text" class="form-control" id="nuevo_proveedor_nombre" placeholder="Nombre del proveedor"></div>
                <div class="mb-3"><label class="form-label fw-semibold">Apellidos</label><input type="text" class="form-control" id="nuevo_proveedor_apellidos" placeholder="Apellidos (opcional)"></div>
                <div class="mb-3"><label class="form-label fw-semibold">Teléfono</label><input type="text" class="form-control" id="nuevo_proveedor_telefono" placeholder="55 1234 5678"></div>
                <div class="mb-3"><label class="form-label fw-semibold">Email</label><input type="email" class="form-control" id="nuevo_proveedor_email" placeholder="proveedor@email.com"></div>
                <div class="mb-3"><label class="form-label fw-semibold">Área de Trabajo</label><input type="text" class="form-control" id="nuevo_proveedor_area" placeholder="Distribución, Panadería..."></div>
                <input type="hidden" id="nuevo_proveedor_tipo" value="distribuidor">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarNuevoProveedor()"><i class="fas fa-save me-2"></i> Guardar Proveedor</button>
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
                        <select class="form-select" id="nuevo_producto_categoria">
                            <option value="">Sin categoría</option>
                            <?php 
                            $categorias->data_seek(0);
                            while($cat = $categorias->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Descripción</label>
                    <textarea class="form-control" id="nuevo_producto_descripcion" rows="2"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-semibold">Precio Compra</label>
                        <input type="number" step="0.01" class="form-control" id="nuevo_producto_precio_compra" value="0.00">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-semibold">Precio Venta</label>
                        <input type="number" step="0.01" class="form-control" id="nuevo_producto_precio_venta" value="0.00">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-semibold">Stock Inicial</label>
                        <input type="number" class="form-control" id="nuevo_producto_stock" value="0">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-semibold">Unidad Base</label>
                        <select class="form-select" id="nuevo_producto_unidad_base">
                            <option value="g">Gramos (g)</option>
                            <option value="kg">Kilogramos (kg)</option>
                            <option value="lb">Libras (lb)</option>
                            <option value="oz">Onzas (oz)</option>
                            <option value="ml">Mililitros (ml)</option>
                            <option value="litro">Litros</option>
                            <option value="unidad">Unidad</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Unidad de Compra</label>
                        <select class="form-select" id="nuevo_producto_unidad_compra">
                            <option value="unidad">Unidad</option>
                            <option value="g">Gramos (g)</option>
                            <option value="kg">Kilogramos (kg)</option>
                            <option value="lb">Libras (lb)</option>
                            <option value="oz">Onzas (oz)</option>
                            <option value="ml">Mililitros (ml)</option>
                            <option value="litro">Litros</option>
                            <option value="paquete">Paquete</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Contenido por Unidad</label>
                        <input type="number" step="0.01" class="form-control" id="nuevo_producto_contenido" value="1.00">
                        <small class="text-muted">Ej: 1 paquete = 1000g</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">¿Es ingrediente?</label>
                        <select class="form-select" id="nuevo_producto_es_ingrediente">
                            <option value="1">Sí</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                </div>
                <input type="hidden" id="nuevo_producto_tipo" value="simple">
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
<!-- MODAL PARA VER DETALLE DE COMPRA -->
<!-- ============================================= -->
<div class="modal fade" id="modalVerCompra" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-receipt me-2"></i> Detalle de Compra #<span id="detalle_compra_id"></span>
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
            <div class="modal-footer" id="detalle_modal_footer"></div>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL PARA REGISTRAR PAGO -->
<!-- ============================================= -->
<div class="modal fade" id="modalPago" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i> Registrar Pago</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPago">
                <div class="modal-body">
                    <input type="hidden" id="pago_compra_id">
                    
                    <div class="card bg-light mb-3">
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6"><span class="text-secondary">Total</span><h4 id="pago_total" class="text-primary">$0.00</h4></div>
                                <div class="col-6"><span class="text-secondary">Pendiente</span><h4 id="pago_pendiente" class="text-danger">$0.00</h4></div>
                            </div>
                            <div class="row text-center mt-2">
                                <div class="col-12">
                                    <div class="progress" style="height:10px;"><div id="pago_progress" class="progress-bar bg-success" style="width:0%;"></div></div>
                                    <small class="text-muted" id="pago_porcentaje">0% pagado</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-danger">Monto a Pagar *</label>
                        <input type="number" step="0.01" class="form-control form-control-lg" id="pago_monto" placeholder="0.00" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Método de Pago</label>
                            <select class="form-select" id="pago_metodo">
                                <option value="efectivo">💵 Efectivo</option>
                                <option value="transferencia">🏦 Transferencia</option>
                                <option value="pago_linea">📱 Pago en Línea</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="campo_tarjeta_pago" style="display:none;">
                            <label class="form-label fw-semibold">Tarjeta</label>
                            <select class="form-select" id="pago_tarjeta_id">
                                <option value="">Seleccionar tarjeta...</option>
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
                    
                    <div class="mb-3" id="campo_plataforma_pago" style="display:none;">
                        <label class="form-label fw-semibold">Plataforma</label>
                        <select class="form-select" id="pago_plataforma">
                            <option value="">Seleccionar...</option>
                            <option value="transfermovil">📱 Transfermóvil</option>
                            <option value="enzona">💳 Enzona</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Referencia</label>
                        <input type="text" class="form-control" id="pago_referencia" placeholder="Número de referencia (opcional)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Observaciones</label>
                        <textarea class="form-control" id="pago_observaciones" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check me-2"></i> Registrar Pago</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL DE PAGO ANIDADO (DENTRO DEL DETALLE) -->
<!-- ============================================= -->
<div class="modal fade" id="modalPagoDetalle" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i> Registrar Pago</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPagoDetalle">
                <div class="modal-body">
                    <input type="hidden" id="pago_detalle_compra_id">
                    
                    <div class="card bg-light mb-3">
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6"><span class="text-secondary">Total</span><h4 id="pago_detalle_total" class="text-primary">$0.00</h4></div>
                                <div class="col-6"><span class="text-secondary">Pendiente</span><h4 id="pago_detalle_pendiente" class="text-danger">$0.00</h4></div>
                            </div>
                            <div class="row text-center mt-2">
                                <div class="col-12">
                                    <div class="progress" style="height:10px;"><div id="pago_detalle_progress" class="progress-bar bg-success" style="width:0%;"></div></div>
                                    <small class="text-muted" id="pago_detalle_porcentaje">0% pagado</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-danger">Monto a Pagar *</label>
                        <input type="number" step="0.01" class="form-control form-control-lg" id="pago_detalle_monto" placeholder="0.00" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Método de Pago</label>
                            <select class="form-select" id="pago_detalle_metodo">
                                <option value="efectivo">💵 Efectivo</option>
                                <option value="transferencia">🏦 Transferencia</option>
                                <option value="pago_linea">📱 Pago en Línea</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="campo_detalle_tarjeta" style="display:none;">
                            <label class="form-label fw-semibold">Tarjeta</label>
                            <select class="form-select" id="pago_detalle_tarjeta_id">
                                <option value="">Seleccionar tarjeta...</option>
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
                    
                    <div class="mb-3" id="campo_detalle_plataforma" style="display:none;">
                        <label class="form-label fw-semibold">Plataforma</label>
                        <select class="form-select" id="pago_detalle_plataforma">
                            <option value="">Seleccionar...</option>
                            <option value="transfermovil">📱 Transfermóvil</option>
                            <option value="enzona">💳 Enzona</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Referencia</label>
                        <input type="text" class="form-control" id="pago_detalle_referencia" placeholder="Número de referencia (opcional)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Observaciones</label>
                        <textarea class="form-control" id="pago_detalle_observaciones" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check me-2"></i> Registrar Pago</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL DE CONFIRMACIÓN PARA PAGAR TODO -->
<!-- ============================================= -->
<div class="modal fade" id="modalConfirmarPagoTotal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i> Confirmar Pago Total
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="fw-bold">¿Seguro que deseas pagar el saldo completo de esta compra?</p>
                <p class="text-danger small">
                    <i class="fas fa-exclamation-circle me-1"></i>
                    Esta acción registrará el pago total y no se puede deshacer.
                </p>
                <input type="hidden" id="confirmar_pago_compra_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="btnConfirmarPagoTotal">
                    <i class="fas fa-check-circle me-2"></i> Sí, Pagar Todo
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$page_scripts = '<script src="../assets/js/compras.js"></script>';
include '../layout.php';
?>