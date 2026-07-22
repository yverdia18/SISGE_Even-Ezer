<?php
// pages/categorias.php - Gestión de categorías (opcional)
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$page = 'categorias';
$page_title = 'Categorías';

$db = Database::getInstance()->getConnection();

$categorias = $db->query("
    SELECT c.*, COUNT(p.id) as total_productos
    FROM categorias c
    LEFT JOIN productos p ON p.categoria_id = c.id
    GROUP BY c.id
    ORDER BY c.nombre ASC
");

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 fw-bold mb-1">🏷️ Categorías</h1>
        <p class="text-secondary">Gestiona las categorías de tus productos</p>
    </div>
    <button class="btn btn-primary" onclick="abrirNuevaCategoria()">
        <i class="fas fa-plus me-2"></i> Nueva Categoría
    </button>
</div>

<div class="row g-4">
    <?php while($cat = $categorias->fetch_assoc()): ?>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($cat['nombre']); ?></h5>
                        <p class="text-secondary small">
                            <?php echo $cat['total_productos'] ?? 0; ?> productos
                        </p>
                        <?php if($cat['descripcion']): ?>
                            <p class="text-secondary small"><?php echo htmlspecialchars($cat['descripcion']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<!-- Modal para nueva categoría (mismo que en productos.php) -->
<div class="modal fade" id="modalNuevaCategoria" tabindex="-1">
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

<?php
$content = ob_get_clean();
$page_scripts = '
<script>
function abrirNuevaCategoria() {
    $("#nueva_categoria_nombre").val("");
    $("#nueva_categoria_descripcion").val("");
    $("#modalNuevaCategoria").modal("show");
}

function guardarNuevaCategoria() {
    let nombre = $("#nueva_categoria_nombre").val().trim();
    if(nombre === "") {
        alert("El nombre es obligatorio");
        return;
    }
    
    $.ajax({
        url: "../api/categorias.php",
        method: "POST",
        data: JSON.stringify({
            nombre: nombre,
            descripcion: $("#nueva_categoria_descripcion").val().trim()
        }),
        contentType: "application/json",
        success: function(response) {
            if(response.success) {
                alert("✅ Categoría creada");
                location.reload();
            } else {
                alert("Error: " + response.message);
            }
        }
    });
}
</script>
';
include '../layout.php';
?>