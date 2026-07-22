<?php
// pages/productos.php - VERSIÓN COMPLETA CON UNIDADES CORREGIDAS
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar autenticación
if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$page = 'productos';
$page_title = 'Productos';

$db = Database::getInstance()->getConnection();

// Obtener productos con categoría
$productos = $db->query("
    SELECT p.*, c.nombre as categoria_nombre,
           (SELECT COUNT(*) FROM recetas r WHERE r.producto_id = p.id AND (r.eliminado = 0 OR r.eliminado IS NULL)) as tiene_receta,
           (SELECT COUNT(*) FROM productos_compuestos pc WHERE pc.producto_compuesto_id = p.id) as tiene_composicion
    FROM productos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE p.eliminado = 0 OR p.eliminado IS NULL
    ORDER BY p.nombre ASC
");

// Obtener categorías para el filtro y selector
$categorias = $db->query("SELECT * FROM categorias ORDER BY nombre ASC");

// Obtener subproductos disponibles
$subproductos = $db->query("
    SELECT id, nombre, precio_compra, stock, unidad_base
    FROM productos 
    WHERE es_ingrediente = 1 AND tipo_producto = 'simple' AND (eliminado = 0 OR eliminado IS NULL)
    ORDER BY nombre ASC
");

// Obtener unidades disponibles
$unidades = $db->query("
    SELECT DISTINCT unidad_origen as unidad FROM unidades_conversion 
    UNION 
    SELECT DISTINCT unidad_destino FROM unidades_conversion 
    ORDER BY unidad ASC
");

// Estadísticas
$stats = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN tipo_producto = 'simple' THEN 1 ELSE 0 END) as simples,
        SUM(CASE WHEN tipo_producto = 'compuesto' THEN 1 ELSE 0 END) as compuestos,
        SUM(stock) as stock_total
    FROM productos
    WHERE eliminado = 0 OR eliminado IS NULL
")->fetch_assoc();

ob_start();
?>

<style>
    .badge-compuesto { background: #6f42c1; color: white; }
    .badge-simple { background: #0d6efd; color: white; }
    .badge-receta { background: #fd7e14; color: white; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 fw-bold mb-1">📦 Productos</h1>
        <p class="text-secondary">Gestiona tu inventario de productos</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProducto" onclick="resetearModalProducto()">
        <i class="fas fa-plus me-2"></i> Nuevo Producto
    </button>
</div>

<!-- Tarjetas de estadísticas -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-secondary">Total Productos</h6>
                <h3 class="fw-bold"><?php echo $stats['total'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-secondary">Productos Simples</h6>
                <h3 class="fw-bold text-primary"><?php echo $stats['simples'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-secondary">Productos Compuestos</h6>
                <h3 class="fw-bold text-purple"><?php echo $stats['compuestos'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-secondary">Stock Total</h6>
                <h3 class="fw-bold text-success"><?php echo $stats['stock_total'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Filtros y búsqueda -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" id="buscarProducto" class="form-control" placeholder="Buscar por nombre...">
        </div>
    </div>
    <div class="col-md-3">
        <select id="filtroCategoria" class="form-select">
            <option value="">Todas las categorías</option>
            <?php 
            $categorias->data_seek(0);
            while($cat = $categorias->fetch_assoc()): 
            ?>
            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select id="filtroTipo" class="form-select">
            <option value="">Todos los tipos</option>
            <option value="simple">Simples</option>
            <option value="compuesto">Compuestos</option>
        </select>
    </div>
</div>

<!-- Tabla de productos -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="tablaProductos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Tipo</th>
                        <th>Precio Compra</th>
                        <th>Precio Venta</th>
                        <th>Stock</th>
                        <th>Unidad Base</th>
                        <th>Unidad Compra</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($producto = $productos->fetch_assoc()): ?>
                    <tr data-categoria="<?php echo $producto['categoria_id']; ?>" data-tipo="<?php echo $producto['tipo_producto']; ?>">
                        <td><?php echo $producto['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                            <?php if($producto['tiene_receta'] > 0): ?>
                                <span class="badge badge-receta ms-1"><i class="fas fa-utensils"></i> Receta</span>
                            <?php endif; ?>
                            <?php if($producto['tiene_composicion'] > 0): ?>
                                <span class="badge badge-compuesto ms-1"><i class="fas fa-blender"></i> Compuesto</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría'); ?></span></td>
                        <td>
                            <?php if($producto['tipo_producto'] == 'simple'): ?>
                                <span class="badge badge-simple">Simple</span>
                            <?php else: ?>
                                <span class="badge badge-compuesto">Compuesto</span>
                            <?php endif; ?>
                        </td>
                        <td>$<?php echo number_format($producto['precio_compra'] ?? 0, 2); ?></td>
                        <td class="fw-bold text-success">$<?php echo number_format($producto['precio_venta'], 2); ?></td>
                        <td>
                            <?php if($producto['stock'] < 5 && $producto['tipo_producto'] == 'simple'): ?>
                                <span class="badge bg-danger"><?php echo $producto['stock']; ?></span>
                            <?php elseif($producto['stock'] < 20 && $producto['tipo_producto'] == 'simple'): ?>
                                <span class="badge bg-warning text-dark"><?php echo $producto['stock']; ?></span>
                            <?php else: ?>
                                <span class="badge bg-success"><?php echo $producto['stock']; ?></span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-info"><?php echo htmlspecialchars($producto['unidad_base'] ?? 'g'); ?></span></td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($producto['unidad_compra'] ?? 'unidad'); ?></span></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary btn-editar" data-id="<?php echo $producto['id']; ?>" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if($producto['tiene_composicion'] > 0): ?>
                                <button class="btn btn-outline-info btn-ver-composicion" data-id="<?php echo $producto['id']; ?>" title="Ver composición">
                                    <i class="fas fa-blender"></i>
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-outline-danger btn-eliminar" data-id="<?php echo $producto['id']; ?>" title="Eliminar">
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
<!-- MODAL PARA CREAR/EDITAR PRODUCTO -->
<!-- ============================================= -->
<div class="modal fade" id="modalProducto" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formProducto" method="POST" action="../api/productos.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">
                        <i class="fas fa-box me-2"></i> Nuevo Producto
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="producto_id" name="id">
                    
                    <!-- Datos básicos -->
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-semibold">Nombre *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Nombre del producto" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Categoría</label>
                            <div class="input-group">
                                <select class="form-select" id="categoria_id" name="categoria_id">
                                    <option value="">Sin categoría</option>
                                    <?php 
                                    $categorias->data_seek(0);
                                    while($cat = $categorias->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <button class="btn btn-outline-primary" type="button" onclick="abrirNuevaCategoria()">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="2" placeholder="Descripción del producto..."></textarea>
                    </div>

                    <!-- ============================================= -->
                    <!-- TIPO Y UNIDADES -->
                    <!-- ============================================= -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Tipo de Producto</label>
                            <select class="form-select" id="tipo_producto" name="tipo_producto">
                                <option value="simple">Simple (se compra)</option>
                                <option value="compuesto">Compuesto (se elabora)</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Unidad Base (para recetas) *</label>
                            <select class="form-select" id="unidad_base" name="unidad_base" required>
                                <option value="g">Gramos (g)</option>
                                <option value="kg">Kilogramos (kg)</option>
                                <option value="lb">Libras (lb)</option>
                                <option value="oz">Onzas (oz)</option>
                                <option value="ml">Mililitros (ml)</option>
                                <option value="litro">Litros</option>
                                <option value="unidad">Unidad</option>
                            </select>
                            <small class="text-muted">Unidad en la que se usa en recetas</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Unidad de Compra</label>
                            <div class="form-control bg-light" id="unidad_compra_label" style="font-weight: bold; color: #0d6efd;">
                                <span id="unidad_compra_texto">unidad</span>
                            </div>
                            <small class="text-muted">Se define al comprar el producto</small>
                            <input type="hidden" id="unidad_compra" name="unidad_compra" value="unidad">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Contenido por Unidad de Compra</label>
                            <div class="form-control bg-light" id="contenido_unidad_label" style="font-weight: bold; color: #198754;">
                                <span id="contenido_unidad_texto">1.00</span>
                            </div>
                            <small class="text-muted" id="contenido_unidad_descripcion">1 unidad = 1.00 unidad base</small>
                            <input type="hidden" id="contenido_unidad" name="contenido_unidad" value="1.00">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">¿Es ingrediente?</label>
                            <select class="form-select" id="es_ingrediente" name="es_ingrediente">
                                <option value="1">Sí</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3" id="campo_es_producto_final">
                            <label class="form-label fw-semibold">¿Se vende al cliente?</label>
                            <select class="form-select" id="es_producto_final" name="es_producto_final">
                                <option value="1">Sí</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                    </div>

                    <!-- Precios -->
                    <div class="row" id="campos_precios">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Precio de Compra</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" class="form-control" id="precio_compra" name="precio_compra" value="0.00">
                            </div>
                            <small class="text-muted">Costo del producto</small>
                        </div>
                        <div class="col-md-4 mb-3" id="campo_precio_venta">
                            <label class="form-label fw-semibold">Precio de Venta</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" class="form-control" id="precio_venta" name="precio_venta" value="0.00">
                            </div>
                            <small class="text-muted">Precio al cliente (si se vende)</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Stock Mínimo</label>
                            <input type="number" class="form-control" id="stock_minimo" name="stock_minimo" value="5">
                            <small class="text-muted">Alerta de stock bajo</small>
                        </div>
                    </div>

                    <!-- Stock (informativo) -->
                    <div class="row">
                        <div class="col-12 mb-3">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Stock:</strong> Los productos inician con <strong>stock = 0</strong>. 
                                El stock se actualiza automáticamente al registrar <strong>compras</strong>.
                                <input type="hidden" id="stock" name="stock" value="0">
                            </div>
                        </div>
                    </div>

                    <!-- ============================================= -->
                    <!-- SECCIÓN DE COMPOSICIÓN (SOLO PARA COMPUESTOS) -->
                    <!-- ============================================= -->
                    <div id="seccion_composicion" style="display:none;">
                        <hr>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0">
                                <i class="fas fa-blender me-2"></i> Composición del Producto
                                <span class="badge bg-secondary" id="contador_subproductos">0</span>
                            </h6>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="agregarSubproducto()">
                                <i class="fas fa-plus me-1"></i> Agregar Subproducto
                            </button>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered" id="tablaSubproductos">
                                <thead>
                                    <tr>
                                        <th>Subproducto</th>
                                        <th style="width:120px;">Cantidad</th>
                                        <th style="width:100px;">Unidad</th>
                                        <th style="width:120px;">Costo Unit.</th>
                                        <th style="width:120px;">Costo Total</th>
                                        <th style="width:80px;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="detallesSubproductos">
                                    <tr>
                                        <td colspan="6" class="text-center text-secondary py-3">
                                            <i class="fas fa-plus-circle me-2"></i>
                                            Agrega los subproductos que componen este producto
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot id="pie_subproductos" style="display:none;">
                                    <tr class="table-success">
                                        <td colspan="4" class="text-end fw-bold">Costo de Producción:</td>
                                        <td class="text-end fw-bold text-success h5" id="costo_produccion_total">$0.00</td>
                                        <td></td>
                                    </tr>
                                    <tr class="table-info">
                                        <td colspan="4" class="text-end fw-bold">Precio de Venta Sugerido:</td>
                                        <td class="text-end fw-bold text-primary h5" id="precio_produccion_sugerido">$0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-semibold">Margen de Ganancia (%)</label>
                                <input type="number" step="0.01" class="form-control" id="margen_composicion" value="50" min="0" max="100">
                                <small class="text-muted">Define el porcentaje de ganancia para calcular el precio</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-semibold">Precio de Venta Sugerido</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" class="form-control" id="precio_sugerido_composicion" placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-semibold">Costo de Producción</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="text" class="form-control" id="costo_produccion_input" readonly disabled style="background:#f8f9fa;font-weight:bold;color:#dc3545;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Guardar Producto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL PARA NUEVA CATEGORÍA -->
<!-- ============================================= -->
<div class="modal fade" id="modalNuevaCategoria" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-tag me-2"></i> Nueva Categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nombre *</label>
                    <input type="text" class="form-control" id="nueva_categoria_nombre" placeholder="Ej: Panadería">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Descripción</label>
                    <textarea class="form-control" id="nueva_categoria_descripcion" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarNuevaCategoria()">
                    <i class="fas fa-save me-2"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL PARA VER COMPOSICIÓN -->
<!-- ============================================= -->
<div class="modal fade" id="modalVerComposicion" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-blender me-2"></i> Composición del Producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleComposicion">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2 text-secondary">Cargando composición...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$page_scripts = '<script src="../assets/js/productos.js"></script>';
include '../layout.php';
?>