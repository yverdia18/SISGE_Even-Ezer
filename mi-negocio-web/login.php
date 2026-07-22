<?php
// login.php
require_once 'includes/config.php';
require_once 'includes/db.php';

// Si ya está logueado, redirigir al dashboard
if(isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Procesar login
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if(empty($email) || empty($password)) {
        $error = 'Por favor, complete todos los campos';
    } else {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, nombre, email, password_hash, rol FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($user = $result->fetch_assoc()) {
            if(password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nombre'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_rol'] = $user['rol'];
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Contraseña incorrecta';
            }
        } else {
            $error = 'Usuario no encontrado';
        }
    }
}

$page_title = 'Iniciar Sesión';
ob_start();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Login</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
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
            max-width: 420px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }
        
        .login-card:hover {
            box-shadow: 0 30px 80px rgba(0,0,0,0.12);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-logo {
            display: inline-block;
            background: linear-gradient(135deg, #10b981, #059669);
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.2rem;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
        }
        
        .login-logo i {
            font-size: 2rem;
            color: white;
        }
        
        .login-header h4 {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.3rem;
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
        
        .form-control-icon {
            position: relative;
        }
        
        .form-control-icon i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        
        .form-control-icon .form-control {
            padding-left: 2.8rem;
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
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #64748b;
        }
        
        .register-link a {
            color: #10b981;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .register-link a:hover {
            color: #059669;
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.08);
            color: #dc2626;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.08);
            color: #059669;
        }
        
        /* Animación de fondo */
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
        
        .bg-shapes span:nth-child(3) {
            width: 150px;
            height: 150px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation-delay: -10s;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translate(0, 0) scale(1);
            }
            33% {
                transform: translate(30px, -30px) scale(1.1);
            }
            66% {
                transform: translate(-20px, 20px) scale(0.9);
            }
        }
        
        @media (max-width: 480px) {
            .login-card {
                padding: 1.8rem;
            }
        }
    </style>
</head>
<body>

<!-- Fondo animado -->
<div class="bg-shapes">
    <span></span>
    <span></span>
    <span></span>
</div>

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <div class="login-logo mx-auto">
                <i class="fas fa-store"></i>
            </div>
            <h4>Bienvenido</h4>
            <p>Inicia sesión para gestionar tu negocio</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert alert-success d-flex align-items-center" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label fw-semibold text-secondary">Correo Electrónico</label>
                <div class="form-control-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email" 
                           class="form-control" 
                           name="email" 
                           placeholder="tu@email.com" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           required>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="form-label fw-semibold text-secondary">Contraseña</label>
                <div class="form-control-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" 
                           class="form-control" 
                           name="password" 
                           placeholder="••••••••"
                           required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-login">
                <i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión
            </button>
        </form>
        
        <div class="register-link">
            ¿No tienes cuenta? <a href="registro.php">Regístrate aquí</a>
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