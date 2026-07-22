// assets/js/compras.js - MÓDULO DE COMPRAS COMPLETO CON UNIDADES

// =============================================
// URLS ABSOLUTAS - GLOBALES
// =============================================
const API_URL = '/mi-negocio-web/api/compras.php';
const API_CLIENTES = '/mi-negocio-web/api/clientes.php';
const API_PRODUCTOS = '/mi-negocio-web/api/productos.php';

$(document).ready(function() {
    console.log('✅ compras.js cargado correctamente');
    
    // =============================================
    // VARIABLES
    // =============================================
    let productosDisponibles = [];
    let contadorProducto = 0;
    let guardando = false;
    
    // =============================================
    // CARGAR PRODUCTOS
    // =============================================
    function cargarProductos() {
        $.ajax({
            url: API_PRODUCTOS,
            method: 'GET',
            xhrFields: { withCredentials: true },
            success: function(response) {
                if(response.success) {
                    productosDisponibles = response.data;
                    console.log('📦 Productos cargados:', productosDisponibles.length);
                }
            }
        });
    }
    cargarProductos();
    
    // =============================================
    // FECHAS
    // =============================================
    $('#modalCompra').on('show.bs.modal', function() {
        let ahora = new Date();
        let fechaStr = ahora.getFullYear() + '-' + String(ahora.getMonth()+1).padStart(2,'0') + '-' + String(ahora.getDate()).padStart(2,'0') + 'T' + String(ahora.getHours()).padStart(2,'0') + ':' + String(ahora.getMinutes()).padStart(2,'0');
        $('#fecha').val(fechaStr);
        let fechaEntrega = new Date();
        fechaEntrega.setDate(fechaEntrega.getDate() + 3);
        let entregaStr = fechaEntrega.getFullYear() + '-' + String(fechaEntrega.getMonth()+1).padStart(2,'0') + '-' + String(fechaEntrega.getDate()).padStart(2,'0');
        $('#fecha_entrega').val(entregaStr);
    });
    
    // =============================================
    // BUSCAR Y FILTRAR
    // =============================================
    $('#buscarCompra').on('keyup', function() {
        let search = $(this).val().toLowerCase();
        $('#tablaCompras tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(search) > -1);
        });
    });
    
    $('#filtroEstado').on('change', function() {
        let estado = $(this).val().toLowerCase();
        if(estado === '') {
            $('#tablaCompras tbody tr').show();
        } else {
            $('#tablaCompras tbody tr').each(function() {
                let rowEstado = $(this).find('td:eq(6)').text().trim().toLowerCase();
                $(this).toggle(rowEstado.includes(estado));
            });
        }
    });
    
    // =============================================
    // TIPO DE COMPRA
    // =============================================
    $('#tipo_compra').on('change', function() {
        if($(this).val() === 'mayorista') {
            $('#campo_factura').show();
        } else {
            $('#campo_factura').hide();
            $('#numero_factura').val('');
        }
    });
    
    // =============================================
    // AGREGAR PRODUCTO CON UNIDAD
    // =============================================
    window.agregarProducto = function() {
        console.log('🔵 agregarProducto() ejecutado');
        contadorProducto++;
        let id = contadorProducto;
        
        let html = `
            <tr id="producto_${id}">
                <td>
                    <select class="form-select form-select-sm producto-select" data-id="${id}" required>
                        <option value="">Seleccionar producto...</option>
                    </select>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm cantidad" data-id="${id}" value="1" min="1" required>
                </td>
                <td>
                    <select class="form-select form-select-sm unidad-select" data-id="${id}" required>
                        <option value="unidad">Unidad</option>
                        <option value="g">Gramos (g)</option>
                        <option value="kg">Kilogramos (kg)</option>
                        <option value="lb">Libras (lb)</option>
                        <option value="oz">Onzas (oz)</option>
                        <option value="ml">Mililitros (ml)</option>
                        <option value="litro">Litros</option>
                        <option value="paquete">Paquete</option>
                    </select>
                </td>
                <td>
                    <input type="number" step="0.01" class="form-control form-control-sm precio_unitario" data-id="${id}" value="0.00" required>
                </td>
                <td class="text-end subtotal_producto" data-id="${id}">$0.00</td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="eliminarProducto(${id})">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
        
        if($('#detallesCompra tr').length === 1 && $('#detallesCompra tr td').attr('colspan')) {
            $('#detallesCompra').empty();
        }
        $('#detallesCompra').append(html);
        
        // Llenar el select de productos
        let selectProducto = $(`#producto_${id} .producto-select`);
        productosDisponibles.forEach(function(p) {
            let opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.nombre + ' (Stock: ' + (p.stock || 0) + ')';
            opt.dataset.precio = p.precio_compra || 0;
            opt.dataset.stock = p.stock || 0;
            opt.dataset.unidadBase = p.unidad_base || 'g';
            opt.dataset.unidadCompra = p.unidad_compra || 'unidad';
            selectProducto.append(opt);
        });
        
        // Vincular eventos para recalcular
        $(`#producto_${id} .cantidad`).on('input', function() {
            recalcularTotales();
        });
        $(`#producto_${id} .precio_unitario`).on('input', function() {
            recalcularTotales();
        });
        $(`#producto_${id} .unidad-select`).on('change', function() {
            recalcularTotales();
        });
        
        recalcularTotales();
        console.log('✅ Producto agregado, ID:', id);
    };
    
    window.eliminarProducto = function(id) {
        console.log('🗑️ Eliminando producto:', id);
        $(`#producto_${id}`).remove();
        if($('#detallesCompra tr').length === 0) {
            $('#detallesCompra').html(`
                <tr><td colspan="6" class="text-center text-secondary py-3">
                    <i class="fas fa-plus-circle me-2"></i> Haz clic en "Agregar Producto"
                </td></tr>
            `);
        }
        recalcularTotales();
    };
    
    function recalcularTotales() {
        let total = 0;
        $('#detallesCompra tr').each(function() {
            let cantidad = parseFloat($(this).find('.cantidad').val()) || 0;
            let precio = parseFloat($(this).find('.precio_unitario').val()) || 0;
            let subtotal = cantidad * precio;
            $(this).find('.subtotal_producto').text('$' + subtotal.toFixed(2));
            total += subtotal;
        });
        $('#total_compra').text('$' + total.toFixed(2));
        console.log('💰 Total:', total.toFixed(2));
    }
    
    $(document).on('change', '.producto-select', function() {
        let id = $(this).data('id');
        let selected = $(this).find(':selected');
        let precio = parseFloat(selected.data('precio')) || 0;
        let unidadCompra = selected.data('unidadcompra') || 'unidad';
        
        $(`#producto_${id} .precio_unitario`).val(precio.toFixed(2));
        
        let selectUnidad = $(`#producto_${id} .unidad-select`);
        if(unidadCompra) {
            selectUnidad.val(unidadCompra);
        }
        recalcularTotales();
    });
    
    $(document).on('input', '.cantidad, .precio_unitario', function() {
        recalcularTotales();
    });
    
    // =============================================
    // ALERTAS
    // =============================================
    function mostrarAlertasCompra(errores) {
        let container = $('#alertas_compra');
        if(container.length === 0) {
            let msg = '❌ Error:\n';
            errores.forEach(function(e) { msg += '- ' + e.mensaje + '\n' + (e.detalle||'') + '\n'; });
            alert(msg);
            return;
        }
        container.empty();
        let html = '<div class="alert alert-danger alert-dismissible fade show"><div class="d-flex align-items-start"><div class="me-3"><i class="fas fa-exclamation-circle fa-2x"></i></div><div><strong>⚠️ No se pudo guardar la compra</strong><br><span class="text-muted small">Se encontraron los siguientes errores:</span><ul class="mt-2 mb-0" style="padding-left:20px;">';
        errores.forEach(function(error) {
            html += '<li><strong>' + error.mensaje + '</strong>';
            if(error.detalle) html += '<br><span class="text-muted small">' + error.detalle + '</span>';
            if(error.sugerencia) html += '<br><span class="text-muted small">💡 ' + error.sugerencia + '</span>';
            if(error.campo) html += '<br><span class="text-muted small">📍 Campo: <strong>' + error.campo + '</strong></span>';
            html += '</li>';
        });
        html += '</ul></div></div><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        container.html(html);
        setTimeout(function() { container.find('.alert').alert('close'); }, 10000);
    }
    
    function mostrarError(titulo, detalle, sugerencia) {
        $('#mensaje_error_titulo').remove();
        let html = '<div id="mensaje_error_titulo" class="alert alert-danger alert-dismissible fade show"><div class="d-flex"><div class="me-3"><i class="fas fa-exclamation-circle fa-2x"></i></div><div><strong>' + titulo + '</strong>' + (detalle ? '<br><span class="text-muted small">' + detalle + '</span>' : '') + (sugerencia ? '<br><span class="text-muted small">💡 ' + sugerencia + '</span>' : '') + '</div></div><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        $('#formPago .modal-body').prepend(html);
        setTimeout(function() { $('#mensaje_error_titulo').alert('close'); }, 8000);
    }
    
    // =============================================
    // GUARDAR COMPRA
    // =============================================
    $('#formCompra').off('submit').on('submit', function(e) {
        e.preventDefault();
        console.log('🔵 Intentando guardar compra...');
        if(guardando) { console.log('⏳ Ya se está guardando...'); return; }
        $('#alertas_compra').empty();
        
        let detalles = [];
        let valid = true;
        let errores = [];
        
        let proveedor_id = $('#proveedor_id').val();
        if(!proveedor_id) {
            errores.push({ campo: 'proveedor', mensaje: '❌ Proveedor no seleccionado', detalle: 'Debes seleccionar un proveedor.', sugerencia: 'Selecciona un proveedor de la lista.' });
            valid = false;
        }
        
        $('#detallesCompra tr').each(function() {
            let producto_id = $(this).find('.producto-select').val();
            let cantidad = $(this).find('.cantidad').val();
            let unidad = $(this).find('.unidad-select').val();
            let precio_unitario = $(this).find('.precio_unitario').val();
            if(producto_id && cantidad && precio_unitario) {
                detalles.push({
                    producto_id: parseInt(producto_id),
                    cantidad: parseInt(cantidad),
                    unidad: unidad,
                    precio_unitario: parseFloat(precio_unitario),
                    subtotal: parseInt(cantidad) * parseFloat(precio_unitario)
                });
            }
        });
        
        if(detalles.length === 0) {
            errores.push({ campo: 'productos', mensaje: '❌ Sin productos', detalle: 'No has agregado ningún producto.', sugerencia: 'Agrega al menos un producto.' });
            valid = false;
        }
        
        let fecha = $('#fecha').val();
        if(fecha) {
            let fechaSel = new Date(fecha);
            let fechaAct = new Date();
            fechaAct.setHours(0,0,0,0);
            fechaSel.setHours(0,0,0,0);
            if(fechaSel > fechaAct) {
                errores.push({ campo: 'fecha', mensaje: '❌ Fecha futura no permitida', detalle: 'La fecha no puede ser posterior a hoy.', sugerencia: 'Selecciona una fecha igual o anterior a hoy.' });
                valid = false;
            }
        }
        
        let tipo_compra = $('#tipo_compra').val();
        if(tipo_compra === 'mayorista') {
            let factura = $('#numero_factura').val().trim();
            if(!factura) {
                errores.push({ campo: 'factura', mensaje: '❌ Número de factura requerido', detalle: 'Para compras al por mayor, es obligatorio.', sugerencia: 'Ingresa el número de factura.' });
                valid = false;
            }
        }
        
        if(!valid || errores.length > 0) {
            mostrarAlertasCompra(errores);
            return;
        }
        
        let data = {
            id: $('#compra_id').val() || null,
            proveedor_id: proveedor_id,
            tipo_compra: tipo_compra,
            numero_factura: $('#numero_factura').val() || '',
            fecha: fecha,
            fecha_entrega: $('#fecha_entrega').val() || null,
            observaciones: $('#observaciones').val() || '',
            detalles: detalles
        };
        
        console.log('📤 Enviando:', data);
        
        let btn = $(this).find('button[type="submit"]');
        let originalText = btn.html();
        btn.prop('disabled', true);
        btn.html('<i class="fas fa-spinner fa-spin me-2"></i> Guardando...');
        guardando = true;
        
        $.ajax({
            url: API_URL,
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            xhrFields: { withCredentials: true },
            timeout: 30000,
            success: function(response) {
                console.log('📥 Respuesta:', response);
                btn.prop('disabled', false);
                btn.html(originalText);
                guardando = false;
                if(response.success) {
                    alert('✅ Compra guardada correctamente');
                    location.reload();
                } else {
                    mostrarAlertasCompra([{
                        campo: 'servidor',
                        mensaje: response.message || '❌ Error en el servidor',
                        detalle: response.detalle || 'Ocurrió un error.',
                        sugerencia: 'Contacta al administrador.'
                    }]);
                }
            },
            error: function(xhr) {
                console.error('❌ Error:', xhr);
                btn.prop('disabled', false);
                btn.html(originalText);
                guardando = false;
                let msg = '❌ Error de comunicación';
                let det = 'No se pudo conectar con el servidor.';
                try {
                    let res = JSON.parse(xhr.responseText);
                    if(res.message) msg = res.message;
                    if(res.detalle) det = res.detalle;
                } catch(e) {
                    if(xhr.responseText) det = xhr.responseText.substring(0, 200);
                }
                mostrarAlertasCompra([{ campo: 'servidor', mensaje: msg, detalle: det, sugerencia: 'Verifica tu conexión.' }]);
            }
        });
    });
    
    // =============================================
    // PROVEEDOR
    // =============================================
    window.abrirNuevoProveedor = function() {
        $('#nuevo_proveedor_nombre, #nuevo_proveedor_apellidos, #nuevo_proveedor_telefono, #nuevo_proveedor_email, #nuevo_proveedor_area').val('');
        $('#modalNuevoProveedor').modal('show');
    };
    
    window.guardarNuevoProveedor = function() {
        let nombre = $('#nuevo_proveedor_nombre').val().trim();
        if(nombre === '') { alert('⚠️ El nombre es obligatorio'); return; }
        let data = {
            nombre: nombre,
            apellidos: $('#nuevo_proveedor_apellidos').val().trim() || '',
            telefono: $('#nuevo_proveedor_telefono').val().trim() || '',
            email: $('#nuevo_proveedor_email').val().trim() || '',
            area_trabajo: $('#nuevo_proveedor_area').val().trim() || '',
            es_proveedor: 1,
            tipo_proveedor: 'distribuidor'
        };
        let btn = document.querySelector('#modalNuevoProveedor .btn-primary');
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
                    alert('✅ Proveedor creado');
                    $('#modalNuevoProveedor').modal('hide');
                    let select = document.getElementById('proveedor_id');
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
                alert('❌ Error al guardar el proveedor');
            }
        });
    };
    
    // =============================================
    // NUEVO PRODUCTO DESDE COMPRAS
    // =============================================
    window.abrirNuevoProducto = function() {
        $('#nuevo_producto_nombre').val('');
        $('#nuevo_producto_descripcion').val('');
        $('#nuevo_producto_categoria').val('');
        $('#nuevo_producto_precio_compra').val('0.00');
        $('#nuevo_producto_precio_venta').val('0.00');
        $('#nuevo_producto_stock').val('0');
        $('#nuevo_producto_unidad_base').val('g');
        $('#nuevo_producto_unidad_compra').val('unidad');
        $('#nuevo_producto_contenido').val('1.00');
        $('#nuevo_producto_es_ingrediente').val('1');
        $('#modalNuevoProductoCompra').modal('show');
    };
    
    window.guardarNuevoProductoCompra = function() {
        let nombre = $('#nuevo_producto_nombre').val().trim();
        if(nombre === '') {
            alert('⚠️ El nombre del producto es obligatorio');
            return;
        }
        
        let data = {
            nombre: nombre,
            descripcion: $('#nuevo_producto_descripcion').val().trim() || '',
            categoria_id: $('#nuevo_producto_categoria').val() || null,
            precio_compra: parseFloat($('#nuevo_producto_precio_compra').val()) || 0,
            precio_venta: parseFloat($('#nuevo_producto_precio_venta').val()) || 0,
            stock: parseInt($('#nuevo_producto_stock').val()) || 0,
            tipo_producto: 'simple',
            es_ingrediente: parseInt($('#nuevo_producto_es_ingrediente').val()) || 1,
            es_producto_final: 1,
            unidad_base: $('#nuevo_producto_unidad_base').val() || 'g',
            unidad_compra: $('#nuevo_producto_unidad_compra').val() || 'unidad',
            contenido_unidad: parseFloat($('#nuevo_producto_contenido').val()) || 1.00
        };
        
        let btn = document.querySelector('#modalNuevoProductoCompra .btn-primary');
        let originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Guardando...';
        btn.disabled = true;
        
        $.ajax({
            url: API_PRODUCTOS,
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            xhrFields: { withCredentials: true },
            success: function(response) {
                btn.innerHTML = originalText;
                btn.disabled = false;
                
                if(response.success) {
                    alert('✅ Producto creado correctamente');
                    $('#modalNuevoProductoCompra').modal('hide');
                    
                    productosDisponibles.push({
                        id: response.id,
                        nombre: nombre,
                        precio_compra: data.precio_compra,
                        stock: data.stock,
                        unidad_base: data.unidad_base,
                        unidad_compra: data.unidad_compra
                    });
                    
                    $('.producto-select').each(function() {
                        let option = document.createElement('option');
                        option.value = response.id;
                        option.textContent = nombre + ' (Stock: ' + data.stock + ')';
                        option.dataset.precio = data.precio_compra;
                        option.dataset.stock = data.stock;
                        option.dataset.unidadBase = data.unidad_base;
                        option.dataset.unidadCompra = data.unidad_compra;
                        this.appendChild(option);
                    });
                } else {
                    alert('❌ Error: ' + response.message);
                }
            },
            error: function() {
                btn.innerHTML = originalText;
                btn.disabled = false;
                alert('❌ Error al guardar el producto');
            }
        });
    };
    
    // =============================================
    // VER DETALLE DE COMPRA
    // =============================================
    $(document).on('click', '.btn-ver', function() {
        let id = $(this).data('id');
        
        $('#modalVerCompra').modal('show');
        $('#detalleCompra').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div><p>Cargando detalles...</p></div>');
        $('#detalle_modal_footer').html('');
        $('#detalle_compra_id').text(id);
        
        $.ajax({
            url: API_URL + '?id=' + id,
            method: 'GET',
            xhrFields: { withCredentials: true },
            success: function(response) {
                if(response.success) {
                    let c = response.data;
                    let total = parseFloat(c.total || 0);
                    let pagado = 0;
                    let estado = c.estado || 'pendiente';
                    
                    if(c.pagos && c.pagos.length > 0) {
                        c.pagos.forEach(p => {
                            pagado += parseFloat(p.monto_pagado || 0);
                        });
                    }
                    
                    let pendiente = total - pagado;
                    let porcentaje = total > 0 ? (pagado / total) * 100 : 0;
                    let estaPagada = pendiente <= 0 || estado === 'pagada';
                    
                    let historialHtml = '';
                    if(c.historial && c.historial.length > 0) {
                        historialHtml = `
                            <hr>
                            <h6 class="fw-bold">📝 Historial de Ediciones</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Usuario</th>
                                            <th>Motivo</th>
                                            <th>Cambios</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${c.historial.map(h => {
                                            let cambios = [];
                                            if(h.datos_anteriores_decoded && h.datos_nuevos_decoded) {
                                                let oldData = h.datos_anteriores_decoded;
                                                let newData = h.datos_nuevos_decoded;
                                                if(oldData.compra && newData.compra) {
                                                    if(oldData.compra.proveedor_id != newData.compra.proveedor_id) cambios.push('Proveedor');
                                                    if(oldData.compra.total != newData.compra.total) cambios.push('Total');
                                                    if(oldData.compra.tipo_compra != newData.compra.tipo_compra) cambios.push('Tipo de compra');
                                                }
                                                if(oldData.detalles && newData.detalles) {
                                                    if(oldData.detalles.length != newData.detalles.length) {
                                                        cambios.push('Productos (' + oldData.detalles.length + ' → ' + newData.detalles.length + ')');
                                                    }
                                                }
                                            }
                                            if(cambios.length === 0) cambios.push('Datos actualizados');
                                            return `
                                                <tr>
                                                    <td>${new Date(h.fecha_edicion).toLocaleString()}</td>
                                                    <td>${h.usuario_nombre || 'Sistema'}</td>
                                                    <td>${h.motivo || 'Sin motivo'}</td>
                                                    <td><span class="badge bg-warning text-dark">${cambios.join(', ')}</span></td>
                                                </tr>
                                            `;
                                        }).join('')}
                                    </tbody>
                                </table>
                            </div>
                        `;
                    }
                    
                    let html = `
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="fw-bold">Proveedor</h6>
                                <p>${c.proveedor_nombre || 'N/A'}</p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold">Factura</h6>
                                <p>${c.numero_factura || 'N/A'}</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <h6 class="fw-bold">Fecha</h6>
                                <p>${new Date(c.fecha).toLocaleString()}</p>
                            </div>
                            <div class="col-md-4">
                                <h6 class="fw-bold">Estado</h6>
                                <p>
                                    <span class="badge bg-${estado === 'pagada' ? 'success' : estado === 'parcial' ? 'info' : 'warning'}">
                                        ${estado.toUpperCase()}
                                    </span>
                                    ${c.editado_por ? ' <span class="badge bg-warning text-dark"><i class="fas fa-edit"></i> Editada</span>' : ''}
                                </p>
                            </div>
                            <div class="col-md-4">
                                <h6 class="fw-bold">Total</h6>
                                <h4 class="text-success">$${total.toFixed(2)}</h4>
                            </div>
                        </div>
                        
                        <div class="card bg-light mt-2 mb-3">
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <span class="text-secondary">Total</span>
                                        <h5 class="text-primary">$${total.toFixed(2)}</h5>
                                    </div>
                                    <div class="col-4">
                                        <span class="text-secondary">Pagado</span>
                                        <h5 class="text-success">$${pagado.toFixed(2)}</h5>
                                    </div>
                                    <div class="col-4">
                                        <span class="text-secondary">Pendiente</span>
                                        <h5 class="text-danger">$${pendiente.toFixed(2)}</h5>
                                    </div>
                                </div>
                                <div class="row text-center mt-2">
                                    <div class="col-12">
                                        <div class="progress" style="height:10px;">
                                            <div class="progress-bar bg-success" style="width:${porcentaje}%;"></div>
                                        </div>
                                        <small class="text-muted">${porcentaje.toFixed(0)}% pagado</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        <h6 class="fw-bold">📦 Productos</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Unidad</th>
                                        <th>Precio</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${c.detalles && c.detalles.length > 0 ? c.detalles.map(d => `
                                        <tr>
                                            <td>${d.producto_nombre || 'Producto'}</td>
                                            <td>${d.cantidad}</td>
                                            <td>${d.unidad_compra || 'unidad'}</td>
                                            <td>$${parseFloat(d.precio_unitario).toFixed(2)}</td>
                                            <td>$${parseFloat(d.subtotal).toFixed(2)}</td>
                                        </tr>
                                    `).join('') : '<tr><td colspan="5" class="text-center">Sin productos</td></tr>'}
                                </tbody>
                                <tfoot>
                                    <tr class="table-success">
                                        <td colspan="5" class="text-end fw-bold">Total:</td>
                                        <td class="text-end fw-bold">$${total.toFixed(2)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        ${c.observaciones ? `<hr><h6 class="fw-bold">📝 Observaciones</h6><p>${c.observaciones}</p>` : ''}
                        ${historialHtml}
                    `;
                    
                    $('#detalleCompra').html(html);
                    
                    let footerHtml = '';
                    
                    if(!estaPagada) {
                        footerHtml += `
                            <button class="btn btn-success" onclick="abrirPagoDesdeDetalle(${c.id})">
                                <i class="fas fa-money-bill-wave me-2"></i> Registrar Pago
                            </button>
                            <button class="btn btn-success" onclick="pagarTodo(${c.id})">
                                <i class="fas fa-check-circle me-2"></i> Pagar Todo ($${pendiente.toFixed(2)})
                            </button>
                        `;
                    } else {
                        footerHtml += `
                            <button class="btn btn-secondary" disabled>
                                <i class="fas fa-check-circle me-2"></i> Compra Pagada
                            </button>
                        `;
                    }
                    
                    footerHtml += `
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i> Cerrar
                        </button>
                    `;
                    
                    $('#detalle_modal_footer').html(footerHtml);
                }
            }
        });
    });
    
    // =============================================
    // ELIMINAR COMPRA
    // =============================================
    $(document).on('click', '.btn-eliminar', function() {
        let id = $(this).data('id');
        let proveedor = $(this).closest('tr').find('td:eq(1)').text().trim();
        if(confirm('⚠️ ¿Eliminar compra de "' + proveedor + '"?\n📌 Se moverá a la PAPELERA.\n📌 Stock ajustado automáticamente.')) {
            $.ajax({
                url: API_URL,
                method: 'DELETE',
                data: JSON.stringify({ id: id }),
                contentType: 'application/json',
                xhrFields: { withCredentials: true },
                success: function(response) {
                    if(response.success) { alert('✅ ' + response.message); location.reload(); }
                    else { alert('❌ Error: ' + response.message); }
                }
            });
        }
    });
    
    // =============================================
    // RESETEAR MODAL
    // =============================================
    $('#modalCompra').on('hidden.bs.modal', function() {
        $('#formCompra')[0].reset();
        $('#compra_id').val('');
        $('#campo_factura').hide();
        $('#detallesCompra').html(`
            <tr><td colspan="6" class="text-center text-secondary py-3">
                <i class="fas fa-plus-circle me-2"></i> Haz clic en "Agregar Producto"
            </td></tr>
        `);
        $('#total_compra').text('$0.00');
        contadorProducto = 0;
    });
    
    // =============================================
    // SECCIÓN DE EDICIÓN
    // =============================================
    $(document).on('click', '.btn-editar-compra', function() {
        let id = $(this).data('id');
        $('#detallesEditar').html(`
            <tr><td colspan="6" class="text-center text-secondary py-3">
                <i class="fas fa-spinner fa-spin me-2"></i> Cargando...
            </td></tr>
        `);
        $('#editar_total').text('$0.00');
        $('#editar_motivo').val('');
        $('#editar_alerta_pagada').addClass('d-none');
        
        $.ajax({
            url: API_URL + '?id=' + id,
            method: 'GET',
            xhrFields: { withCredentials: true },
            success: function(response) {
                if(response.success) {
                    let compra = response.data;
                    $('#editar_compra_numero').text(compra.id);
                    $('#editar_compra_id').val(compra.id);
                    $('#editar_proveedor_id').val(compra.proveedor_id || '');
                    $('#editar_tipo_compra').val(compra.tipo_compra || 'minorista');
                    $('#editar_numero_factura').val(compra.numero_factura || '');
                    $('#editar_fecha').val(compra.fecha ? compra.fecha.replace(' ', 'T').slice(0, 16) : '');
                    $('#editar_fecha_entrega').val(compra.fecha_entrega || '');
                    if(compra.estado === 'pagada') { $('#editar_alerta_pagada').removeClass('d-none'); }
                    
                    cargarProveedoresEditar(compra.proveedor_id);
                    
                    if(compra.detalles && compra.detalles.length > 0) {
                        $('#detallesEditar').empty();
                        compra.detalles.forEach(function(detalle) { agregarFilaEditar(detalle); });
                        recalcularTotalEditar();
                    } else {
                        $('#detallesEditar').html(`
                            <tr><td colspan="6" class="text-center text-secondary py-3">
                                <i class="fas fa-plus-circle me-2"></i> Sin productos
                            </td></tr>
                        `);
                    }
                    $('#modalEditarCompra').modal('show');
                }
            }
        });
    });
    
    function cargarProveedoresEditar(selectedId) {
        $.ajax({
            url: API_CLIENTES,
            method: 'GET',
            xhrFields: { withCredentials: true },
            success: function(response) {
                if(response.success) {
                    let select = $('#editar_proveedor_id');
                    select.empty();
                    select.append('<option value="">Seleccionar proveedor</option>');
                    response.data.forEach(function(p) {
                        let opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = p.nombre + ' ' + (p.apellidos || '');
                        if(p.id == selectedId) { opt.selected = true; }
                        select.append(opt);
                    });
                }
            }
        });
    }
    
    function agregarFilaEditar(detalle) {
        let id = 'edit_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);
        let html = `
            <tr id="editar_producto_${id}">
                <td>
                    <select class="form-select form-select-sm producto-select-editar" data-id="${id}" required>
                        <option value="">Seleccionar producto...</option>
                    </select>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm cantidad-editar" data-id="${id}" value="${detalle.cantidad || 1}" min="1" required>
                </td>
                <td>
                    <select class="form-select form-select-sm unidad-select-editar" data-id="${id}" required>
                        <option value="unidad">Unidad</option>
                        <option value="g">Gramos (g)</option>
                        <option value="kg">Kilogramos (kg)</option>
                        <option value="lb">Libras (lb)</option>
                        <option value="oz">Onzas (oz)</option>
                        <option value="ml">Mililitros (ml)</option>
                        <option value="litro">Litros</option>
                        <option value="paquete">Paquete</option>
                    </select>
                </td>
                <td>
                    <input type="number" step="0.01" class="form-control form-control-sm precio-editar" data-id="${id}" value="${detalle.precio_unitario || 0}" required>
                </td>
                <td class="text-end subtotal-editar" data-id="${id}">$${parseFloat((detalle.cantidad || 0) * (detalle.precio_unitario || 0)).toFixed(2)}</td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="eliminarProductoEditar('${id}')">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
        if($('#detallesEditar tr').length === 1 && $('#detallesEditar tr td').attr('colspan')) { $('#detallesEditar').empty(); }
        $('#detallesEditar').append(html);
        let select = $(`#editar_producto_${id} .producto-select-editar`);
        cargarProductosEnSelect(select, detalle.producto_id);
    }
    
    function cargarProductosEnSelect(select, selectedId) {
        let id = select.data('id');
        $.ajax({
            url: API_PRODUCTOS,
            method: 'GET',
            xhrFields: { withCredentials: true },
            success: function(response) {
                if(response.success) {
                    select.empty();
                    select.append('<option value="">Seleccionar producto</option>');
                    response.data.forEach(function(p) {
                        let opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = p.nombre + ' (Stock: ' + (p.stock || 0) + ')';
                        opt.dataset.precio = p.precio_compra || 0;
                        if(p.id == selectedId) { opt.selected = true; }
                        select.append(opt);
                    });
                    if(selectedId) {
                        let precio = parseFloat(select.find(':selected').data('precio')) || 0;
                        $(`#editar_producto_${id} .precio-editar`).val(precio.toFixed(2));
                        recalcularTotalEditar();
                    }
                }
            }
        });
    }
    
    window.agregarProductoEditar = function() {
        let id = 'edit_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);
        let html = `
            <tr id="editar_producto_${id}">
                <td>
                    <select class="form-select form-select-sm producto-select-editar" data-id="${id}" required>
                        <option value="">Seleccionar producto...</option>
                    </select>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm cantidad-editar" data-id="${id}" value="1" min="1" required>
                </td>
                <td>
                    <select class="form-select form-select-sm unidad-select-editar" data-id="${id}" required>
                        <option value="unidad">Unidad</option>
                        <option value="g">Gramos (g)</option>
                        <option value="kg">Kilogramos (kg)</option>
                        <option value="lb">Libras (lb)</option>
                        <option value="oz">Onzas (oz)</option>
                        <option value="ml">Mililitros (ml)</option>
                        <option value="litro">Litros</option>
                        <option value="paquete">Paquete</option>
                    </select>
                </td>
                <td>
                    <input type="number" step="0.01" class="form-control form-control-sm precio-editar" data-id="${id}" value="0.00" required>
                </td>
                <td class="text-end subtotal-editar" data-id="${id}">$0.00</td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="eliminarProductoEditar('${id}')">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
        if($('#detallesEditar tr').length === 1 && $('#detallesEditar tr td').attr('colspan')) { $('#detallesEditar').empty(); }
        $('#detallesEditar').append(html);
        let select = $(`#editar_producto_${id} .producto-select-editar`);
        cargarProductosEnSelect(select, null);
        recalcularTotalEditar();
    };
    
    window.eliminarProductoEditar = function(id) {
        $(`#editar_producto_${id}`).remove();
        if($('#detallesEditar tr').length === 0) {
            $('#detallesEditar').html(`
                <tr><td colspan="6" class="text-center text-secondary py-3">
                    <i class="fas fa-plus-circle me-2"></i> Haz clic en "Agregar Producto"
                </td></tr>
            `);
        }
        recalcularTotalEditar();
    };
    
    function recalcularTotalEditar() {
        let total = 0;
        $('#detallesEditar tr').each(function() {
            let cantidad = parseFloat($(this).find('.cantidad-editar').val()) || 0;
            let precio = parseFloat($(this).find('.precio-editar').val()) || 0;
            let subtotal = cantidad * precio;
            $(this).find('.subtotal-editar').text('$' + subtotal.toFixed(2));
            total += subtotal;
        });
        $('#editar_total').text('$' + total.toFixed(2));
    }
    
    $(document).on('change', '.producto-select-editar', function() {
        let id = $(this).data('id');
        let precio = parseFloat($(this).find(':selected').data('precio')) || 0;
        $(`#editar_producto_${id} .precio-editar`).val(precio.toFixed(2));
        recalcularTotalEditar();
    });
    
    $(document).on('input', '.cantidad-editar, .precio-editar', function() {
        recalcularTotalEditar();
    });
    
    $('#formEditarCompra').off('submit').on('submit', function(e) {
        e.preventDefault();
        let compra_id = $('#editar_compra_id').val();
        let motivo = $('#editar_motivo').val().trim();
        if(!motivo) { alert('⚠️ Explica el motivo de la edición'); $('#editar_motivo').focus(); return; }
        
        let detalles = [];
        let valid = true;
        $('#detallesEditar tr').each(function() {
            let producto_id = $(this).find('.producto-select-editar').val();
            let cantidad = $(this).find('.cantidad-editar').val();
            let unidad = $(this).find('.unidad-select-editar').val();
            let precio_unitario = $(this).find('.precio-editar').val();
            if(producto_id && cantidad && precio_unitario) {
                detalles.push({
                    producto_id: parseInt(producto_id),
                    cantidad: parseInt(cantidad),
                    unidad: unidad,
                    precio_unitario: parseFloat(precio_unitario),
                    subtotal: parseInt(cantidad) * parseFloat(precio_unitario)
                });
            } else if($(this).find('.producto-select-editar').length > 0) { valid = false; }
        });
        if(!valid || detalles.length === 0) { alert('⚠️ Agregue al menos un producto'); return; }
        
        let data = {
            id: compra_id,
            proveedor_id: $('#editar_proveedor_id').val(),
            tipo_compra: $('#editar_tipo_compra').val(),
            numero_factura: $('#editar_numero_factura').val() || '',
            fecha: $('#editar_fecha').val(),
            fecha_entrega: $('#editar_fecha_entrega').val() || null,
            motivo: motivo,
            detalles: detalles
        };
        if(!data.proveedor_id) { alert('⚠️ Seleccione un proveedor'); return; }
        
        let btn = $(this).find('button[type="submit"]');
        let originalText = btn.html();
        btn.prop('disabled', true);
        btn.html('<i class="fas fa-spinner fa-spin me-2"></i> Guardando cambios...');
        
        $.ajax({
            url: API_URL,
            method: 'PUT',
            data: JSON.stringify(data),
            contentType: 'application/json',
            xhrFields: { withCredentials: true },
            success: function(response) {
                btn.prop('disabled', false);
                btn.html(originalText);
                if(response.success) {
                    alert('✅ Compra editada correctamente\n📌 Stock ajustado\n📌 Historial registrado');
                    $('#modalEditarCompra').modal('hide');
                    location.reload();
                } else { alert('❌ Error: ' + response.message); }
            },
            error: function(xhr) {
                btn.prop('disabled', false);
                btn.html(originalText);
                alert('❌ Error al guardar los cambios');
            }
        });
    });
    
    $('#modalEditarCompra').on('hidden.bs.modal', function() {
        $('#formEditarCompra')[0].reset();
        $('#editar_compra_id').val('');
        $('#detallesEditar').html(`
            <tr><td colspan="6" class="text-center text-secondary py-3">
                <i class="fas fa-plus-circle me-2"></i> Cargando...
            </td></tr>
        `);
        $('#editar_total').text('$0.00');
        $('#editar_motivo').val('');
        $('#editar_alerta_pagada').addClass('d-none');
    });
    
    // =============================================
    // SECCIÓN DE PAGO
    // =============================================
    $(document).on('click', '.btn-pagar', function() {
        let id = $(this).data('id');
        
        $('#pago_compra_id').val(id);
        $('#pago_monto').val('');
        $('#pago_metodo').val('efectivo');
        $('#pago_referencia').val('');
        $('#pago_observaciones').val('');
        $('#campo_tarjeta_pago').hide();
        $('#campo_plataforma_pago').hide();
        $('#mensaje_error_titulo').remove();
        $('#pago_monto').prop('disabled', false);
        $('#pago_monto').attr('placeholder', 'Ingresa el monto a pagar');
        $('#modalPago .btn-success').prop('disabled', false);
        $('#pago_pendiente').removeData('original');
        
        $.ajax({
            url: API_URL + '?id=' + id,
            method: 'GET',
            xhrFields: { withCredentials: true },
            success: function(response) {
                if(response.success) {
                    let compra = response.data;
                    let total = parseFloat(compra.total || 0);
                    let pagado = 0;
                    
                    if(compra.pagos && compra.pagos.length > 0) {
                        compra.pagos.forEach(p => {
                            pagado += parseFloat(p.monto_pagado || 0);
                        });
                    }
                    
                    let pendiente = total - pagado;
                    let porcentaje = total > 0 ? (pagado / total) * 100 : 0;
                    
                    $('#pago_total').text('$' + total.toFixed(2));
                    $('#pago_pendiente').text('$' + pendiente.toFixed(2));
                    $('#pago_pendiente').data('original', pendiente);
                    $('#pago_progress').css('width', porcentaje + '%');
                    $('#pago_porcentaje').text(porcentaje.toFixed(0) + '% pagado');
                    
                    if(pendiente <= 0) {
                        $('#pago_monto').prop('disabled', true);
                        $('#pago_monto').val('');
                        $('#pago_monto').attr('placeholder', '✅ Compra ya pagada');
                        $('#modalPago .btn-success').prop('disabled', true);
                        $('#pago_monto').attr('max', 0);
                    } else {
                        $('#pago_monto').prop('disabled', false);
                        $('#pago_monto').val(pendiente.toFixed(2));
                        $('#pago_monto').attr('max', pendiente);
                        $('#pago_monto').attr('placeholder', 'Máximo: $' + pendiente.toFixed(2));
                        $('#modalPago .btn-success').prop('disabled', false);
                    }
                    
                    $('#modalPago').modal('show');
                }
            },
            error: function(xhr) {
                mostrarError('❌ Error', 'No se pudieron cargar los datos de la compra', 'Intenta nuevamente');
            }
        });
    });
    
    $(document).on('input', '#pago_monto', function() {
        let monto = parseFloat($(this).val()) || 0;
        let pendienteOriginal = parseFloat($('#pago_pendiente').data('original')) || 0;
        let total = parseFloat($('#pago_total').text().replace('$', '').replace(',', '')) || 0;
        
        if($(this).val() === '') {
            let pagado = total - pendienteOriginal;
            let porcentaje = total > 0 ? (pagado / total) * 100 : 0;
            $('#pago_pendiente').text('$' + pendienteOriginal.toFixed(2));
            $('#pago_progress').css('width', porcentaje + '%');
            $('#pago_porcentaje').text(porcentaje.toFixed(0) + '% pagado');
            $('#pago_monto').attr('max', pendienteOriginal);
            $('#pago_monto').attr('placeholder', 'Máximo: $' + pendienteOriginal.toFixed(2));
            $('#pago_monto').prop('disabled', false);
            $('#modalPago .btn-success').prop('disabled', false);
            return;
        }
        
        if(monto === 0) {
            let pagado = total - pendienteOriginal;
            let porcentaje = total > 0 ? (pagado / total) * 100 : 0;
            $('#pago_pendiente').text('$' + pendienteOriginal.toFixed(2));
            $('#pago_progress').css('width', porcentaje + '%');
            $('#pago_porcentaje').text(porcentaje.toFixed(0) + '% pagado');
            $('#pago_monto').attr('max', pendienteOriginal);
            $('#pago_monto').attr('placeholder', 'Máximo: $' + pendienteOriginal.toFixed(2));
            $('#pago_monto').prop('disabled', false);
            $('#modalPago .btn-success').prop('disabled', false);
            return;
        }
        
        if(monto > pendienteOriginal) {
            $(this).val(pendienteOriginal.toFixed(2));
            monto = pendienteOriginal;
        }
        
        let nuevoPendiente = pendienteOriginal - monto;
        let pagado = total - nuevoPendiente;
        let porcentaje = total > 0 ? (pagado / total) * 100 : 0;
        
        $('#pago_pendiente').text('$' + nuevoPendiente.toFixed(2));
        $('#pago_progress').css('width', porcentaje + '%');
        $('#pago_porcentaje').text(porcentaje.toFixed(0) + '% pagado');
        
        if(nuevoPendiente > 0) {
            $('#pago_monto').attr('max', nuevoPendiente);
            $('#pago_monto').attr('placeholder', 'Máximo: $' + nuevoPendiente.toFixed(2));
            $('#pago_monto').prop('disabled', false);
            $('#modalPago .btn-success').prop('disabled', false);
        } else {
            $('#pago_monto').attr('max', 0);
            $('#pago_monto').attr('placeholder', '✅ Compra pagada');
            $('#pago_monto').prop('disabled', true);
            $('#modalPago .btn-success').prop('disabled', true);
        }
    });
    
    $(document).on('change', '#pago_metodo', function() {
        let val = $(this).val();
        $('#campo_tarjeta_pago').hide();
        $('#campo_plataforma_pago').hide();
        $('#mensaje_error_titulo').remove();
        if(val === 'transferencia') {
            $('#campo_tarjeta_pago').show();
        } else if(val === 'pago_linea') {
            $('#campo_tarjeta_pago').show();
            $('#campo_plataforma_pago').show();
        }
    });
    
    $('#modalPago').on('hidden.bs.modal', function() {
        $('#pago_pendiente').removeData('original');
        $('#pago_monto').prop('disabled', false);
        $('#pago_monto').val('');
        $('#modalPago .btn-success').prop('disabled', false);
        $('#mensaje_error_titulo').remove();
    });
    
    $('#formPago').off('submit').on('submit', function(e) {
        e.preventDefault();
        
        let compra_id = $('#pago_compra_id').val();
        let monto = parseFloat($('#pago_monto').val());
        let pendienteOriginal = parseFloat($('#pago_pendiente').data('original')) || 0;
        let metodo = $('#pago_metodo').val();
        let tarjeta_id = $('#pago_tarjeta_id').val();
        let plataforma = $('#pago_plataforma').val();
        let referencia = $('#pago_referencia').val().trim();
        let observaciones = $('#pago_observaciones').val().trim();
        
        if(!monto || monto <= 0) {
            mostrarError('❌ Monto inválido', 'El monto debe ser mayor a 0.', 'Ingresa un monto válido para continuar.');
            $('#pago_monto').focus();
            return;
        }
        
        if(monto > pendienteOriginal) {
            mostrarError('❌ Monto excede el saldo', 'Saldo pendiente: $' + pendienteOriginal.toFixed(2), 'El monto ingresado ($' + monto.toFixed(2) + ') excede el saldo disponible.');
            $('#pago_monto').focus();
            return;
        }
        
        if(metodo === 'pago_linea' && !plataforma) {
            mostrarError('❌ Plataforma requerida', 'Debes seleccionar una plataforma de pago.', 'Selecciona Transfermóvil o Enzona para continuar.');
            $('#pago_plataforma').focus();
            return;
        }
        
        if((metodo === 'transferencia' || metodo === 'pago_linea') && !tarjeta_id) {
            mostrarError('❌ Tarjeta requerida', 'Debes seleccionar una tarjeta de pago.', 'Selecciona una tarjeta registrada para continuar.');
            $('#pago_tarjeta_id').focus();
            return;
        }
        
        let data = {
            action: 'pagar',
            compra_id: compra_id,
            monto: monto,
            metodo_pago: metodo,
            tarjeta_id: tarjeta_id || null,
            plataforma: plataforma || null,
            referencia: referencia,
            observaciones: observaciones
        };
        
        let btn = $(this).find('button[type="submit"]');
        let originalText = btn.html();
        btn.prop('disabled', true);
        btn.html('<i class="fas fa-spinner fa-spin me-2"></i> Registrando...');
        
        $.ajax({
            url: API_URL,
            method: 'PATCH',
            data: JSON.stringify(data),
            contentType: 'application/json',
            xhrFields: { withCredentials: true },
            timeout: 30000,
            success: function(response) {
                btn.prop('disabled', false);
                btn.html(originalText);
                if(response.success) {
                    let mensaje = '✅ Pago registrado\n';
                    mensaje += 'Estado: ' + response.estado.toUpperCase() + '\n';
                    mensaje += 'Total pagado: $' + response.total_pagado.toFixed(2) + '\n';
                    mensaje += 'Pendiente: $' + response.pendiente.toFixed(2) + '\n';
                    mensaje += response.mensaje_pendiente || '';
                    alert(mensaje);
                    $('#modalPago').modal('hide');
                    location.reload();
                } else {
                    mostrarError(response.message || '❌ Error', response.detalle || 'Error al registrar el pago.', response.codigo ? 'Código: ' + response.codigo : '');
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false);
                btn.html(originalText);
                let msg = '❌ Error al registrar el pago';
                let det = 'No se pudo completar la operación.';
                try {
                    let res = JSON.parse(xhr.responseText);
                    if(res.message) msg = res.message;
                    if(res.detalle) det = res.detalle;
                } catch(e) { if(xhr.responseText) det = xhr.responseText; }
                mostrarError(msg, det, 'Error de comunicación con el servidor');
            }
        });
    });
});

