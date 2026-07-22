<!-- layout.php -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - <?php echo $page_title ?? 'Dashboard'; ?></title>
    
    <!-- Bootstrap 5 (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts (opcional) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ============================================= -->
    <!-- CSS UNIFICADO - UN SOLO ARCHIVO -->
    <!-- ============================================= -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    
    <!-- Estilos específicos de la página (si los hay) -->
    <?php echo $page_styles ?? ''; ?>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <!-- SIDEBAR COMPLETO CON ICONOS DELANTE Y DETRÁS -->

        <div class="d-flex" id="wrapper">
            <!-- Sidebar -->
            <div class="bg-dark text-white" id="sidebar-wrapper" style="min-width: 250px; min-height: 100vh; transition: all 0.3s ease;">
                <div class="sidebar-heading text-center py-4 border-bottom">
                    <h4><?php echo SITE_NAME; ?></h4>
                </div>
                <div class="list-group list-group-flush">
                    
                    <!-- ============================================= -->
                    <!-- DASHBOARD -->
                    <!-- ============================================= -->
                    <a href="<?php echo SITE_URL; ?>dashboard.php" 
                    class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo ($page == 'dashboard') ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line me-2"></i> Dashboard
                        <span class="badge bg-primary float-end">
                            <i class="fas fa-home"></i>
                        </span>
                    </a>
                    
                    <!-- ============================================= -->
                    <!-- PRODUCTOS -->
                    <!-- ============================================= -->
                    <a href="<?php echo SITE_URL; ?>pages/productos.php" 
                    class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo ($page == 'productos') ? 'active' : ''; ?>">
                        <i class="fas fa-box me-2"></i> Productos
                        <span class="badge bg-primary float-end">
                            <i class="fas fa-cube"></i>
                        </span>
                    </a>
                    
                    <!-- ============================================= -->
                    <!-- CATEGORÍAS -->
                    <!-- ============================================= -->
                    <a href="<?php echo SITE_URL; ?>pages/categorias.php" 
                    class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo ($page == 'categorias') ? 'active' : ''; ?>">
                        <i class="fas fa-tags me-2"></i> Categorías
                        <span class="badge bg-info float-end">
                            <i class="fas fa-tag"></i>
                        </span>
                    </a>
                    
                    <!-- ============================================= -->
                    <!-- VENTAS -->
                    <!-- ============================================= -->
                    <a href="<?php echo SITE_URL; ?>pages/ventas.php" 
                    class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo ($page == 'ventas') ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-cart me-2"></i> Ventas
                        <span class="badge bg-success float-end">
                            <i class="fas fa-money-bill-wave"></i>
                        </span>
                    </a>
                    
                    <!-- ============================================= -->
                    <!-- COMPRAS -->
                    <!-- ============================================= -->
                    <a href="<?php echo SITE_URL; ?>pages/compras.php" 
                    class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo ($page == 'compras') ? 'active' : ''; ?>">
                        <i class="fas fa-truck me-2"></i> Compras
                        <span class="badge bg-warning text-dark float-end">
                            <i class="fas fa-arrow-down"></i>
                        </span>
                    </a>
                    
                    <!-- ============================================= -->
                    <!-- RECETAS -->
                    <!-- ============================================= -->
                    <a href="<?php echo SITE_URL; ?>pages/recetas.php" 
                    class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo ($page == 'recetas') ? 'active' : ''; ?>">
                        <i class="fas fa-utensils me-2"></i> Recetas
                        <span class="badge bg-success float-end">
                            <i class="fas fa-chef-hat"></i>
                        </span>
                    </a>
                    
                    <!-- ============================================= -->
                    <!-- CLIENTES -->
                    <!-- ============================================= -->
                    <a href="<?php echo SITE_URL; ?>pages/clientes.php" 
                    class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo ($page == 'clientes') ? 'active' : ''; ?>">
                        <i class="fas fa-users me-2"></i> Clientes
                        <span class="badge bg-info float-end">
                            <i class="fas fa-address-book"></i>
                        </span>
                    </a>
                    
                    <!-- ============================================= -->
                    <!-- SEPARADOR (solo admin) -->
                    <!-- ============================================= -->
                    <?php if(isset($_SESSION['user_rol']) && $_SESSION['user_rol'] === 'admin'): ?>
                    <div class="border-bottom border-secondary my-2"></div>
                    
                    <!-- ============================================= -->
                    <!-- PAPELERA (solo admin) -->
                    <!-- ============================================= -->
                    <a href="<?php echo SITE_URL; ?>pages/papelera.php" 
                    class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo ($page == 'papelera') ? 'active' : ''; ?>">
                        <i class="fas fa-trash-alt me-2"></i> Papelera
                        <span class="badge bg-danger float-end">
                            <i class="fas fa-trash"></i>
                        </span>
                    </a>
                    
                    <!-- ============================================= -->
                    <!-- USUARIOS (solo admin) -->
                    <!-- ============================================= -->
                    <a href="<?php echo SITE_URL; ?>pages/usuarios.php" 
                    class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo ($page == 'usuarios') ? 'active' : ''; ?>">
                        <i class="fas fa-users-cog me-2"></i> Usuarios
                        <span class="badge bg-danger float-end">
                            <i class="fas fa-user-shield"></i>
                        </span>
                    </a>
                    <?php endif; ?>
                    
                </div>
            </div>
            
            <!-- ============================================= -->
            <!-- PAGE CONTENT -->
            <!-- ============================================= -->
            <div id="page-content-wrapper" class="w-100">
                <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
                    <div class="container-fluid">
                        <!-- Botón de toggle del sidebar -->
                        <button class="btn btn-outline-secondary" id="menu-toggle">
                            <i class="fas fa-bars"></i>
                        </button>
                        
                        <!-- Título de la página -->
                        <span class="navbar-text ms-3 d-none d-md-inline">
                            <?php echo $page_title ?? 'Dashboard'; ?>
                        </span>
                        
                        <!-- Información del usuario -->
                        <span class="navbar-text ms-auto">
                            <i class="fas fa-user-circle me-2"></i> 
                            <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?>
                            <a href="<?php echo SITE_URL; ?>logout.php" class="btn btn-sm btn-danger ms-2">
                                <i class="fas fa-sign-out-alt"></i>
                            </a>
                        </span>
                    </div>
                </nav>
                
                <div class="container-fluid px-4 py-4">
                    <?php echo $content ?? ''; ?>
                </div>
            </div>
        </div>
        
        <!-- Page Content -->
        <div id="page-content-wrapper" class="w-100">
            <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
                <div class="container-fluid">
                    <button class="btn btn-outline-secondary" id="menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span class="navbar-text ms-auto">
                        <i class="fas fa-user-circle me-2"></i> 
                        <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?>
                        <a href="logout.php" class="btn btn-sm btn-danger ms-2">
                            <i class="fas fa-sign-out-alt"></i> Salir
                        </a>
                    </span>
                </div>
            </nav>
            
            <div class="container-fluid px-4 py-4">
                <?php echo $content ?? ''; ?>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS y dependencias -->
    <!-- SCRIPTS -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- ============================================= -->
    <!-- main.js SIEMPRE DEBE ESTAR CARGADO -->
    <!-- ============================================= -->
    <script src="<?php echo SITE_URL; ?>assets/js/main.js"></script>

    <!-- Scripts específicos de cada página -->
    <?php echo $page_scripts ?? ''; ?>
</body>
</html>