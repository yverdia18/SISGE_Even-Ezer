<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$page = 'recetas';
$page_title = 'Recetas';

$db = Database::getInstance()->getConnection();

// Obtener recetas
$recetas = $db->query("
    SELECT r.*, p.nombre as producto_nombre 
    FROM recetas r
    LEFT JOIN productos p ON r.producto_id = p.id
    WHERE r.eliminado = 0 AND r.activo = 1
    ORDER BY r.nombre ASC
");

// Obtener productos compuestos para el select
$productos_compuestos = $db->query("
    SELECT id, nombre, unidad_base 
    FROM productos 
    WHERE tipo_producto = 'compuesto' OR tiene_receta = 1
    ORDER BY nombre ASC
");

// Obtener ingredientes disponibles
$ingredientes_disponibles = $db->query("
    SELECT id, nombre, unidad_base, precio_compra, contenido_unidad 
    FROM productos 
    WHERE tipo_producto = 'simple' OR tipo_producto IS NULL OR tipo_producto = ''
    ORDER BY nombre ASC
");

ob_start();
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h1>📋 Recetas</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalReceta" onclick="resetFormReceta()">
            <i class="fas fa-plus me-2"></i> Nueva Receta
        </button>
    </div>
</div>

<!-- Tarjetas de estadísticas -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Total Recetas</h6>
                        <h2 class="mb-0"><?php echo $recetas->num_rows; ?></h2>
                    </div>
                    <i class="fas fa-book fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Activas</h6>
                        <h2 class="mb-0"><?php echo $recetas->num_rows; ?></h2>
                    </div>
                    <i class="fas fa-check-circle fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Con Producto Final</h6>
                        <?php
                        $con_producto = $db->query("SELECT COUNT(*) as total FROM recetas WHERE producto_id IS NOT NULL AND producto_id > 0 AND eliminado = 0");
                        $total_con_producto = $con_producto->fetch_assoc()['total'];
                        ?>
                        <h2 class="mb-0"><?php echo $total_con_producto; ?></h2>
                    </div>
                    <i class="fas fa-box fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Costo Promedio</h6>
                        <?php
                        $costo_promedio = $db->query("SELECT AVG(costo_total_produccion) as promedio FROM recetas WHERE eliminado = 0 AND costo_total_produccion > 0");
                        $promedio = $costo_promedio->fetch_assoc()['promedio'] ?? 0;
                        ?>
                        <h2 class="mb-0">$<?php echo number_format($promedio, 2); ?></h2>
                    </div>
                    <i class="fas fa-dollar-sign fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- TARJETAS DE RECETAS CON BOTONES VISIBLES -->
<!-- ============================================================ -->
<div class="row">
    <?php if($recetas->num_rows > 0): ?>
        <?php while($receta = $recetas->fetch_assoc()): 
            // Verificar stock de ingredientes
            $ingredientes_receta = $db->query("
                SELECT ri.*, p.nombre, p.stock, p.unidad_base
                FROM receta_ingredientes ri
                LEFT JOIN productos p ON ri.ingrediente_id = p.id
                WHERE ri.receta_id = " . $receta['id']
            );
            
            $stock_suficiente = true;
            $faltantes = [];
            while($ing = $ingredientes_receta->fetch_assoc()) {
                $necesario = $ing['cantidad'];
                $disponible = $ing['stock'] ?? 0;
                if($disponible < $necesario) {
                    $stock_suficiente = false;
                    $faltantes[] = $ing['nombre'] . " (necesita: $necesario, tiene: $disponible)";
                }
            }
        ?>
        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
            <div class="card h-100 shadow-sm hover-card <?php echo !$stock_suficiente ? 'border-warning' : ''; ?>">
                <div class="card-header bg-white border-0 pt-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="card-title mb-0">
                                <?php echo htmlspecialchars($receta['nombre']); ?>
                            </h5>
                            <?php if(!$stock_suficiente): ?>
                                <span class="badge bg-warning text-dark mt-1">
                                    <i class="fas fa-exclamation-triangle me-1"></i> Stock insuficiente
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-outline-primary btn-editar" data-id="<?php echo $receta['id']; ?>" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-outline-success btn-producir" 
                                    data-id="<?php echo $receta['id']; ?>" 
                                    data-nombre="<?php echo htmlspecialchars($receta['nombre']); ?>"
                                    data-rendimiento="<?php echo $receta['rendimiento'] ?? 1; ?>"
                                    data-producto="<?php echo htmlspecialchars($receta['producto_nombre'] ?? 'Sin producto'); ?>" 
                                    title="Producir">
                                <i class="fas fa-play"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-eliminar" data-id="<?php echo $receta['id']; ?>" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if(!empty($receta['descripcion'])): ?>
                        <p class="card-text text-muted small">
                            <?php echo htmlspecialchars(substr($receta['descripcion'], 0, 60)); ?>
                            <?php if(strlen($receta['descripcion']) > 60): ?>...<?php endif; ?>
                        </p>
                    <?php endif; ?>

                    <div class="mb-2">
                        <span class="badge bg-info me-1">
                            <i class="fas fa-box me-1"></i> 
                            <?php echo $receta['producto_id'] ? htmlspecialchars($receta['producto_nombre'] ?? 'N/A') : 'Sin asignar'; ?>
                        </span>
                        <span class="badge bg-secondary">
                            <i class="fas fa-arrow-right me-1"></i> 
                            <?php echo $receta['rendimiento'] ?? 1; ?> <?php echo $receta['unidad_medida'] ?? 'und'; ?>
                        </span>
                    </div>

                    <div class="row mt-3">
                        <div class="col-6">
                            <small class="text-muted d-block">Costo Unitario</small>
                            <strong class="text-success">
                                $<?php echo number_format($receta['costo_unitario_produccion'] ?? 0, 2); ?>
                            </strong>
                        </div>
                        <div class="col-6 text-end">
                            <small class="text-muted d-block">Precio Sugerido</small>
                            <strong class="text-warning">
                                <?php if($receta['precio_venta_sugerido'] > 0): ?>
                                    $<?php echo number_format($receta['precio_venta_sugerido'], 2); ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </strong>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white border-0 pb-3">
                    <button class="btn <?php echo $stock_suficiente ? 'btn-success' : 'btn-warning'; ?> btn-sm w-100 btn-producir" 
                            data-id="<?php echo $receta['id']; ?>" 
                            data-nombre="<?php echo htmlspecialchars($receta['nombre']); ?>"
                            data-rendimiento="<?php echo $receta['rendimiento'] ?? 1; ?>"
                            data-producto="<?php echo htmlspecialchars($receta['producto_nombre'] ?? 'Sin producto'); ?>">
                        <i class="fas fa-play me-1"></i> 
                        <?php echo $stock_suficiente ? 'Producir' : 'Verificar Stock'; ?>
                    </button>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-12 text-center py-5">
            <i class="fas fa-book fa-4x text-muted mb-3 d-block"></i>
            <h4 class="text-muted">No hay recetas registradas</h4>
            <p class="text-muted">Crea tu primera receta para empezar</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalReceta" onclick="resetFormReceta()">
                <i class="fas fa-plus me-2"></i> Crear primera receta
            </button>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- MODAL CREAR/EDITAR RECETA -->
<!-- ============================================================ -->
<div class="modal fade" id="modalReceta" tabindex="-1" aria-labelledby="modalTitulo" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form id="formReceta" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">Nueva Receta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="receta_id" name="id">
                    
                    <!-- Datos básicos -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre de la receta <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Rendimiento <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="rendimiento" name="rendimiento" value="1" required>
                            <small class="text-muted">Cantidad de producto final</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Unidad de medida</label>
                            <select class="form-select" id="unidad_medida" name="unidad_medida">
                                <option value="racion">Ración</option>
                                <option value="unidad">Unidad</option>
                                <option value="kg">Kilogramo</option>
                                <option value="lb">Libra</option>
                                <option value="litro">Litro</option>
                                <option value="ml">Mililitro</option>
                                <option value="g">Gramo</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Producto final</label>
                            <select class="form-select" id="producto_id" name="producto_id">
                                <option value="">Seleccionar producto final</option>
                                <?php if($productos_compuestos->num_rows > 0): ?>
                                    <?php while($prod = $productos_compuestos->fetch_assoc()): ?>
                                    <option value="<?php echo $prod['id']; ?>">
                                        <?php echo htmlspecialchars($prod['nombre']); ?> (<?php echo $prod['unidad_base'] ?? 'unidad'; ?>)
                                    </option>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <option value="">No hay productos compuestos</option>
                                <?php endif; ?>
                            </select>
                            <small class="text-muted">Producto que se obtiene al producir esta receta</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Tiempo preparación (min)</label>
                            <input type="number" class="form-control" id="tiempo_preparacion" name="tiempo_preparacion" value="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Margen de ganancia (%)</label>
                            <input type="number" step="0.01" class="form-control" id="margen_ganancia" name="margen_ganancia" value="40">
                            <small class="text-muted">Para calcular precio sugerido</small>
                        </div>
                    </div>

                    <!-- Tarjetas de costos -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="card bg-light border-primary">
                                <div class="card-body">
                                    <h6 class="card-title text-muted">💰 Costo Total</h6>
                                    <h4 class="text-primary" id="costoTotalDisplay">$0.00</h4>
                                    <small>Suma de todos los ingredientes</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light border-success">
                                <div class="card-body">
                                    <h6 class="card-title text-muted">📊 Costo Unitario</h6>
                                    <h4 class="text-success" id="costoUnitarioDisplay">$0.00</h4>
                                    <small>Costo total / Rendimiento</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light border-warning">
                                <div class="card-body">
                                    <h6 class="card-title text-muted">🏷️ Precio Venta Sugerido</h6>
                                    <h4 class="text-warning" id="precioVentaDisplay">$0.00</h4>
                                    <small>Con margen de ganancia</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="2"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Instrucciones de preparación</label>
                        <textarea class="form-control" id="instrucciones" name="instrucciones" rows="3"></textarea>
                    </div>

                    <!-- ============================================================ -->
                    <!-- INGREDIENTES EN TABLA -->
                    <!-- ============================================================ -->
                    <hr>
                    <h6 class="mb-3"><i class="fas fa-list me-2"></i> Ingredientes</h6>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="tablaIngredientes">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 5%;">#</th>
                                    <th style="width: 30%;">Ingrediente</th>
                                    <th style="width: 15%;">Cantidad</th>
                                    <th style="width: 15%;">Unidad</th>
                                    <th style="width: 15%;">Merma (%)</th>
                                    <th style="width: 10%;">Costo</th>
                                    <th style="width: 5%;">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="ingredientesBody">
                                <?php if($ingredientes_disponibles->num_rows > 0): ?>
                                    <!-- Fila template (oculta) -->
                                    <tr id="ingredienteTemplate" style="display: none;">
                                        <td class="text-center">1</td>
                                        <td>
                                            <select class="form-select form-select-sm ingrediente-select" name="ingrediente[]">
                                                <option value="">Seleccionar ingrediente</option>
                                                <?php 
                                                $ingredientes_disponibles->data_seek(0);
                                                while($ing = $ingredientes_disponibles->fetch_assoc()): 
                                                ?>
                                                <option value="<?php echo $ing['id']; ?>" 
                                                        data-precio="<?php echo $ing['precio_compra']; ?>"
                                                        data-contenido="<?php echo $ing['contenido_unidad'] ?? 1; ?>"
                                                        data-unidad-base="<?php echo $ing['unidad_base'] ?? 'g'; ?>">
                                                    <?php echo htmlspecialchars($ing['nombre']); ?>
                                                    ($<?php echo $ing['precio_compra']; ?> / <?php echo $ing['contenido_unidad'] ?? 1; ?> <?php echo $ing['unidad_base'] ?? 'g'; ?>)
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control form-control-sm cantidad-ingrediente" name="cantidad[]" placeholder="0.00" value="">
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm unidad-ingrediente" name="unidad[]">
                                                <option value="g">Gramos (g)</option>
                                                <option value="kg">Kilogramos (kg)</option>
                                                <option value="lb">Libras (lb)</option>
                                                <option value="oz">Onzas (oz)</option>
                                                <option value="ml">Mililitros (ml)</option>
                                                <option value="litro">Litros (L)</option>
                                                <option value="unidad">Unidad</option>
                                                <option value="taza">Taza</option>
                                                <option value="cucharada">Cucharada</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control form-control-sm merma-ingrediente" name="merma[]" placeholder="0" value="0">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm costo-ingrediente" readonly placeholder="$0.00" value="$0.0000">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-danger btn-sm eliminar-ingrediente">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            No hay ingredientes disponibles. Primero crea productos de tipo "simple".
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <button type="button" class="btn btn-outline-primary btn-sm" id="agregarIngrediente" <?php echo $ingredientes_disponibles->num_rows == 0 ? 'disabled' : ''; ?>>
                        <i class="fas fa-plus me-2"></i> Agregar Ingrediente
                    </button>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarReceta">
                        <i class="fas fa-save me-2"></i> Guardar Receta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL PRODUCIR RECETA -->
<!-- ============================================================ -->
<div class="modal fade" id="modalProducir" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🔧 Producir Receta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que deseas producir esta receta?</p>
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 id="producirNombreReceta" class="mb-2">Cargando...</h6>
                        <p class="mb-1"><strong>Rendimiento:</strong> <span id="producirRendimiento">-</span></p>
                        <p class="mb-0"><strong>Producto final:</strong> <span id="producirProducto">-</span></p>
                    </div>
                </div>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    Se descontarán los ingredientes del stock y se sumará el producto final.
                </div>
                <input type="hidden" id="producir_receta_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnConfirmarProduccion">
                    <i class="fas fa-play me-2"></i> Producir
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CSS Adicional -->
<style>
.hover-card {
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.05);
}
.hover-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.12) !important;
    border-color: #0d6efd;
}
.hover-card .card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
}
.hover-card .card-footer {
    background: #f8f9fa;
}
.badge {
    font-weight: 500;
    padding: 6px 10px;
}
</style>

<?php
$content = ob_get_clean();

// ============================================================
// ✅ SOLO SE INCLUYE EL ARCHIVO JS EXTERNO
// ============================================================
$page_scripts = '
<script src="../assets/js/recetas.js"></script>
';
include '../layout.php';
?>