// =============================================
// FUNCIONES GLOBALES (FUERA DEL DOCUMENT.READY)
// =============================================

// =============================================
// ABRIR PAGO DESDE DETALLE (MODAL ANIDADO)
// =============================================
window.abrirPagoDesdeDetalle = function(compraId) {
    console.log('🔵 Abriendo pago desde detalle para compra ID:', compraId);
    
    $('#pago_detalle_compra_id').val(compraId);
    $('#pago_detalle_monto').val('');
    $('#pago_detalle_metodo').val('efectivo');
    $('#pago_detalle_referencia').val('');
    $('#pago_detalle_observaciones').val('');
    $('#campo_detalle_tarjeta').hide();
    $('#campo_detalle_plataforma').hide();
    $('#pago_detalle_pendiente').removeData('original');
    $('#mensaje_error_detalle').remove();
    $('#pago_detalle_monto').prop('disabled', false);
    $('#formPagoDetalle button[type="submit"]').prop('disabled', false);
    
    $.ajax({
        url: API_URL + '?id=' + compraId,
        method: 'GET',
        xhrFields: { withCredentials: true },
        success: function(response) {
            if(response.success) {
                let compra = response.data;
                let total = parseFloat(compra.total || 0);
                let pagado = 0;
                
                if(compra.pagos && compra.pagos.length > 0) {
                    compra.pagos.forEach(p => {
                        pagado += parseFloat(p.monto_pagado || 0);
                    });
                }
                
                let pendiente = total - pagado;
                let porcentaje = total > 0 ? (pagado / total) * 100 : 0;
                
                $('#pago_detalle_total').text('$' + total.toFixed(2));
                $('#pago_detalle_pendiente').text('$' + pendiente.toFixed(2));
                $('#pago_detalle_pendiente').data('original', pendiente);
                $('#pago_detalle_progress').css('width', porcentaje + '%');
                $('#pago_detalle_porcentaje').text(porcentaje.toFixed(0) + '% pagado');
                
                if(pendiente <= 0) {
                    $('#pago_detalle_monto').prop('disabled', true);
                    $('#pago_detalle_monto').val('');
                    $('#pago_detalle_monto').attr('placeholder', '✅ Compra ya pagada');
                    $('#pago_detalle_monto').attr('max', 0);
                    $('#formPagoDetalle button[type="submit"]').prop('disabled', true);
                } else {
                    $('#pago_detalle_monto').prop('disabled', false);
                    $('#pago_detalle_monto').val(pendiente.toFixed(2));
                    $('#pago_detalle_monto').attr('max', pendiente);
                    $('#pago_detalle_monto').attr('placeholder', 'Máximo: $' + pendiente.toFixed(2));
                    $('#formPagoDetalle button[type="submit"]').prop('disabled', false);
                }
                
                $('#modalPagoDetalle').modal('show');
            }
        },
        error: function(xhr) {
            alert('❌ Error al cargar los datos de la compra');
        }
    });
};

