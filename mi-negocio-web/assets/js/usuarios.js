// assets/js/usuarios.js
$(document).ready(function() {
    // Buscar usuarios
    $('#buscarUsuario').on('keyup', function() {
        let search = $(this).val().toLowerCase();
        $('.usuario-item').each(function() {
            let text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(search) > -1);
        });
    });
    
    // Filtrar por rol
    $('#filtroRol').on('change', function() {
        let rol = $(this).val();
        $('.usuario-item').each(function() {
            let userRol = $(this).data('rol');
            if(rol === '' || userRol === rol) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // Filtrar por estado
    $('#filtroEstado').on('change', function() {
        let estado = $(this).val();
        $('.usuario-item').each(function() {
            let activo = $(this).data('activo');
            if(estado === '' || activo == estado) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // Editar usuario
    $('.btn-editar').on('click', function() {
        let id = $(this).data('id');
        $.ajax({
            url: '../api/usuarios.php?id=' + id,
            method: 'GET',
            success: function(response) {
                if(response.success) {
                    let data = response.data;
                    $('#usuario_id').val(data.id);
                    $('#nombre').val(data.nombre);
                    $('#email').val(data.email);
                    $('#rol').val(data.rol);
                    $('#activo').prop('checked', data.activo == 1);
                    $('#password').val('');
                    $('#campo_password .text-muted').show();
                    $('#modalTitulo').html('<i class="fas fa-user-edit me-2"></i> Editar Usuario');
                    $('#modalUsuario').modal('show');
                }
            }
        });
    });
    
    // Eliminar usuario
    $('.btn-eliminar').on('click', function() {
        let id = $(this).data('id');
        let nombre = $(this).closest('.card-body').find('h5').text();
        
        if(confirm('¿Seguro que deseas eliminar al usuario "' + nombre + '"?')) {
            $.ajax({
                url: '../api/usuarios.php',
                method: 'DELETE',
                data: JSON.stringify({ id: id }),
                success: function(response) {
                    if(response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                }
            });
        }
    });
    
    // Resetear modal al cerrar
    $('#modalUsuario').on('hidden.bs.modal', function() {
        $('#formUsuario')[0].reset();
        $('#usuario_id').val('');
        $('#password').val('');
        $('#campo_password .text-muted').hide();
        $('#modalTitulo').html('<i class="fas fa-user-plus me-2"></i> Nuevo Usuario');
    });
    
    // Guardar usuario
    $('#formUsuario').on('submit', function(e) {
        e.preventDefault();
        
        let formData = {
            id: $('#usuario_id').val(),
            nombre: $('#nombre').val(),
            email: $('#email').val(),
            password: $('#password').val(),
            rol: $('#rol').val(),
            activo: $('#activo').is(':checked') ? 1 : 0
        };
        
        // Validar contraseña para nuevo usuario
        if(!formData.id && formData.password.length < 6) {
            alert('La contraseña debe tener al menos 6 caracteres');
            return;
        }
        
        $.ajax({
            url: '../api/usuarios.php',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            success: function(response) {
                if(response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Error al guardar el usuario');
            }
        });
    });
});

// Toggle password visibility
function togglePassword() {
    let input = document.getElementById('password');
    let icon = document.getElementById('toggleIcon');
    if(input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}