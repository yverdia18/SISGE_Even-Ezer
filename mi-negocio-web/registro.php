<?php
// registro.php
require_once 'includes/config.php';
require_once 'includes/db.php';

// Si ya está logueado, redirigir
if(isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    if(empty($nombre) || empty($email) || empty($password)) {
        $error = 'Todos los campos son obligatorios';
    } elseif($password !== $password_confirm) {
        $error = 'Las contraseñas no coinciden';
    } elseif(strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } else {
        $db = Database::getInstance()->getConnection();
        
        // Verificar si el email ya existe
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if($stmt->get_result()->num_rows > 0) {
            $error = 'Este correo ya está registrado';
        } else {
            // Crear usuario
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, 'usuario')");
            $stmt->bind_param("sss", $nombre, $email, $password_hash);
            
            if($stmt->execute()) {
                $success = '¡Registro exitoso! Ahora puedes iniciar sesión.';
            } else {
                $error = 'Error al registrar: ' . $db->error;
            }
        }
    }
}

$page_title = 'Registro';
ob_start();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Registro</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* Usamos los mismos estilos que el login */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .login-container {
            width: 100%;
            max-width: 440px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-logo {
            background: linear-gradient(135deg, #10b981, #059669);
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.2rem;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
        }
        
        .login-logo i {
            font-size: 2rem;
            color: white;
        }
        
        .login-header h4 {
            font-weight: 700;
            color: #0f172a;
        }
        
        .login-header p {
            color: #64748b;
            font-size: 0.95rem;
        }
        
        .form-control {
            border-radius: 12px;
            padding: 0.8rem 1rem;
            border: 2px solid #f1f5f9;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #f8fafc;
        }
        
        .form-control:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
            background: white;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 0.9rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(16, 185, 129, 0.4);
            background: linear-gradient(135deg, #059669, #047857);
        }
        
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #64748b;
        }
        
        .login-link a {
            color: #10b981;
            font-weight: 600;
            text-decoration: none;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
        }
        
        .bg-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }
        
        .bg-shapes span {
            position: absolute;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.05);
            animation: float 20s infinite ease-in-out;
        }
        
        .bg-shapes span:nth-child(1) {
            width: 300px;
            height: 300px;
            top: -100px;
            right: -100px;
            animation-delay: 0s;
        }
        
        .bg-shapes span:nth-child(2) {
            width: 200px;
            height: 200px;
            bottom: -50px;
            left: -50px;
            animation-delay: -5s;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -30px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
        }
    </style>
</head>
<body>

<div class="bg-shapes">
    <span></span>
    <span></span>
</div>

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <div class="login-logo">
                <i class="fas fa-user-plus"></i>
            </div>
            <h4>Crear Cuenta</h4>
            <p>Regístrate para empezar a gestionar tu negocio</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger d-flex align-items-center">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert alert-success d-flex align-items-center">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label fw-semibold text-secondary">Nombre</label>
                <div class="form-control-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" class="form-control" name="nombre" placeholder="Tu nombre" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-semibold text-secondary">Correo Electrónico</label>
                <div class="form-control-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email" class="form-control" name="email" placeholder="tu@email.com" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-semibold text-secondary">Contraseña</label>
                <div class="form-control-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control" name="password" placeholder="Mínimo 6 caracteres" required>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="form-label fw-semibold text-secondary">Confirmar Contraseña</label>
                <div class="form-control-icon">
                    <i class="fas fa-check-circle"></i>
                    <input type="password" class="form-control" name="password_confirm" placeholder="Repite tu contraseña" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-login">
                <i class="fas fa-user-plus me-2"></i> Registrarse
            </button>
        </form>
        
        <div class="login-link">
            ¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a>
        </div>
        
        <div class="text-center mt-3">
            <a href="index.php" class="text-decoration-none text-secondary" style="font-size: 0.9rem;">
                <i class="fas fa-arrow-left me-1"></i> Volver al inicio
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>