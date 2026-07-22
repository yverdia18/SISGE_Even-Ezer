<?php
// pages/usuarios.php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar autenticación y rol de administrador
if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if($_SESSION['user_rol'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

$page = 'usuarios';
$page_title = 'Gestión de Usuarios';

$db = Database::getInstance()->getConnection();

// Obtener usuarios
$usuarios = $db->query("
    SELECT id, nombre, email, rol, activo, bloqueado, 
           DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as fecha_registro,
           DATE_FORMAT(ultimo_acceso, '%d/%m/%Y %H:%i') as ultimo_acceso
    FROM usuarios 
    ORDER BY id DESC
");

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 fw-bold mb-1">👥 Gestión de Usuarios</h1>
        <p class="text-secondary">Administra los usuarios del sistema</p>
    </div>
    <?php if($_SESSION['user_rol'] === 'admin'): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUsuario">
        <i class="fas fa-user-plus me-2"></i> Nuevo Usuario
    </button>
    <?php endif; ?>
</div>

<!-- Filtros y búsqueda -->
<div class="row mb-4">
    <div class="col-md-5">
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" id="buscarUsuario" class="form-control" placeholder="Buscar por nombre o email...">
        </div>
    </div>
    <div class="col-md-3">
        <select id="filtroRol" class="form-select">
            <option value="">Todos los roles</option>
            <option value="admin">Administrador</option>
            <option value="usuario">Usuario</option>
        </select>
    </div>
    <div class="col-md-2">
        <select id="filtroEstado" class="form-select">
            <option value="">Todos los estados</option>
            <option value="1">Activos</option>
            <option value="0">Inactivos</option>
        </select>
    </div>
</div>

<!-- Tarjetas de usuarios -->
<div class="row g-4" id="listaUsuarios">
    <?php while($usuario = $usuarios->fetch_assoc()): ?>
    <div class="col-lg-4 col-md-6 usuario-item" 
         data-rol="<?php echo $usuario['rol']; ?>" 
         data-activo="<?php echo $usuario['activo']; ?>"
         data-bloqueado="<?php echo $usuario['bloqueado']; ?>">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 rounded-3 p-3 me-3">
                            <i class="fas fa-user fa-2x text-primary"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($usuario['nombre']); ?></h5>
                            <p class="text-secondary mb-0 small"><?php echo htmlspecialchars($usuario['email']); ?></p>
                        </div>
                    </div>
                    <div>
                        <?php if($usuario['rol'] === 'admin'): ?>
                            <span class="badge bg-danger">Admin</span>
                        <?php else: ?>
                            <span class="badge bg-info">Usuario</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <hr>
                
                <div class="row small">
                    <div class="col-6">
                        <span class="text-secondary">Estado:</span><br>
                        <?php if($usuario['bloqueado']): ?>
                            <span class="badge bg-danger">🔒 Bloqueado</span>
                        <?php elseif($usuario['activo']): ?>
                            <span class="badge bg-success">✅ Activo</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">⛔ Inactivo</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-6">
                        <span class="text-secondary">Registro:</span><br>
                        <small><?php echo $usuario['fecha_registro']; ?></small>
                    </div>
                </div>
                
                <?php if($usuario['ultimo_acceso']): ?>
                <div class="mt-2 small text-secondary">
                    <i class="fas fa-clock me-1"></i> Último acceso: <?php echo $usuario['ultimo_acceso']; ?>
                </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <div class="btn-group w-100">
                        <button class="btn btn-outline-primary btn-sm btn-editar" data-id="<?php echo $usuario['id']; ?>">
                            <i class="fas fa-edit me-1"></i> Editar
                        </button>
                        <?php if($usuario['id'] != $_SESSION['user_id']): ?>
                            <button class="btn btn-outline-danger btn-sm btn-eliminar" data-id="<?php echo $usuario['id']; ?>">
                                <i class="fas fa-trash me-1"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<!-- Modal para crear/editar usuario -->
<div class="modal fade" id="modalUsuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formUsuario" method="POST" action="../api/usuarios.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">
                        <i class="fas fa-user-plus me-2"></i> Nuevo Usuario
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="usuario_id" name="id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre *</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Nombre completo" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Correo Electrónico *</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="usuario@email.com" required>
                    </div>
                    
                    <div class="mb-3" id="campo_password">
                        <label class="form-label fw-semibold">Contraseña</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" placeholder="Mínimo 6 caracteres">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                        <small class="text-muted">Dejar en blanco para mantener la contraseña actual</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rol</label>
                        <select class="form-select" id="rol" name="rol">
                            <option value="usuario">Usuario</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" checked>
                            <label class="form-check-label fw-semibold" for="activo">Usuario Activo</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Guardar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para resetear contraseña -->
<div class="modal fade" id="modalResetPassword" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-key me-2"></i> Resetear Contraseña
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="reset_usuario_id">
                <p>¿Estás seguro de que deseas resetear la contraseña de <strong id="reset_usuario_nombre"></strong>?</p>
                <p>La nueva contraseña será: <strong><code id="nueva_password"><?php echo substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8); ?></code></strong></p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    El usuario deberá cambiar su contraseña en el próximo inicio de sesión.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="btnResetPassword">
                    <i class="fas fa-key me-2"></i> Resetear Contraseña
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$page_scripts = '<script src="../assets/js/usuarios.js"></script>';
include '../layout.php';
?>