// =============================================
// GUARDAR PAGO DESDE DETALLE
// =============================================
$('#formPagoDetalle').off('submit').on('submit', function(e) {
    e.preventDefault();
    
    let compra_id = $('#pago_detalle_compra_id').val();
    let monto = parseFloat($('#pago_detalle_monto').val());
    let pendienteOriginal = parseFloat($('#pago_detalle_pendiente').data('original')) || 0;
    let metodo = $('#pago_detalle_metodo').val();
    let tarjeta_id = $('#pago_detalle_tarjeta_id').val();
    let plataforma = $('#pago_detalle_plataforma').val();
    let referencia = $('#pago_detalle_referencia').val().trim();
    let observaciones = $('#pago_detalle_observaciones').val().trim();
    
    if(!monto || monto <= 0) {
        alert('⚠️ El monto debe ser mayor a 0');
        $('#pago_detalle_monto').focus();
        return;
    }
    
    if(monto > pendienteOriginal) {
        alert('⚠️ El monto excede el saldo pendiente ($' + pendienteOriginal.toFixed(2) + ')');
        $('#pago_detalle_monto').focus();
        return;
    }
    
    if(metodo === 'pago_linea' && !plataforma) {
        alert('⚠️ Selecciona una plataforma de pago');
        $('#pago_detalle_plataforma').focus();
        return;
    }
    
    if((metodo === 'transferencia' || metodo === 'pago_linea') && !tarjeta_id) {
        alert('⚠️ Selecciona una tarjeta de pago');
        $('#pago_detalle_tarjeta_id').focus();
        return;
    }
    
    let data = {
        action: 'pagar',
        compra_id: compra_id,
        monto: monto,
        metodo_pago: metodo,
        tarjeta_id: tarjeta_id || null,
        plataforma: plataforma || null,
        referencia: referencia,
        observaciones: observaciones
    };
    
    let btn = $(this).find('button[type="submit"]');
    let originalText = btn.html();
    btn.prop('disabled', true);
    btn.html('<i class="fas fa-spinner fa-spin me-2"></i> Registrando...');
    
    $.ajax({
        url: API_URL,
        method: 'PATCH',
        data: JSON.stringify(data),
        contentType: 'application/json',
        xhrFields: { withCredentials: true },
        timeout: 30000,
        success: function(response) {
            btn.prop('disabled', false);
            btn.html(originalText);
            
            if(response.success) {
                alert('✅ Pago registrado\nEstado: ' + response.estado.toUpperCase() + '\nPagado: $' + response.total_pagado.toFixed(2) + '\nPendiente: $' + response.pendiente.toFixed(2));
                $('#modalPagoDetalle').modal('hide');
                let compraId = $('#pago_detalle_compra_id').val();
                $('.btn-ver[data-id="' + compraId + '"]').click();
            } else {
                alert('❌ Error: ' + response.message);
            }
        },
        error: function(xhr) {
            btn.prop('disabled', false);
            btn.html(originalText);
            alert('❌ Error al registrar el pago');
        }
    });
});

