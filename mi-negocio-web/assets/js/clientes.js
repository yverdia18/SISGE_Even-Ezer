// assets/js/clientes.js
$(document).ready(function() {
    // Buscar clientes
    $('#buscarCliente').on('keyup', function() {
        let search = $(this).val().toLowerCase();
        $('#tablaClientes tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(search) > -1);
        });
    });
    
    // Filtrar por área
    $('#filtroArea').on('change', function() {
        let area = $(this).val().toLowerCase();
        if(area === '') {
            $('#tablaClientes tbody tr').show();
        } else {
            $('#tablaClientes tbody tr').each(function() {
                let rowArea = $(this).find('td:eq(4)').text().trim().toLowerCase();
                $(this).toggle(rowArea.includes(area));
            });
        }
    });
    
    // Editar cliente
    $('.btn-editar').on('click', function() {
        let id = $(this).data('id');
        $.ajax({
            url: '../api/clientes.php?id=' + id,
            method: 'GET',
            success: function(response) {
                if(response.success) {
                    let data = response.data;
                    $('#cliente_id').val(data.id);
                    $('#nombre').val(data.nombre);
                    $('#apellidos').val(data.apellidos);
                    $('#telefono').val(data.telefono);
                    $('#telefono2').val(data.telefono2);
                    $('#email').val(data.email);
                    $('#cuenta_bancaria').val(data.cuenta_bancaria);
                    $('#banco').val(data.banco);
                    $('#area_trabajo').val(data.area_trabajo);
                    $('#puesto').val(data.puesto);
                    $('#direccion').val(data.direccion);
                    $('#direccion2').val(data.direccion2);
                    $('#ciudad').val(data.ciudad);
                    $('#estado').val(data.estado);
                    $('#codigo_postal').val(data.codigo_postal);
                    $('#notas').val(data.notas);
                    $('#redes_sociales').val(data.redes_sociales);
                    $('#modalTitulo').html('<i class="fas fa-user-edit me-2"></i> Editar Cliente');
                    $('#modalCliente').modal('show');
                }
            }
        });
    });
    
    // Ver detalle del cliente
    $('.btn-ver').on('click', function() {
        let id = $(this).data('id');
        $('#modalVerCliente').modal('show');
        $('#detalleCliente').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mt-2 text-secondary">Cargando información del cliente...</p>
            </div>
        `);
        
        $.ajax({
            url: '../api/clientes.php?id=' + id,
            method: 'GET',
            success: function(response) {
                if(response.success) {
                    let c = response.data;
                    let html = `
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="fw-bold">${c.nombre} ${c.apellidos || ''}</h5>
                                <p class="text-secondary">
                                    <i class="fas fa-envelope me-2"></i> ${c.email || 'No especificado'}
                                    <br>
                                    <i class="fas fa-phone me-2"></i> ${c.telefono || 'No especificado'}
                                    ${c.telefono2 ? `<br><i class="fas fa-phone me-2"></i> ${c.telefono2}` : ''}
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold">Información</h6>
                                <p class="text-secondary">
                                    <i class="fas fa-briefcase me-2"></i> ${c.area_trabajo || 'No especificado'}
                                    ${c.puesto ? `<br><i class="fas fa-user-tag me-2"></i> ${c.puesto}` : ''}
                                    <br>
                                    <i class="fas fa-university me-2"></i> ${c.banco || 'No especificado'}
                                    ${c.cuenta_bancaria ? `<br><i class="fas fa-credit-card me-2"></i> ${c.cuenta_bancaria}` : ''}
                                </p>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="fw-bold">Dirección</h6>
                                <p class="text-secondary">
                                    ${c.direccion || 'No especificada'}
                                    ${c.ciudad ? `<br>${c.ciudad}` : ''}
                                    ${c.estado ? `, ${c.estado}` : ''}
                                    ${c.codigo_postal ? ` - CP: ${c.codigo_postal}` : ''}
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold">Redes Sociales</h6>
                                <p class="text-secondary">${c.redes_sociales || 'No especificadas'}</p>
                            </div>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <h6 class="fw-bold">Notas</h6>
                            <p class="text-secondary">${c.notas || 'Sin notas'}</p>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="fw-bold">Total Compras</h6>
                                <h4 class="text-primary">${c.total_compras || 0}</h4>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold">Total Gastado</h6>
                                <h4 class="text-success">$${parseFloat(c.total_gastado || 0).toFixed(2)}</h4>
                            </div>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <h6 class="fw-bold">Últimas Ventas</h6>
                            ${c.ventas && c.ventas.length > 0 ? `
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Fecha</th>
                                                <th>Total</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${c.ventas.map(v => `
                                                <tr>
                                                    <td>${v.id}</td>
                                                    <td>${new Date(v.fecha).toLocaleDateString()}</td>
                                                    <td>$${parseFloat(v.total).toFixed(2)}</td>
                                                    <td><span class="badge bg-success">${v.estado}</span></td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            ` : '<p class="text-secondary">No hay ventas registradas</p>'}
                        </div>
                    `;
                    $('#detalleCliente').html(html);
                }
            }
        });
    });
    
    // Eliminar cliente
    $('.btn-eliminar').on('click', function() {
        if(confirm('¿Seguro que deseas eliminar este cliente?')) {
            let id = $(this).data('id');
            $.ajax({
                url: '../api/clientes.php',
                method: 'DELETE',
                data: JSON.stringify({ id: id }),
                success: function(response) {
                    if(response.success) {
                        location.reload();
                    } else {
                        alert('Error al eliminar');
                    }
                }
            });
        }
    });
    
    // Resetear modal
    $('#modalCliente').on('hidden.bs.modal', function() {
        $('#formCliente')[0].reset();
        $('#cliente_id').val('');
        $('#modalTitulo').html('<i class="fas fa-user-plus me-2"></i> Nuevo Cliente');
    });
    
    // Guardar cliente
    $('#formCliente').on('submit', function(e) {
        e.preventDefault();
        let formData = {
            id: $('#cliente_id').val(),
            nombre: $('#nombre').val(),
            apellidos: $('#apellidos').val(),
            telefono: $('#telefono').val(),
            telefono2: $('#telefono2').val(),
            email: $('#email').val(),
            cuenta_bancaria: $('#cuenta_bancaria').val(),
            banco: $('#banco').val(),
            area_trabajo: $('#area_trabajo').val(),
            puesto: $('#puesto').val(),
            direccion: $('#direccion').val(),
            direccion2: $('#direccion2').val(),
            ciudad: $('#ciudad').val(),
            estado: $('#estado').val(),
            codigo_postal: $('#codigo_postal').val(),
            notas: $('#notas').val(),
            redes_sociales: $('#redes_sociales').val()
        };
        
        $.ajax({
            url: '../api/clientes.php',
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
                alert('Error al guardar el cliente');
            }
        });
    });
});

// Exportar clientes a CSV
function exportarClientes() {
    window.location.href = '../api/exportar_clientes.php';
}