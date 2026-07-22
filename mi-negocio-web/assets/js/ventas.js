// assets/js/ventas.js - VERSIÓN CORREGIDA CON PRECIO AUTOMÁTICO

$(document).ready(function() {
    console.log('✅ ventas.js cargado correctamente');
    
    // =============================================
    // VARIABLES
    // =============================================
    const API_URL = '/mi-negocio-web/api/ventas.php';
    const API_CLIENTES = '/mi-negocio-web/api/clientes.php';
    const API_PRODUCTOS = '/mi-negocio-web/api/productos.php';
    let productosDisponibles = [];
    let contadorProducto = 0;
    let guardando = false;
    
    // =============================================
    // CARGAR PRODUCTOS QUE SE VENDEN
    // =============================================
    function cargarProductosVenta() {
        console.log('🔄 Cargando productos para venta...');
        
        $.ajax({
            url: API_PRODUCTOS + '?venta=1',
            method: 'GET',
            xhrFields: { withCredentials: true },
            success: function(response) {
                console.log('📦 Respuesta del servidor:', response);
                
                if(response.success) {
                    productosDisponibles = response.data;
                    console.log('✅ Productos disponibles:', productosDisponibles.length);
                    
                    // Actualizar los selects que ya existen
                    actualizarSelectsProductos();
                } else {
                    console.error('❌ Error en respuesta:', response.message);
                }
            },
            error: function(xhr) {
                console.error('❌ Error al cargar productos:', xhr);
            }
        });
    }
    
    // =============================================
    // ACTUALIZAR SELECTS DE PRODUCTOS
    // =============================================
    function actualizarSelectsProductos() {
        console.log('🔄 Actualizando selects de productos...');
        
        $('.producto-select-venta').each(function() {
            let select = $(this);
            let currentValue = select.val();
            
            // Limpiar select (excepto la primera opción)
            select.find('option:not(:first)').remove();
            
            // Agregar productos
            let productosFiltrados = productosDisponibles.filter(p => p.stock > 0);
            
            if(productosFiltrados.length === 0) {
                select.append('<option value="" disabled>No hay productos disponibles</option>');
            } else {
                productosFiltrados.forEach(p => {
                    let opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.nombre + ' (Stock: ' + p.stock + ')';
                    opt.dataset.precio = p.precio_venta || 0;
                    opt.dataset.costo = p.costo_promedio || 0;
                    opt.dataset.stock = p.stock || 0;
                    select.append(opt);
                });
            }
            
            // Restaurar selección si existía
            if(currentValue) {
                select.val(currentValue);
            }
        });
    }
    
    // =============================================
    // ABRIR MODAL - Cargar productos
    // =============================================
    $('#modalVenta').on('show.bs.modal', function() {
        console.log('🔵 Abriendo modal de venta...');
        
        // Cargar productos frescos
        cargarProductosVenta();
        
        // Fecha actual
        let ahora = new Date();
        let fechaStr = ahora.getFullYear() + '-' + 
                        String(ahora.getMonth() + 1).padStart(2, '0') + '-' + 
                        String(ahora.getDate()).padStart(2, '0') + 'T' + 
                        String(ahora.getHours()).padStart(2, '0') + ':' + 
                        String(ahora.getMinutes()).padStart(2, '0');
        $('#fecha_venta').val(fechaStr);
    });
    
    // =============================================
    // BUSCAR VENTAS
    // =============================================
    $('#buscarVenta').on('keyup', function() {
        let search = $(this).val().toLowerCase();
        $('#tablaVentas tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(search) > -1);
        });
    });
    
    $('#filtroEstadoVenta').on('change', function() {
        let estado = $(this).val().toLowerCase();
        if(estado === '') {
            $('#tablaVentas tbody tr').show();
        } else {
            $('#tablaVentas tbody tr').each(function() {
                let rowEstado = $(this).find('td:eq(6)').text().trim().toLowerCase();
                $(this).toggle(rowEstado.includes(estado));
            });
        }
    });
    
    // =============================================
    // AGREGAR PRODUCTO A LA VENTA
    // =============================================
    window.agregarProductoVenta = function() {
        contadorProducto++;
        let id = contadorProducto;
        
        let html = `
            <tr id="producto_venta_${id}">
                <td>
                    <select class="form-select form-select-sm producto-select-venta" data-id="${id}" required>
                        <option value="">Seleccionar producto</option>
                    </select>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm cantidad-venta" data-id="${id}" value="1" min="1" required>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" class="form-control form-control-sm precio-venta-unitario" data-id="${id}" value="0.00" required>
                    </div>
                </td>
                <td class="text-end subtotal-venta" data-id="${id}">$0.00</td>
                <td class="text-end ganancia-producto" data-id="${id}">$0.00</td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="eliminarProductoVenta(${id})">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
        
        if($('#detallesVenta tr').length === 1 && $('#detallesVenta tr td').attr('colspan')) {
            $('#detallesVenta').empty();
        }
        
        $('#detallesVenta').append(html);
        
        // Llenar el select con productos disponibles
        let select = $(`#producto_venta_${id} .producto-select-venta`);
        let productosFiltrados = productosDisponibles.filter(p => p.stock > 0);
        
        if(productosFiltrados.length === 0) {
            select.append('<option value="" disabled>No hay productos disponibles</option>');
        } else {
            productosFiltrados.forEach(p => {
                let opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.nombre + ' (Stock: ' + p.stock + ')';
                opt.dataset.precio = p.precio_venta || 0;
                opt.dataset.costo = p.costo_promedio || 0;
                opt.dataset.stock = p.stock || 0;
                select.append(opt);
            });
        }
        
        recalcularTotalesVenta();
    };
    
    window.eliminarProductoVenta = function(id) {
        $(`#producto_venta_${id}`).remove();
        if($('#detallesVenta tr').length === 0) {
            $('#detallesVenta').html(`
                <tr><td colspan="6" class="text-center text-secondary py-3">
                    <i class="fas fa-plus-circle me-2"></i> Haz clic en "Agregar Producto"
                </td></tr>
            `);
        }
        recalcularTotalesVenta();
    };
    
    // =============================================
    // SELECCIONAR PRODUCTO - Actualizar precio automáticamente
    // =============================================
    $(document).on('change', '.producto-select-venta', function() {
        let id = $(this).data('id');
        let selectedOption = $(this).find(':selected');
        
        let precio = parseFloat(selectedOption.data('precio')) || 0;
        let costo = parseFloat(selectedOption.data('costo')) || 0;
        let stock = parseInt(selectedOption.data('stock')) || 0;
        
        console.log(`📊 Producto seleccionado - ID: ${id}, Precio: ${precio}, Costo: ${costo}, Stock: ${stock}`);
        
        // Actualizar el precio unitario
        let precioInput = $(`#producto_venta_${id} .precio-venta-unitario`);
        precioInput.val(precio.toFixed(2));
        
        // Limitar cantidad al stock disponible
        $(`#producto_venta_${id} .cantidad-venta`).attr('max', stock);
        
        recalcularTotalesVenta();
    });

    // =============================================
    // RECALCULAR TOTALES
    // =============================================
    function recalcularTotalesVenta() {
        let total = 0;
        let gananciaTotal = 0;
        
        $('#detallesVenta tr').each(function() {
            let select = $(this).find('.producto-select-venta');
            let id = select.data('id');
            let cantidad = parseFloat($(this).find('.cantidad-venta').val()) || 0;
            let precio = parseFloat($(this).find('.precio-venta-unitario').val()) || 0;
            let costo = parseFloat(select.find(':selected').data('costo')) || 0;
            
            let subtotal = cantidad * precio;
            let ganancia = (precio - costo) * cantidad;
            
            console.log(`📊 Producto ${id}: Cantidad=${cantidad}, Precio=${precio}, Costo=${costo}, Ganancia unitaria=${precio - costo}, Ganancia total=${ganancia}`);
            
            $(this).find('.subtotal-venta').text('$' + subtotal.toFixed(2));
            $(this).find('.ganancia-producto').text('$' + ganancia.toFixed(2));
            
            total += subtotal;
            gananciaTotal += ganancia;
        });
        
        $('#total_venta').text('$' + total.toFixed(2));
        $('#ganancia_total').text('$' + gananciaTotal.toFixed(2));
        
        console.log(`💰 Total: $${total.toFixed(2)}, Ganancia: $${gananciaTotal.toFixed(2)}`);
    }

    // =============================================
    // RECALCULAR AL CAMBIAR CANTIDAD O PRECIO
    // =============================================
    $(document).on('input', '.cantidad-venta, .precio-venta-unitario', function() {
        recalcularTotalesVenta();
    });
    
    // =============================================
    // GUARDAR VENTA
    // =============================================
    $('#formVenta').off('submit').on('submit', function(e) {
        e.preventDefault();
        
        if(guardando) return;
        
        let detalles = [];
        let valid = true;
        
        $('#detallesVenta tr').each(function() {
            let producto_id = $(this).find('.producto-select-venta').val();
            let cantidad = $(this).find('.cantidad-venta').val();
            let precio_unitario = $(this).find('.precio-venta-unitario').val();
            let costo = parseFloat($(this).find('.producto-select-venta option:selected').data('costo')) || 0;
            
            if(producto_id && cantidad && precio_unitario) {
                detalles.push({
                    producto_id: parseInt(producto_id),
                    cantidad: parseInt(cantidad),
                    precio_unitario: parseFloat(precio_unitario),
                    costo_unitario: costo,
                    subtotal: parseInt(cantidad) * parseFloat(precio_unitario)
                });
            } else if($(this).find('.producto-select-venta').length > 0) {
                valid = false;
            }
        });
        
        if(!valid || detalles.length === 0) {
            alert('⚠️ Agregue al menos un producto');
            return;
        }
        
        let data = {
            id: $('#venta_id').val() || null,
            cliente_id: $('#cliente_id').val() || null,
            fecha: $('#fecha_venta').val(),
            estado: $('#estado_venta').val(),
            observaciones: $('#observaciones_venta').val(),
            detalles: detalles
        };
        
        let btn = $(this).find('button[type="submit"]');
        let originalText = btn.html();
        btn.prop('disabled', true);
        btn.html('<i class="fas fa-spinner fa-spin me-2"></i> Registrando...');
        guardando = true;
        
        $.ajax({
            url: API_URL,
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            xhrFields: { withCredentials: true },
            timeout: 30000,
            success: function(response) {
                btn.prop('disabled', false);
                btn.html(originalText);
                guardando = false;
                
                if(response.success) {
                    alert('✅ Venta registrada correctamente');
                    location.reload();
                } else {
                    alert('❌ Error: ' + response.message);
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false);
                btn.html(originalText);
                guardando = false;
                alert('❌ Error al registrar la venta');
            }
        });
    });
    
    // =============================================
    // VER DETALLE DE VENTA
    // =============================================
    $(document).on('click', '.btn-ver', function() {
        let id = $(this).data('id');
        $('#modalVerVenta').modal('show');
        $('#detalleVenta').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div><p>Cargando...</p></div>');
        
        $.ajax({
            url: API_URL + '?id=' + id,
            method: 'GET',
            xhrFields: { withCredentials: true },
            success: function(response) {
                if(response.success) {
                    let v = response.data;
                    let html = `
                        <div class="row">
                            <div class="col-md-6"><h6>Cliente</h6><p>${v.cliente_nombre || 'Cliente general'}</p></div>
                            <div class="col-md-6"><h6>Fecha</h6><p>${new Date(v.fecha).toLocaleString()}</p></div>
                        </div>
                        <div class="row">
                            <div class="col-md-4"><h6>Total</h6><h4 class="text-success">$${parseFloat(v.total).toFixed(2)}</h4></div>
                            <div class="col-md-4"><h6>Ganancia</h6><h4 class="text-primary">$${parseFloat(v.ganancia || 0).toFixed(2)}</h4></div>
                            <div class="col-md-4"><h6>Estado</h6><span class="badge bg-${v.estado === 'pagada' ? 'success' : 'warning'}">${v.estado}</span></div>
                        </div>
                        <hr><h6>Productos</h6>
                        <div class="table-responsive"><table class="table table-sm">
                            <thead><tr><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Subtotal</th></tr></thead>
                            <tbody>${v.detalles && v.detalles.length > 0 ? v.detalles.map(d => `
                                <tr><td>${d.producto_nombre}</td><td>${d.cantidad}</td><td>$${parseFloat(d.precio_unitario).toFixed(2)}</td><td>$${parseFloat(d.subtotal).toFixed(2)}</td></tr>
                            `).join('') : '<tr><td colspan="4" class="text-center">Sin productos</td></tr>'}</tbody>
                        </table></div>
                        ${v.observaciones ? `<hr><h6>Observaciones</h6><p>${v.observaciones}</p>` : ''}
                    `;
                    $('#detalleVenta').html(html);
                }
            }
        });
    });
    
    // =============================================
    // CANCELAR VENTA
    // =============================================
    $(document).on('click', '.btn-cancelar', function() {
        let id = $(this).data('id');
        if(confirm('⚠️ ¿Seguro que deseas cancelar esta venta?')) {
            $.ajax({
                url: API_URL,
                method: 'DELETE',
                data: JSON.stringify({ id: id }),
                contentType: 'application/json',
                xhrFields: { withCredentials: true },
                success: function(response) {
                    if(response.success) {
                        alert('✅ Venta cancelada');
                        location.reload();
                    } else {
                        alert('❌ Error: ' + response.message);
                    }
                }
            });
        }
    });
    
    // =============================================
    // NUEVO CLIENTE
    // =============================================
    window.abrirNuevoCliente = function() {
        $('#nuevo_cliente_nombre').val('');
        $('#nuevo_cliente_apellidos').val('');
        $('#nuevo_cliente_telefono').val('');
        $('#nuevo_cliente_email').val('');
        $('#modalNuevoCliente').modal('show');
    };
    
    window.guardarNuevoCliente = function() {
        let nombre = $('#nuevo_cliente_nombre').val().trim();
        if(nombre === '') {
            alert('⚠️ El nombre es obligatorio');
            return;
        }
        
        let data = {
            nombre: nombre,
            apellidos: $('#nuevo_cliente_apellidos').val().trim() || '',
            telefono: $('#nuevo_cliente_telefono').val().trim() || '',
            email: $('#nuevo_cliente_email').val().trim() || ''
        };
        
        let btn = document.querySelector('#modalNuevoCliente .btn-primary');
        let originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Guardando...';
        btn.disabled = true;
        
        $.ajax({
            url: API_CLIENTES,
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            xhrFields: { withCredentials: true },
            success: function(response) {
                btn.innerHTML = originalText;
                btn.disabled = false;
                
                if(response.success) {
                    alert('✅ Cliente creado correctamente');
                    $('#modalNuevoCliente').modal('hide');
                    
                    let select = document.getElementById('cliente_id');
                    let option = document.createElement('option');
                    option.value = response.id;
                    option.textContent = nombre + (data.apellidos ? ' ' + data.apellidos : '');
                    option.selected = true;
                    select.appendChild(option);
                } else {
                    alert('❌ Error: ' + response.message);
                }
            },
            error: function() {
                btn.innerHTML = originalText;
                btn.disabled = false;
                alert('❌ Error al guardar el cliente');
            }
        });
    };
    
    // =============================================
    // RESETEAR MODAL
    // =============================================
    $('#modalVenta').on('hidden.bs.modal', function() {
        $('#formVenta')[0].reset();
        $('#venta_id').val('');
        $('#detallesVenta').html(`
            <tr><td colspan="6" class="text-center text-secondary py-3">
                <i class="fas fa-plus-circle me-2"></i> Haz clic en "Agregar Producto"
            </td></tr>
        `);
        $('#total_venta').text('$0.00');
        $('#ganancia_total').text('$0.00');
        contadorProducto = 0;
    });
});

console.log('✅ ventas.js cargado correctamente');