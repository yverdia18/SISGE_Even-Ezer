<?php
// index.php - Página de inicio
require_once 'includes/config.php';

// Si ya está logueado, redirigir al dashboard
if(isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$page_title = 'Inicio';
ob_start();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Inicio</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            overflow-x: hidden;
        }
        
        /* Navbar moderno */
        .navbar-modern {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 1rem 0;
            transition: all 0.3s ease;
        }
        
        .navbar-modern.scrolled {
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .navbar-brand {
            font-weight: 800;
            font-size: 1.5rem;
            color: #0f172a !important;
        }
        
        .navbar-brand span {
            color: #10b981;
        }
        
        .btn-login-nav {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white !important;
            padding: 0.6rem 1.8rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
            text-decoration: none;
        }
        
        .btn-login-nav:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(16, 185, 129, 0.4);
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
        }
        
        /* Hero Section */
        .hero-section {
            min-height: 90vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.08) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
        }
        
        .hero-badge {
            display: inline-block;
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            padding: 0.4rem 1.2rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .hero-title {
            font-size: 4rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
            margin-bottom: 1.5rem;
        }
        
        .hero-title span {
            background: linear-gradient(135deg, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero-subtitle {
            font-size: 1.2rem;
            color: #475569;
            font-weight: 400;
            max-width: 550px;
            margin-bottom: 2rem;
            line-height: 1.7;
        }
        
        .btn-primary-hero {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 0.9rem 2.5rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
        }
        
        .btn-primary-hero:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(16, 185, 129, 0.4);
            color: white;
        }
        
        .btn-outline-hero {
            border: 2px solid #e2e8f0;
            color: #0f172a;
            background: white;
            padding: 0.9rem 2.5rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-outline-hero:hover {
            border-color: #10b981;
            color: #10b981;
            background: transparent;
            transform: translateY(-3px);
        }
        
        /* Features */
        .features-section {
            padding: 5rem 0;
            background: white;
        }
        
        .feature-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            border: 1px solid #f1f5f9;
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.07);
            border-color: #10b981;
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.2rem;
            font-size: 1.8rem;
            color: #10b981;
        }
        
        /* Stats */
        .stats-section {
            padding: 4rem 0;
            background: linear-gradient(135deg, #0f172a, #1e293b);
            color: white;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            color: #10b981;
            display: block;
        }
        
        .stat-label {
            color: #94a3b8;
            font-weight: 500;
        }
        
        /* Footer */
        .footer {
            background: #0f172a;
            color: #94a3b8;
            padding: 3rem 0;
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            .hero-section {
                min-height: auto;
                padding: 6rem 0;
            }
            .hero-section::before {
                display: none;
            }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-modern fixed-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-store me-2" style="color: #10b981;"></i>
            Mi<span>Negocio</span>
        </a>
        
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link" href="#features">Características</a>
                </li>
                <li class="nav-item ms-lg-3">
                    <a href="login.php" class="btn btn-login-nav">
                        <i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center hero-content">
            <div class="col-lg-6">
                <div class="hero-badge">
                    <i class="fas fa-rocket me-2"></i> Transforma tu negocio
                </div>
                
                <h1 class="hero-title">
                    Gestiona tu negocio<br>
                    de forma <span>inteligente</span>
                </h1>
                
                <p class="hero-subtitle">
                    Controla tus productos, ventas y compras en un solo lugar. 
                    Diseñado para emprendedores como tú.
                </p>
                
                <div class="hero-buttons d-flex flex-wrap gap-3">
                    <a href="login.php" class="btn-primary-hero">
                        <i class="fas fa-play me-2"></i> Comenzar Ahora
                    </a>
                    <a href="#features" class="btn-outline-hero">
                        <i class="fas fa-info-circle me-2"></i> Saber Más
                    </a>
                </div>
            </div>
            
            <div class="col-lg-6 d-none d-lg-block">
                <div class="text-center">
                    <div style="background: white; border-radius: 20px; padding: 2rem; box-shadow: 0 20px 60px rgba(0,0,0,0.1);">
                        <i class="fas fa-chart-line" style="font-size: 8rem; color: #10b981;"></i>
                        <h4 class="mt-3">Dashboard Preview</h4>
                        <p class="text-secondary">Tu negocio al alcance de un clic</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section" id="features">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge bg-success bg-opacity-10 text-success px-4 py-2 rounded-pill mb-3">
                Características
            </span>
            <h2 class="display-5 fw-bold">Todo lo que necesitas</h2>
            <p class="text-secondary">Herramientas diseñadas para hacer crecer tu negocio</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <h5>Gestión de Productos</h5>
                    <p>Controla tu inventario, precios y stock en tiempo real.</p>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h5>Ventas Rápidas</h5>
                    <p>Registra ventas de forma ágil y visualiza el historial.</p>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <h5>Control de Compras</h5>
                    <p>Administra tus compras a proveedores y mantén tu stock.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-3 mb-4 mb-md-0">
                <span class="stat-number" id="statProducts">0</span>
                <span class="stat-label">Productos Gestionados</span>
            </div>
            <div class="col-md-3 mb-4 mb-md-0">
                <span class="stat-number" id="statSales">0</span>
                <span class="stat-label">Ventas Realizadas</span>
            </div>
            <div class="col-md-3 mb-4 mb-md-0">
                <span class="stat-number" id="statClients">0</span>
                <span class="stat-label">Clientes Activos</span>
            </div>
            <div class="col-md-3">
                <span class="stat-number" id="statRevenue">$0</span>
                <span class="stat-label">Ingresos Generados</span>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4 mb-md-0">
                <h5 class="text-white fw-bold mb-3">
                    <i class="fas fa-store me-2" style="color: #10b981;"></i>
                    Mi<span style="color: #10b981;">Negocio</span>
                </h5>
                <p>La solución completa para gestionar tu negocio.</p>
            </div>
            
            <div class="col-md-4 mb-4 mb-md-0">
                <h6 class="text-white fw-bold mb-3">Enlaces Rápidos</h6>
                <ul class="list-unstyled">
                    <li><a href="login.php" class="text-decoration-none text-secondary">Iniciar Sesión</a></li>
                    <li><a href="#features" class="text-decoration-none text-secondary">Características</a></li>
                </ul>
            </div>
            
            <div class="col-md-4">
                <h6 class="text-white fw-bold mb-3">Contacto</h6>
                <p class="text-secondary">
                    <i class="fas fa-envelope me-2"></i> info@minegocio.com
                </p>
            </div>
        </div>
        
        <hr class="border-secondary">
        
        <div class="text-center text-secondary">
            <small>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Todos los derechos reservados.</small>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar-modern');
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
    
    // Animación de números
    function animateCounter(element, target, suffix = '') {
        let current = 0;
        const increment = Math.ceil(target / 50);
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            element.textContent = (suffix === '$' ? '$' : '') + current.toLocaleString();
        }, 40);
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        animateCounter(document.getElementById('statProducts'), 156);
        animateCounter(document.getElementById('statSales'), 1247);
        animateCounter(document.getElementById('statClients'), 89);
        animateCounter(document.getElementById('statRevenue'), 45678, '$');
    });
</script>

</body>
</html>