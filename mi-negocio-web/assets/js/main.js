// assets/js/main.js - FUNCIONALIDAD DEL SIDEBAR

$(document).ready(function() {
    console.log('✅ main.js cargado correctamente');
    
    // =============================================
    // TOGGLE DEL SIDEBAR
    // =============================================
    $("#menu-toggle").click(function(e) {
        e.preventDefault();
        $("#wrapper").toggleClass("toggled");
        
        // Guardar estado en localStorage
        let isToggled = $("#wrapper").hasClass("toggled");
        localStorage.setItem('sidebarToggled', isToggled);
    });
    
    // =============================================
    // RESTAURAR ESTADO
    // =============================================
    let savedState = localStorage.getItem('sidebarToggled');
    if (savedState === 'true') {
        $("#wrapper").addClass("toggled");
    }
    
    // =============================================
    // CERRAR SIDEBAR EN MÓVIL
    // =============================================
    $(document).on('click', '#page-content-wrapper', function(e) {
        if (window.innerWidth < 768 && $("#wrapper").hasClass("toggled")) {
            if (!$(e.target).closest('#sidebar-wrapper').length) {
                $("#wrapper").removeClass("toggled");
                localStorage.setItem('sidebarToggled', false);
            }
        }
    });
    
    // Cerrar sidebar al hacer clic en un enlace (móvil)
    $('#sidebar-wrapper .list-group-item').on('click', function() {
        if (window.innerWidth < 768) {
            $("#wrapper").removeClass("toggled");
            localStorage.setItem('sidebarToggled', false);
        }
    });
    
    // Ajustar al redimensionar
    $(window).on('resize', function() {
        if (window.innerWidth >= 768) {
            $("#wrapper").removeClass("toggled");
        }
    });
});

console.log('✅ main.js cargado correctamente');