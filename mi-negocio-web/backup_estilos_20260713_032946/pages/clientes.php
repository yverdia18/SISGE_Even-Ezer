<?php
// pages/clientes.php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar autenticación
if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$page = 'clientes';
$page_title = 'Clientes';

$db = Database::getInstance()->getConnection();

// Obtener clientes
$clientes = $db->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM ventas v WHERE v.cliente_id = c.id) as total_compras,
           (SELECT SUM(total) FROM ventas v WHERE v.cliente_id = c.id) as total_gastado
    FROM clientes c
    ORDER BY c.nombre ASC
");

// Obtener áreas de trabajo para el filtro
$areas = $db->query("SELECT DISTINCT area_trabajo FROM clientes WHERE area_trabajo IS NOT NULL AND area_trabajo != '' ORDER BY area_trabajo");

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 fw-bold mb-1">👥 Clientes</h1>
        <p class="text-secondary">Gestiona tu libreta de direcciones de clientes</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCliente">
        <i class="fas fa-user-plus me-2"></i> Nuevo Cliente
    </button>
</div>

<!-- Filtros y búsqueda -->
<div class="row mb-4">
    <div class="col-md-5">
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" id="buscarCliente" class="form-control" placeholder="Buscar por nombre, teléfono o email...">
        </div>
    </div>
    <div class="col-md-3">
        <select id="filtroArea" class="form-select">
            <option value="">Todas las áreas</option>
            <?php while($area = $areas->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($area['area_trabajo']); ?>">
                    <?php echo htmlspecialchars($area['area_trabajo']); ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="col-md-2">
        <button class="btn btn-success w-100" onclick="exportarClientes()">
            <i class="fas fa-file-export me-2"></i> Exportar
        </button>
    </div>
</div>

<!-- Tabla de clientes -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="tablaClientes">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Área</th>
                        <th>Compras</th>
                        <th>Total Gastado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($cliente = $clientes->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $cliente['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($cliente['nombre'] . ' ' . ($cliente['apellidos'] ?? '')); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($cliente['telefono'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($cliente['email'] ?? '-'); ?></td>
                        <td>
                            <?php if($cliente['area_trabajo']): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($cliente['area_trabajo']); ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $cliente['total_compras'] ?? 0; ?></td>
                        <td>
                            <?php if($cliente['total_gastado'] > 0): ?>
                                <span class="text-success fw-bold">
                                    $<?php echo number_format($cliente['total_gastado'], 2); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary btn-ver" data-id="<?php echo $cliente['id']; ?>" title="Ver detalle">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-success btn-editar" data-id="<?php echo $cliente['id']; ?>" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-eliminar" data-id="<?php echo $cliente['id']; ?>" title="Eliminar">
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

<!-- Modal para crear/editar cliente -->
<div class="modal fade" id="modalCliente" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formCliente" method="POST" action="../api/clientes.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">
                        <i class="fas fa-user-plus me-2"></i> Nuevo Cliente
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="cliente_id" name="id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Nombre *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Nombre" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Apellidos</label>
                            <input type="text" class="form-control" id="apellidos" name="apellidos" placeholder="Apellidos">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Teléfono</label>
                            <input type="text" class="form-control" id="telefono" name="telefono" placeholder="55 1234 5678">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Teléfono 2</label>
                            <input type="text" class="form-control" id="telefono2" name="telefono2" placeholder="55 8765 4321">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="cliente@email.com">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Banco</label>
                            <input type="text" class="form-control" id="banco" name="banco" placeholder="BBVA, Santander, etc.">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Cuenta Bancaria</label>
                            <input type="text" class="form-control" id="cuenta_bancaria" name="cuenta_bancaria" placeholder="Número de cuenta">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Área de Trabajo</label>
                            <input type="text" class="form-control" id="area_trabajo" name="area_trabajo" placeholder="Ventas, Contabilidad, etc.">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Puesto</label>
                            <input type="text" class="form-control" id="puesto" name="puesto" placeholder="Gerente, Supervisor, etc.">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Dirección</label>
                        <input type="text" class="form-control" id="direccion" name="direccion" placeholder="Calle y número">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Ciudad</label>
                            <input type="text" class="form-control" id="ciudad" name="ciudad" placeholder="Ciudad">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Estado</label>
                            <input type="text" class="form-control" id="estado" name="estado" placeholder="Estado">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label fw-semibold">CP</label>
                            <input type="text" class="form-control" id="codigo_postal" name="codigo_postal" placeholder="CP">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Dirección 2</label>
                        <input type="text" class="form-control" id="direccion2" name="direccion2" placeholder="Dirección alternativa">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Redes Sociales</label>
                        <input type="text" class="form-control" id="redes_sociales" name="redes_sociales" placeholder="Facebook, LinkedIn, Instagram, etc.">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notas</label>
                        <textarea class="form-control" id="notas" name="notas" rows="3" placeholder="Información adicional..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Guardar Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para ver detalle del cliente -->
<div class="modal fade" id="modalVerCliente" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user me-2"></i> Detalle del Cliente
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleCliente">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2 text-secondary">Cargando información del cliente...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$page_scripts = '<script src="../assets/js/clientes.js"></script>';
include '../layout.php';
?>