// =============================================
// Mostrar/ocultar campos según método de pago (detalle)
// =============================================
$(document).on('change', '#pago_detalle_metodo', function() {
    let val = $(this).val();
    $('#campo_detalle_tarjeta').hide();
    $('#campo_detalle_plataforma').hide();
    if(val === 'transferencia') {
        $('#campo_detalle_tarjeta').show();
    } else if(val === 'pago_linea') {
        $('#campo_detalle_tarjeta').show();
        $('#campo_detalle_plataforma').show();
    }
});

// =============================================
// Actualizar pendiente al cambiar monto (detalle)
// =============================================
$(document).on('input', '#pago_detalle_monto', function() {
    let monto = parseFloat($(this).val()) || 0;
    let pendienteOriginal = parseFloat($('#pago_detalle_pendiente').data('original')) || 0;
    let total = parseFloat($('#pago_detalle_total').text().replace('$', '')) || 0;
    
    if($(this).val() === '') {
        let pagado = total - pendienteOriginal;
        let porcentaje = total > 0 ? (pagado / total) * 100 : 0;
        $('#pago_detalle_pendiente').text('$' + pendienteOriginal.toFixed(2));
        $('#pago_detalle_progress').css('width', porcentaje + '%');
        $('#pago_detalle_porcentaje').text(porcentaje.toFixed(0) + '% pagado');
        return;
    }
    
    if(monto > pendienteOriginal) {
        $(this).val(pendienteOriginal.toFixed(2));
        monto = pendienteOriginal;
    }
    
    let nuevoPendiente = pendienteOriginal - monto;
    let pagado = total - nuevoPendiente;
    let porcentaje = total > 0 ? (pagado / total) * 100 : 0;
    
    $('#pago_detalle_pendiente').text('$' + nuevoPendiente.toFixed(2));
    $('#pago_detalle_progress').css('width', porcentaje + '%');
    $('#pago_detalle_porcentaje').text(porcentaje.toFixed(0) + '% pagado');
});

