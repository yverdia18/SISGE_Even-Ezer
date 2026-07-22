// assets/js/papelera.js - MÓDULO DE PAPELERA

$(document).ready(function() {
    console.log('✅ papelera.js cargado correctamente');
    
    const API_URL = '/mi-negocio-web/api/papelera.php';
    
    // =============================================
    // VER DATOS DEL ELEMENTO
    // =============================================
    $(document).on('click', '.btn-ver-datos', function() {
        let datos = $(this).data('datos');
        
        try {
            let dataObj = JSON.parse(datos);
            let datosFormateados = JSON.stringify(dataObj, null, 2);
            $('#datosJson').text(datosFormateados);
            $('#modalVerDatos').modal('show');
        } catch(e) {
            $('#datosJson').text(datos);
            $('#modalVerDatos').modal('show');
        }
    });
    
    // =============================================
    // RESTAURAR ELEMENTO
    // =============================================
    $(document).on('click', '.btn-restaurar', function() {
        let id = $(this).data('id');
        let btn = $(this);
        
        if(!confirm('⚠️ ¿Seguro que deseas restaurar este elemento?')) {
            return;
        }
        
        let originalText = btn.html();
        btn.prop('disabled', true);
        btn.html('<i class="fas fa-spinner fa-spin"></i>');
        
        $.ajax({
            url: API_URL,
            method: 'POST',
            data: JSON.stringify({ action: 'restaurar', id: id }),
            contentType: 'application/json',
            xhrFields: { withCredentials: true },
            success: function(response) {
                btn.prop('disabled', false);
                btn.html(originalText);
                
                if(response.success) {
                    alert('✅ Elemento restaurado correctamente');
                    location.reload();
                } else {
                    alert('❌ Error: ' + response.message);
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false);
                btn.html(originalText);
                alert('❌ Error al restaurar');
            }
        });
    });
    
    // =============================================
    // ELIMINAR DEFINITIVAMENTE
    // =============================================
    $(document).on('click', '.btn-eliminar-definitivo', function() {
        let id = $(this).data('id');
        let btn = $(this);
        
        if(!confirm('⚠️ ¿Seguro que deseas ELIMINAR DEFINITIVAMENTE este elemento?\nEsta acción NO se puede deshacer.')) {
            return;
        }
        
        if(!confirm('⚠️ Confirmación final: ¿Estás ABSOLUTAMENTE SEGURO?')) {
            return;
        }
        
        let originalText = btn.html();
        btn.prop('disabled', true);
        btn.html('<i class="fas fa-spinner fa-spin"></i>');
        
        $.ajax({
            url: API_URL,
            method: 'DELETE',
            data: JSON.stringify({ id: id }),
            contentType: 'application/json',
            xhrFields: { withCredentials: true },
            success: function(response) {
                btn.prop('disabled', false);
                btn.html(originalText);
                
                if(response.success) {
                    alert('✅ Elemento eliminado definitivamente');
                    location.reload();
                } else {
                    alert('❌ Error: ' + response.message);
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false);
                btn.html(originalText);
                alert('❌ Error al eliminar');
            }
        });
    });
    
    // =============================================
    // VACIAR PAPELERA
    // =============================================
    window.vaciarPapelera = function() {
        if(!confirm('⚠️ ¿Seguro que deseas VACIAR COMPLETAMENTE la papelera?\nEsta acción eliminará TODOS los elementos permanentemente.')) {
            return;
        }
        
        if(!confirm('⚠️ Confirmación final: ¿Estás ABSOLUTAMENTE SEGURO?')) {
            return;
        }
        
        $.ajax({
            url: API_URL,
            method: 'DELETE',
            data: JSON.stringify({ action: 'vaciar' }),
            contentType: 'application/json',
            xhrFields: { withCredentials: true },
            success: function(response) {
                if(response.success) {
                    alert('✅ Papelera vaciada correctamente');
                    location.reload();
                } else {
                    alert('❌ Error: ' + response.message);
                }
            },
            error: function(xhr) {
                alert('❌ Error al vaciar la papelera');
            }
        });
    };
});

console.log('✅ papelera.js cargado correctamente');