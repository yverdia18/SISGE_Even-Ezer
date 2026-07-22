<?php
// pages/recetas.php - VERSIÓN COMPLETA CON UNIDAD BASE
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$page = 'recetas';
$page_title = 'Recetas';

$db = Database::getInstance()->getConnection();

// Obtener recetas activas
$recetas = $db->query("
    SELECT r.*, p.nombre as producto_nombre, p.precio_venta as precio_actual
    FROM recetas r
    LEFT JOIN productos p ON r.producto_id = p.id
    WHERE r.activo = 1 AND (r.eliminado = 0 OR r.eliminado IS NULL)
    ORDER BY r.nombre ASC
");

// Obtener ingredientes con unidad base
$ingredientes = $db->query("
    SELECT id, nombre, precio_compra, stock, unidad_base
    FROM productos 
    WHERE es_ingrediente = 1 AND tipo_producto = 'simple' 
    AND (eliminado = 0 OR eliminado IS NULL)
    ORDER BY nombre ASC
");

// Obtener productos finales
$productos_finales = $db->query("
    SELECT id, nombre, precio_venta 
    FROM productos 
    WHERE es_producto_final = 1 AND (eliminado = 0 OR eliminado IS NULL)
    ORDER BY nombre ASC
");

// Obtener categorías para los modales
$categorias = $db->query("SELECT * FROM categorias ORDER BY nombre ASC");

ob_start();
?>

<style>
    .receta-card { transition: all 0.3s ease; cursor: default; }
    .receta-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.12) !important; }
    .receta-icon { width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; }
    .ingrediente-stock { font-weight: 600; }
    .badge-receta { font-size: 0.75rem; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 fw-bold mb-1">🧪 Recetas</h1>
        <p class="text-secondary">Crea y gestiona tus recetas</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalReceta" onclick="resetearModalReceta()">
        <i class="fas fa-plus me-2"></i> Nueva Receta
    </button>
</div>

<!-- Tarjetas de recetas -->
<div class="row g-4" id="listaRecetas">
    <?php if($recetas && $recetas->num_rows > 0): ?>
        <?php while($receta = $recetas->fetch_assoc()): ?>
        <div class="col-md-4 col-lg-3">
            <div class="card border-0 shadow-sm h-100 receta-card" data-id="<?php echo $receta['id']; ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="receta-icon bg-primary bg-opacity-10 p-3 rounded-3">
                            <i class="fas fa-utensils fa-2x text-primary"></i>
                        </div>
                        <span class="badge bg-success badge-receta"><?php echo $receta['rendimiento']; ?> <?php echo $receta['unidad_medida'] ?? 'raciones'; ?></span>
                    </div>
                    <h5 class="fw-bold mt-3"><?php echo htmlspecialchars($receta['nombre']); ?></h5>
                    <p class="text-secondary small">
                        <?php echo htmlspecialchars($receta['producto_nombre'] ?? 'Producto final'); ?>
                        <?php if($receta['tiempo_preparacion'] > 0): ?>
                            <span class="badge bg-info ms-1"><?php echo $receta['tiempo_preparacion']; ?> min</span>
                        <?php endif; ?>
                    </p>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div>
                            <span class="text-muted small">Costo Total</span>
                            <h6 class="fw-bold text-danger">$<?php echo number_format($receta['costo_total'] ?? 0, 2); ?></h6>
                            <small class="text-muted">$<?php echo number_format(($receta['costo_total'] ?? 0) / max($receta['rendimiento'], 1), 2); ?> / ración</small>
                        </div>
                        <div>
                            <span class="text-muted small">Precio sugerido</span>
                            <h6 class="fw-bold text-success">$<?php echo number_format($receta['precio_venta_sugerido'] ?? 0, 2); ?></h6>
                            <small class="text-muted">$<?php echo number_format(($receta['precio_venta_sugerido'] ?? 0) / max($receta['rendimiento'], 1), 2); ?> / ración</small>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="d-flex gap-1 flex-wrap">
                            <button class="btn btn-sm btn-outline-primary btn-ver-receta" data-id="<?php echo $receta['id']; ?>"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-sm btn-outline-warning btn-editar-receta" data-id="<?php echo $receta['id']; ?>"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-outline-success btn-producir" data-id="<?php echo $receta['id']; ?>"><i class="fas fa-play"></i> Producir</button>
                            <button class="btn btn-sm btn-outline-danger btn-eliminar-receta" data-id="<?php echo $receta['id']; ?>"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="text-center py-5">
                <i class="fas fa-utensils fa-4x text-muted mb-3"></i>
                <h4>No hay recetas creadas</h4>
                <p class="text-secondary">Crea tu primera receta</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalReceta" onclick="resetearModalReceta()">
                    <i class="fas fa-plus me-2"></i> Crear Receta
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================= -->
<!-- MODAL PARA CREAR/EDITAR RECETA -->
<!-- ============================================= -->
<div class="modal fade" id="modalReceta" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><span id="modalRecetaTitulo">Nueva Receta</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formReceta">
                <div class="modal-body">
                    <input type="hidden" id="receta_id" name="id">
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold text-danger">Nombre de la Receta *</label>
                            <input type="text" class="form-control" id="receta_nombre" name="nombre" required>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label fw-semibold">Rendimiento</label>
                            <input type="number" class="form-control" id="receta_rendimiento" value="1" min="1">
                            <small class="text-muted">Cantidad de raciones</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold">Unidad de Medida</label>
                            <select class="form-select" id="receta_unidad">
                                <option value="racion">Ración</option>
                                <option value="porcion">Porción</option>
                                <option value="unidad">Unidad</option>
                                <option value="kg">Kg</option>
                                <option value="lb">Lb</option>
                                <option value="g">g</option>
                                <option value="ml">ml</option>
                                <option value="litro">Litro</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold">Tiempo (minutos)</label>
                            <input type="number" class="form-control" id="receta_tiempo" value="0" min="0">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Producto Final</label>
                            <div class="input-group">
                                <select class="form-select" id="receta_producto_id">
                                    <option value="">Seleccionar producto...</option>
                                    <?php 
                                    $productos_finales->data_seek(0);
                                    while($prod = $productos_finales->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $prod['id']; ?>"><?php echo htmlspecialchars($prod['nombre']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <button class="btn btn-outline-success" type="button" onclick="abrirNuevoProductoReceta()"><i class="fas fa-plus"></i></button>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Descripción</label>
                            <input type="text" class="form-control" id="receta_descripcion">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Instrucciones</label>
                        <textarea class="form-control" id="receta_instrucciones" rows="3"></textarea>
                    </div>

                    <hr>
                    
                    <!-- ============================================= -->
                    <!-- INGREDIENTES -->
                    <!-- ============================================= -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">
                            <i class="fas fa-blender me-2"></i> Ingredientes
                            <span class="badge bg-secondary" id="contador_ingredientes">0</span>
                        </h6>
                        <div>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="agregarIngrediente()">
                                <i class="fas fa-plus me-1"></i> Agregar Ingrediente
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="abrirNuevoIngrediente()">
                                <i class="fas fa-box me-1"></i> Nuevo Ingrediente
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th style="min-width:180px;">Ingrediente</th>
                                    <th style="width:100px;">Cantidad</th>
                                    <th style="width:100px;">Unidad</th>
                                    <th style="width:100px;">Stock</th>
                                    <th style="width:120px;">Costo Unit.</th>
                                    <th style="width:120px;">Costo Total</th>
                                    <th style="width:80px;">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="detallesIngredientes">
                                <tr><td colspan="7" class="text-center text-secondary py-3">
                                    <i class="fas fa-plus-circle me-2"></i> Agrega ingredientes
                                </td></tr>
                            </tbody>
                            <tfoot>
                                <tr class="table-success">
                                    <td colspan="5" class="text-end fw-bold">Costo Total:</td>
                                    <td class="text-end fw-bold text-success h5" id="costo_total_receta">$0.00</td>
                                    <td></td>
                                </tr>
                                <tr class="table-success">
                                    <td colspan="5" class="text-end fw-bold">Costo por Ración:</td>
                                    <td class="text-end fw-bold text-success" id="costo_por_racion">$0.00</td>
                                    <td></td>
                                </tr>
                                <tr class="table-info">
                                    <td colspan="5" class="text-end fw-bold">Precio Sugerido Total:</td>
                                    <td class="text-end fw-bold text-primary h5" id="precio_sugerido_receta">$0.00</td>
                                    <td></td>
                                </tr>
                                <tr class="table-info">
                                    <td colspan="5" class="text-end fw-bold">Precio por Ración:</td>
                                    <td class="text-end fw-bold text-primary" id="precio_por_racion">$0.00</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="text-end fw-bold">Margen de Ganancia:</td>
                                    <td class="text-end fw-bold" id="margen_ganancia_receta">0%</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Margen de Ganancia (%)</label>
                            <input type="number" step="0.01" class="form-control" id="receta_margen" value="50" min="0" max="100">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Precio Sugerido</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" class="form-control" id="receta_precio_sugerido" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Costo Total</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="text" class="form-control" id="receta_costo_total" readonly disabled style="background:#f8f9fa;font-weight:bold;color:#dc3545;">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i> Guardar Receta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- MODALES ADICIONALES -->
<!-- ============================================= -->
<!-- Modal Nuevo Ingrediente -->
<div class="modal fade" id="modalNuevoIngrediente" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-box me-2"></i> Nuevo Ingrediente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold text-danger">Nombre *</label>
                    <input type="text" class="form-control" id="nuevo_ingrediente_nombre">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Precio de Compra</label>
                        <input type="number" step="0.01" class="form-control" id="nuevo_ingrediente_precio" value="0.00">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Stock Inicial</label>
                        <input type="number" class="form-control" id="nuevo_ingrediente_stock" value="0">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Unidad Base</label>
                    <select class="form-select" id="nuevo_ingrediente_unidad">
                        <option value="g">Gramos (g)</option>
                        <option value="kg">Kilogramos (kg)</option>
                        <option value="lb">Libras (lb)</option>
                        <option value="ml">Mililitros (ml)</option>
                        <option value="litro">Litros</option>
                        <option value="unidad">Unidad</option>
                    </select>
                    <small class="text-muted">Unidad para recetas</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Categoría</label>
                    <select class="form-select" id="nuevo_ingrediente_categoria">
                        <option value="">Sin categoría</option>
                        <?php 
                        $categorias->data_seek(0);
                        while($cat = $categorias->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <input type="hidden" id="nuevo_ingrediente_tipo" value="simple">
                <input type="hidden" id="nuevo_ingrediente_es_ingrediente" value="1">
                <input type="hidden" id="nuevo_ingrediente_es_final" value="0">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarNuevoIngrediente()">
                    <i class="fas fa-save me-2"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nuevo Producto Final -->
<div class="modal fade" id="modalNuevoProductoReceta" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-box me-2"></i> Nuevo Producto Final</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold text-danger">Nombre *</label>
                    <input type="text" class="form-control" id="nuevo_producto_receta_nombre">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Categoría</label>
                    <select class="form-select" id="nuevo_producto_receta_categoria">
                        <option value="">Sin categoría</option>
                        <?php 
                        $categorias->data_seek(0);
                        while($cat = $categorias->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <input type="hidden" id="nuevo_producto_receta_tipo" value="compuesto">
                <input type="hidden" id="nuevo_producto_receta_es_ingrediente" value="0">
                <input type="hidden" id="nuevo_producto_receta_es_final" value="1">
                <input type="hidden" id="nuevo_producto_receta_tiene_receta" value="1">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarNuevoProductoReceta()">
                    <i class="fas fa-save me-2"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ver Receta -->
<div class="modal fade" id="modalVerReceta" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-utensils me-2"></i> Detalle de Receta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleReceta">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div><p>Cargando...</p></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Producir -->
<div class="modal fade" id="modalProducir" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-play me-2"></i> Producir Receta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="cuerpoProducir">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnConfirmarProduccion">
                    <i class="fas fa-check me-2"></i> Confirmar Producción
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$page_scripts = '<script src="../assets/js/recetas.js"></script>';
include '../layout.php';
?>