// =============================================
// PAGAR TODO DESDE EL DETALLE
// =============================================
window.pagarTodo = function(compraId) {
    console.log('🔵 Pagar todo para compra ID:', compraId);
    
    if(!compraId) {
        alert('❌ Error: ID de compra no válido');
        return;
    }
    
    $('#confirmar_pago_compra_id').val(compraId);
    $('#modalConfirmarPagoTotal').modal('show');
};

// =============================================
// EJECUTAR PAGO TOTAL (después de confirmar)
// =============================================
$(document).on('click', '#btnConfirmarPagoTotal', function() {
    console.log('🔵 Confirmado, ejecutando pago total...');
    
    let compraId = $('#confirmar_pago_compra_id').val();
    let btn = $(this);
    let originalText = btn.html();
    
    btn.prop('disabled', true);
    btn.html('<i class="fas fa-spinner fa-spin me-2"></i> Procesando...');
    
    $('#modalConfirmarPagoTotal').modal('hide');
    
    const url = '/mi-negocio-web/api/compras.php';
    
    $.ajax({
        url: url + '?id=' + compraId,
        method: 'GET',
        xhrFields: { withCredentials: true },
        success: function(response) {
            if(response.success) {
                let compra = response.data;
                let total = parseFloat(compra.total || 0);
                let pagado = 0;
                
                if(compra.pagos && compra.pagos.length > 0) {
                    compra.pagos.forEach(p => {
                        pagado += parseFloat(p.monto_pagado || 0);
                    });
                }
                
                let pendiente = total - pagado;
                
                if(pendiente <= 0) {
                    alert('✅ Esta compra ya está completamente pagada');
                    btn.prop('disabled', false);
                    btn.html(originalText);
                    return;
                }
                
                let btnDetalle = document.querySelector('#detalle_modal_footer .btn-success:last-child');
                if(btnDetalle) {
                    btnDetalle.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Procesando...';
                    btnDetalle.disabled = true;
                }
                
                let data = {
                    action: 'pagar',
                    compra_id: compraId,
                    monto: pendiente,
                    metodo_pago: 'efectivo',
                    tarjeta_id: null,
                    plataforma: null,
                    referencia: 'Pago total desde detalle',
                    observaciones: 'Pago único por el saldo completo'
                };
                
                $.ajax({
                    url: url,
                    method: 'PATCH',
                    data: JSON.stringify(data),
                    contentType: 'application/json',
                    xhrFields: { withCredentials: true },
                    timeout: 30000,
                    success: function(response) {
                        btn.prop('disabled', false);
                        btn.html(originalText);
                        
                        if(btnDetalle) {
                            btnDetalle.innerHTML = '<i class="fas fa-check-circle me-2"></i> Pagar Todo ($' + pendiente.toFixed(2) + ')';
                            btnDetalle.disabled = false;
                        }
                        
                        if(response.success) {
                            alert('✅ Pago total registrado correctamente\n' +
                                  'Total pagado: $' + response.total_pagado.toFixed(2) + '\n' +
                                  'Estado: ' + response.estado.toUpperCase());
                            $('#modalVerCompra').modal('hide');
                            location.reload();
                        } else {
                            alert('❌ Error: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        btn.prop('disabled', false);
                        btn.html(originalText);
                        
                        if(btnDetalle) {
                            btnDetalle.innerHTML = '<i class="fas fa-check-circle me-2"></i> Pagar Todo ($' + pendiente.toFixed(2) + ')';
                            btnDetalle.disabled = false;
                        }
                        
                        alert('❌ Error al procesar el pago');
                    }
                });
            }
        },
        error: function(xhr) {
            btn.prop('disabled', false);
            btn.html(originalText);
            
            let btnDetalle = document.querySelector('#detalle_modal_footer .btn-success:last-child');
            if(btnDetalle) {
                btnDetalle.innerHTML = '<i class="fas fa-check-circle me-2"></i> Pagar Todo ($' + pendiente.toFixed(2) + ')';
                btnDetalle.disabled = false;
            }
            
            alert('❌ Error al obtener los datos de la compra');
        }
    });
});

console.log('✅ compras.js cargado correctamente');