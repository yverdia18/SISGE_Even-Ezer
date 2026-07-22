<?php
// pages/papelera.php - MÓDULO DE PAPELERA (SOLO ADMIN)
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar autenticación y rol
if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if($_SESSION['user_rol'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

$page = 'papelera';
$page_title = 'Papelera';

$db = Database::getInstance()->getConnection();

// Obtener elementos de la papelera
$elementos = $db->query("
    SELECT p.*, u.nombre as eliminado_por_nombre
    FROM papelera p
    LEFT JOIN usuarios u ON p.eliminado_por = u.id
    WHERE p.restaurado = 0
    ORDER BY p.fecha_eliminacion DESC
    LIMIT 100
");

// Estadísticas por tabla
$stats = $db->query("
    SELECT tabla_origen, COUNT(*) as total
    FROM papelera
    WHERE restaurado = 0
    GROUP BY tabla_origen
");

ob_start();
?>

<style>
    .papelera-card {
        transition: all 0.3s ease;
        border-left: 4px solid #dc3545;
    }
    .papelera-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    .badge-tabla {
        font-size: 0.75rem;
        padding: 4px 10px;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 fw-bold mb-1">
            <i class="fas fa-trash-alt text-danger me-2"></i> Papelera
        </h1>
        <p class="text-secondary">Elementos eliminados recientemente (solo administradores)</p>
    </div>
    <div>
        <button class="btn btn-danger" onclick="vaciarPapelera()">
            <i class="fas fa-broom me-2"></i> Vaciar Papelera
        </button>
    </div>
</div>

<!-- Estadísticas -->
<div class="row g-3 mb-4">
    <?php 
    $colores = ['danger', 'warning', 'info', 'secondary', 'primary'];
    $i = 0;
    while($stat = $stats->fetch_assoc()): 
        $color = $colores[$i % count($colores)];
        $iconos = [
            'productos' => 'fa-box',
            'clientes' => 'fa-users',
            'recetas' => 'fa-utensils',
            'usuarios' => 'fa-user',
            'compras' => 'fa-shopping-cart',
            'ventas' => 'fa-money-bill'
        ];
        $icono = $iconos[$stat['tabla_origen']] ?? 'fa-file';
    ?>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="fas <?php echo $icono; ?> fa-2x text-<?php echo $color; ?> mb-2"></i>
                <h6 class="fw-bold"><?php echo ucfirst($stat['tabla_origen']); ?></h6>
                <h4 class="text-<?php echo $color; ?>"><?php echo $stat['total']; ?></h4>
            </div>
        </div>
    </div>
    <?php $i++; endwhile; ?>
</div>

<!-- Tabla de elementos -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0">📋 Elementos eliminados</h6>
        <span class="badge bg-secondary"><?php echo $elementos->num_rows; ?> elementos</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="tablaPapelera">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tabla</th>
                        <th>Datos</th>
                        <th>Eliminado por</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($elementos->num_rows > 0): ?>
                        <?php while($item = $elementos->fetch_assoc()): 
                            $datos = json_decode($item['datos'], true);
                        ?>
                        <tr>
                            <td><?php echo $item['id']; ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $item['tabla_origen'] === 'productos' ? 'primary' : 
                                         ($item['tabla_origen'] === 'clientes' ? 'success' : 
                                         ($item['tabla_origen'] === 'recetas' ? 'warning' : 
                                         ($item['tabla_origen'] === 'usuarios' ? 'danger' : 'secondary')));
                                ?> badge-tabla">
                                    <?php echo ucfirst($item['tabla_origen']); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-secondary btn-ver-datos" 
                                        data-datos='<?php echo htmlspecialchars($item['datos']); ?>'>
                                    <i class="fas fa-eye"></i> Ver
                                </button>
                            </td>
                            <td><?php echo htmlspecialchars($item['eliminado_por_nombre'] ?? 'Sistema'); ?></td>
                            <td><?php echo formatDate($item['fecha_eliminacion']); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-success btn-restaurar" data-id="<?php echo $item['id']; ?>" title="Restaurar">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-eliminar-definitivo" data-id="<?php echo $item['id']; ?>" title="Eliminar definitivamente">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-secondary py-4">
                                <i class="fas fa-trash-alt fa-3x mb-3 d-block"></i>
                                La papelera está vacía
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para ver datos -->
<div class="modal fade" id="modalVerDatos" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i> Datos del elemento
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="datosContenido">
                <pre id="datosJson" style="background: #f8f9fa; padding: 15px; border-radius: 8px; white-space: pre-wrap; word-break: break-all;"></pre>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$page_scripts = '
<script src="../assets/js/papelera.js"></script>
';
include '../layout.php';
?>