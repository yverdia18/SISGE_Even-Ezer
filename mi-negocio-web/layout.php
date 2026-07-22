<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - <?php echo $page_title ?? 'Dashboard'; ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Estilos específicos de página -->
    <?php echo $page_styles ?? ''; ?>
</head>
<body>

<div class="d-flex" id="wrapper">
    <!-- ============================================= -->
    <!-- SIDEBAR -->
    <!-- ============================================= -->
    <div class="bg-dark text-white" id="sidebar-wrapper">
        <div class="sidebar-heading text-center py-4 border-bottom">
            <h4><?php echo SITE_NAME; ?></h4>
        </div>
        <div class="list-group list-group-flush">
            <!-- ✅ Dashboard - ruta corregida -->
            <a href="/mi-negocio-web/dashboard.php" 
                class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo ($page == 'dashboard') ? 'active' : ''; ?>">
                <i class="fas fa-chart-line me-2"></i> Dashboard
                <span class="badge bg-primary float-end"><i class="fas fa-home"></i></span>
            </a>
            
            <!-- ✅ Productos - ruta corregida -->
            <a href="/mi-negocio-web/pages/productos.php" 
                class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo ($page == 'productos') ? 'active' : ''; ?>">
                <i class="fas fa-box me-2"></i> Productos
                <span class="badge bg-primary float-end"><i class="fas fa-cube"></i></span>
            </a>
            
            <!-- ✅ Categorías - ruta corregida -->
            <a href="/mi-negocio-web/pages/categorias.php" 
                class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo ($page == 'categorias') ? 'active' : ''; ?>">
                <i class="fas fa-tags me-2"></i> Categorías
                <span class="badge bg-info float-end"><i class="fas fa-tag"></i></span>
            </a>
            
            <!-- ✅ Ventas - ruta corregida -->
            <a href="/mi-negocio-web/pages/ventas.php" 
                class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo ($page == 'ventas') ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart me-2"></i> Ventas
                <span class="badge bg-success float-end"><i class="fas fa-money-bill-wave"></i></span>
            </a>
            
            <!-- ✅ Compras - ruta corregida -->
            <a href="/mi-negocio-web/pages/compras.php" 
                class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo ($page == 'compras') ? 'active' : ''; ?>">
                <i class="fas fa-truck me-2"></i> Compras
                <span class="badge bg-warning text-dark float-end"><i class="fas fa-arrow-down"></i></span>
            </a>
            
            <!-- ✅ Recetas - ruta corregida -->
            <a href="/mi-negocio-web/pages/recetas.php" 
                class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo ($page == 'recetas') ? 'active' : ''; ?>">
                <i class="fas fa-utensils me-2"></i> Recetas
                <span class="badge bg-success float-end"><i class="fas fa-chef-hat"></i></span>
            </a>
            
            <!-- ✅ Clientes - ruta corregida -->
            <a href="/mi-negocio-web/pages/clientes.php" 
                class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo ($page == 'clientes') ? 'active' : ''; ?>">
                <i class="fas fa-users me-2"></i> Clientes
                <span class="badge bg-info float-end"><i class="fas fa-address-book"></i></span>
            </a>
            
            <?php if(isset($_SESSION['user_rol']) && $_SESSION['user_rol'] === 'admin'): ?>
            <div class="border-bottom border-secondary my-2"></div>
            
            <!-- ✅ Papelera - ruta corregida -->
            <a href="/mi-negocio-web/pages/papelera.php" 
                class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo ($page == 'papelera') ? 'active' : ''; ?>">
                <i class="fas fa-trash-alt me-2"></i> Papelera
                <span class="badge bg-danger float-end"><i class="fas fa-trash"></i></span>
            </a>
            
            <!-- ✅ Usuarios - ruta corregida -->
            <a href="/mi-negocio-web/pages/usuarios.php" 
                class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo ($page == 'usuarios') ? 'active' : ''; ?>">
                <i class="fas fa-users-cog me-2"></i> Usuarios
                <span class="badge bg-danger float-end"><i class="fas fa-user-shield"></i></span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ============================================= -->
    <!-- CONTENIDO PRINCIPAL -->
    <!-- ============================================= -->
    <div id="page-content-wrapper" class="w-100">
        <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
            <div class="container-fluid">
                <button class="btn btn-outline-secondary" id="menu-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="navbar-text ms-3 d-none d-md-inline">
                    <?php echo $page_title ?? 'Dashboard'; ?>
                </span>
                <span class="navbar-text ms-auto">
                    <i class="fas fa-user-circle me-2"></i> 
                    <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?>
                    <a href="/mi-negocio-web/logout.php" class="btn btn-sm btn-danger ms-2">
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

<!-- ============================================= -->
<!-- SCRIPTS - ORDEN CORRECTO (RUTAS ABSOLUTAS) -->
<!-- ============================================= -->

<!-- Bootstrap JS (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- jQuery (LOCAL) -->
<script src="/mi-negocio-web/assets/js/jquery-3.6.0.min.js"></script>

<!-- Chart.js (LOCAL) -->
<script src="/mi-negocio-web/assets/js/chart.min.js"></script>

<!-- main.js (scripts globales) -->
<script src="/mi-negocio-web/assets/js/main.js"></script>

<!-- Scripts específicos de cada página -->
<?php echo $page_scripts ?? ''; ?>

</body>
</html>