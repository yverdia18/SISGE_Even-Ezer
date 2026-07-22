// ============================================================
// PRODUCTOS.JS - VERSIÓN ACTUALIZADA CON RECETAS
// ============================================================

// ============================================================
// URLS ABSOLUTAS - GLOBALES
// ============================================================
const API_URL = '/mi-negocio-web/api/productos.php';
const API_CATEGORIAS = '/mi-negocio-web/api/categorias.php';
const API_RECETAS = '/mi-negocio-web/api/recetas.php';

// ============================================================
// ESPERAR A QUE JQUERY ESTÉ CARGADO
// ============================================================
$(document).ready(function() {
    console.log('✅ productos.js cargado correctamente');
    
    // ============================================================
    // VARIABLES
    // ============================================================
    let guardandoProducto = false;
    let recetaCargada = null;
    let esProductoConReceta = false;
    
    // ============================================================
    // FUNCIONES DE CONVERSIÓN
    // ============================================================
    function actualizarContenidoUnidad() {
        let unidadBase = $('#unidad_base').val();
        let unidadCompra = $('#unidad_compra').val();
        let contenido = $('#contenido_unidad').val() || 1.00;
        
        $('#contenido_unidad_label').text(unidadBase);
        $('#contenido_unidad_desc').text('1 ' + unidadCompra + ' = ' + contenido + ' ' + unidadBase);
    }
    
    // ============================================================
    // MOSTRAR/OCULTAR SECCIÓN DE COMPOSICIÓN
    // ============================================================
    $(document).on('change', '#tipo_producto', function() {
        if($(this).val() === 'compuesto') {
            $('#seccion_composicion').show();
            $('#campos_precio_stock').hide();
            $('#es_ingrediente').val(0);
            $('#es_producto_final').val(1);
            
            // Si tiene receta, cargar composición
            const productoId = $('#producto_id').val();
            if(productoId) {
                cargarComposicionDesdeReceta(productoId);
            } else {
                $('#detallesComposicion').html(`
                    <tr><td colspan="6" class="text-center text-secondary py-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Guarda el producto primero, luego asígnale una receta.
                    </td></tr>
                `);
                $('#pie_composicion').hide();
            }
        } else {
            $('#seccion_composicion').hide();
            $('#campos_precio_stock').show();
            $('#es_ingrediente').val(1);
            $('#es_producto_final').val(1);
        }
    });
    
    // ============================================================
    // CARGAR COMPOSICIÓN DESDE RECETA
    // ============================================================
    function cargarComposicionDesdeReceta(productoId) {
        console.log('🔍 Buscando receta para producto ID:', productoId);
        
        $('#detallesComposicion').html(`
            <tr><td colspan="6" class="text-center text-secondary py-3">
                <i class="fas fa-spinner fa-spin me-2"></i> Cargando receta...
            </td></tr>
        `);
        $('#pie_composicion').hide();
        
        $.ajax({
            url: API_RECETAS + '?producto_id=' + productoId,
            method: 'GET',
            xhrFields: { withCredentials: true },
            success: function(response) {
                console.log('📦 Respuesta receta:', response);
                
                // Si la respuesta tiene success y data, extraer
                let data = response.data || response;
                
                // Si no hay receta o es un array vacío
                if(!data || data.length === 0 || (Array.isArray(data) && data.length === 0)) {
                    $('#detallesComposicion').html(`
                        <tr><td colspan="6" class="text-center text-warning py-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No hay receta asociada a este producto.
                        </td></tr>
                    `);
                    $('#pie_composicion').hide();
                    return;
                }
                
                // Si es un array, buscar la receta que coincida con el producto
                let receta = null;
                if(Array.isArray(data)) {
                    receta = data.find(r => r.producto_id == productoId) || data[0];
                } else {
                    receta = data;
                }
                
                if(!receta || !receta.id) {
                    $('#detallesComposicion').html(`
                        <tr><td colspan="6" class="text-center text-warning py-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No se encontró receta para este producto.
                        </td></tr>
                    `);
                    $('#pie_composicion').hide();
                    return;
                }
                
                recetaCargada = receta;
                esProductoConReceta = true;
                
                console.log('📦 Receta encontrada:', receta);
                
                // Mostrar datos de la receta
                $('#nombre_receta_asociada').text(receta.nombre || 'Sin nombre');
                $('#rendimiento_receta').text((receta.rendimiento || 1) + ' unidades');
                $('#unidad_receta').text(receta.unidad_medida || 'racion');
                
                // Mostrar ingredientes
                let html = '';
                if(receta.ingredientes && receta.ingredientes.length > 0) {
                    let contador = 0;
                    receta.ingredientes.forEach(function(ing) {
                        contador++;
                        const costoUnitario = parseFloat(ing.costo_porcion || 0);
                        const cantidad = parseFloat(ing.cantidad || 0);
                        const costoTotal = costoUnitario * cantidad;
                        
                        html += '<tr>';
                        html += '<td class="text-center">' + contador + '</td>';
                        html += '<td>' + (ing.ingrediente_nombre || 'Sin nombre') + '</td>';
                        html += '<td class="text-end">' + cantidad.toFixed(2) + '</td>';
                        html += '<td>' + (ing.unidad || 'g') + '</td>';
                        html += '<td class="text-end">$' + costoUnitario.toFixed(4) + '</td>';
                        html += '<td class="text-end">$' + costoTotal.toFixed(2) + '</td>';
                        html += '</tr>';
                    });
                } else {
                    html = '<tr><td colspan="6" class="text-center text-muted">Esta receta no tiene ingredientes</td></tr>';
                }
                
                $('#detallesComposicion').html(html);
                $('#pie_composicion').show();
                
                // Mostrar costos
                const costoProduccion = parseFloat(receta.costo_total_produccion || 0);
                const precioSugerido = parseFloat(receta.precio_venta_sugerido || 0);
                
                $('#costo_produccion_total').text('$' + costoProduccion.toFixed(2));
                $('#precio_produccion_sugerido').text('$' + precioSugerido.toFixed(2));
                
                // ============================================================
                // ✅ BLOQUEAR CAMPOS Y ACTUALIZAR PRECIOS
                // ============================================================
                
                // Bloquear tipo de producto
                $('#tipo_producto').val('compuesto');
                $('#tipo_producto').prop('disabled', true);
                $('#tipo_bloqueado_msg').show();
                
                // Bloquear y actualizar precios
                $('#precio_compra').val(costoProduccion.toFixed(2));
                $('#precio_compra').prop('disabled', true);
                $('#precio_compra').addClass('campo-bloqueado');
                $('#precio_compra_origen').text('💰 Desde receta: $' + costoProduccion.toFixed(2));
                
                $('#precio_venta').val(precioSugerido.toFixed(2));
                $('#precio_venta').prop('disabled', true);
                $('#precio_venta').addClass('campo-bloqueado');
                $('#precio_venta_origen').text('🏷️ Desde receta: $' + precioSugerido.toFixed(2));
                
                // Bloquear es_ingrediente y es_producto_final
                $('#es_ingrediente').val(0);
                $('#es_ingrediente').prop('disabled', true);
                $('#es_producto_final').val(1);
                $('#es_producto_final').prop('disabled', true);
                
                // Guardar que tiene receta
                $('#seccion_composicion').show();
                $('#campos_precio_stock').hide();
                
            },
            error: function(xhr) {
                console.error('❌ Error al cargar receta:', xhr);
                $('#detallesComposicion').html(`
                    <tr><td colspan="6" class="text-center text-danger py-3">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Error al cargar la receta.
                    </td></tr>
                `);
                $('#pie_composicion').hide();
            }
        });
    }
    
    // ============================================================
    // VER RECETA (desde el botón en la tabla)
    // ============================================================
    $(document).on('click', '.btn-ver-receta', function() {
        let id = $(this).data('id');
        $('#modalVerReceta').modal('show');
        $('#detalleReceta').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div><p>Cargando...</p></div>');
        
        $.ajax({
            url: API_RECETAS + '?producto_id=' + id,
            method: 'GET',
            xhrFields: { withCredentials: true },
            success: function(response) {
                let data = response.data || response;
                let receta = Array.isArray(data) ? data[0] : data;
                
                if(!receta || !receta.id) {
                    $('#detalleReceta').html(`
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No hay receta asociada a este producto.
                        </div>
                    `);
                    return;
                }
                
                let html = `
                    <h5 class="fw-bold">${receta.nombre || 'Sin nombre'}</h5>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <small class="text-muted d-block">Rendimiento</small>
                            <strong>${receta.rendimiento || 1} unidades</strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Unidad</small>
                            <strong>${receta.unidad_medida || 'racion'}</strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Costo Unitario</small>
                            <strong class="text-success">$${parseFloat(receta.costo_unitario_produccion || 0).toFixed(2)}</strong>
                        </div>
                    </div>
                    <hr>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Ingrediente</th>
                                    <th class="text-end">Cantidad</th>
                                    <th>Unidad</th>
                                    <th class="text-end">Costo</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${receta.ingredientes && receta.ingredientes.length > 0 ? 
                                    receta.ingredientes.map((ing, i) => `
                                        <tr>
                                            <td>${i + 1}</td>
                                            <td>${ing.ingrediente_nombre || 'Sin nombre'}</td>
                                            <td class="text-end">${parseFloat(ing.cantidad || 0).toFixed(2)}</td>
                                            <td>${ing.unidad || 'g'}</td>
                                            <td class="text-end">$${parseFloat(ing.costo_porcion || 0).toFixed(4)}</td>
                                        </tr>
                                    `).join('') : 
                                    '<tr><td colspan="5" class="text-center">Sin ingredientes</td></tr>'
                                }
                            </tbody>
                            <tfoot>
                                <tr class="table-success">
                                    <td colspan="4" class="text-end fw-bold">Costo Total:</td>
                                    <td class="text-end fw-bold">$${parseFloat(receta.costo_total_produccion || 0).toFixed(2)}</td>
                                </tr>
                                <tr class="table-info">
                                    <td colspan="4" class="text-end fw-bold">Precio Sugerido:</td>
                                    <td class="text-end fw-bold">$${parseFloat(receta.precio_venta_sugerido || 0).toFixed(2)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    ${receta.instrucciones ? `
                        <div class="mt-3">
                            <small class="text-muted d-block">Instrucciones:</small>
                            <p class="text-secondary">${receta.instrucciones}</p>
                        </div>
                    ` : ''}
                `;
                $('#detalleReceta').html(html);
            },
            error: function() {
                $('#detalleReceta').html('<div class="alert alert-danger">Error al cargar la receta</div>');
            }
        });
    });
    
    // ============================================================
    // RESETEAR MODAL
    // ============================================================
    window.resetearModalProducto = function() {
        $('#formProducto')[0].reset();
        $('#producto_id').val('');
        $('#categoria_id').val('');
        $('#campos_precio_stock').show();
        $('#seccion_composicion').hide();
        $('#detallesComposicion').html(`
            <tr><td colspan="6" class="text-center text-secondary py-3">
                <i class="fas fa-plus-circle me-2"></i> Selecciona "Compuesto" para ver la composición
            </td></tr>
        `);
        $('#pie_composicion').hide();
        $('#costo_produccion_total').text('$0.00');
        $('#precio_produccion_sugerido').text('$0.00');
        $('#modalTitulo').html('<i class="fas fa-box me-2"></i> Nuevo Producto');
        
        // Resetear campos bloqueados
        $('#tipo_producto').prop('disabled', false);
        $('#tipo_bloqueado_msg').hide();
        $('#precio_compra').prop('disabled', false);
        $('#precio_compra').removeClass('campo-bloqueado');
        $('#precio_compra_origen').text('');
        $('#precio_venta').prop('disabled', false);
        $('#precio_venta').removeClass('campo-bloqueado');
        $('#precio_venta_origen').text('');
        $('#es_ingrediente').prop('disabled', false);
        $('#es_producto_final').prop('disabled', false);
        
        // Unidades por defecto
        $('#unidad_base').val('g');
        $('#unidad_compra').val('unidad');
        $('#contenido_unidad').val('1.00');
        actualizarContenidoUnidad();
        
        recetaCargada = null;
        esProductoConReceta = false;
    };
    
    // ============================================================
    // FILTROS Y BÚSQUEDA
    // ============================================================
    $('#buscarProducto').on('keyup', function() {
        let search = $(this).val().toLowerCase();
        $('#tablaProductos tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(search) > -1);
        });
    });
    
    $('#filtroCategoria').on('change', function() {
        let categoria = $(this).val();
        if(categoria === '') {
            $('#tablaProductos tbody tr').show();
        } else {
            $('#tablaProductos tbody tr').each(function() {
                let cat = $(this).data('categoria');
                $(this).toggle(cat == categoria);
            });
        }
    });
    
    $('#filtroTipo').on('change', function() {
        let tipo = $(this).val();
        if(tipo === '') {
            $('#tablaProductos tbody tr').show();
        } else {
            $('#tablaProductos tbody tr').each(function() {
                let rowTipo = $(this).data('tipo');
                $(this).toggle(rowTipo == tipo);
            });
        }
    });
    
    // ============================================================
    // EDITAR PRODUCTO
    // ============================================================
    $(document).on('click', '.btn-editar', function() {
        let id = $(this).data('id');
        resetearModalProducto();
        
        $.ajax({
            url: API_URL + '?id=' + id,
            method: 'GET',
            xhrFields: { withCredentials: true },
            success: function(response) {
                if(response.success) {
                    let data = response.data;
                    
                    $('#producto_id').val(data.id);
                    $('#nombre').val(data.nombre);
                    $('#descripcion').val(data.descripcion);
                    $('#categoria_id').val(data.categoria_id || '');
                    $('#precio_compra').val(data.precio_compra);
                    $('#precio_venta').val(data.precio_venta);
                    $('#stock').val(data.stock);
                    $('#stock_minimo').val(data.stock_minimo || 5);
                    $('#tipo_producto').val(data.tipo_producto || 'simple');
                    $('#es_ingrediente').val(data.es_ingrediente);
                    $('#es_producto_final').val(data.es_producto_final);
                    
                    // Cargar unidades
                    $('#unidad_base').val(data.unidad_base || 'g');
                    $('#unidad_compra').val(data.unidad_compra || 'unidad');
                    $('#contenido_unidad').val(data.contenido_unidad || 1.00);
                    actualizarContenidoUnidad();
                    
                    // ============================================================
                    // ✅ SI ES COMPUESTO O TIENE RECETA, CARGAR COMPOSICIÓN
                    // ============================================================
                    if(data.tipo_producto === 'compuesto' || data.tiene_receta > 0) {
                        $('#tipo_producto').val('compuesto');
                        cargarComposicionDesdeReceta(data.id);
                    } else {
                        $('#seccion_composicion').hide();
                        $('#campos_precio_stock').show();
                        $('#tipo_producto').prop('disabled', false);
                        $('#tipo_bloqueado_msg').hide();
                        $('#precio_compra').prop('disabled', false);
                        $('#precio_compra').removeClass('campo-bloqueado');
                        $('#precio_compra_origen').text('');
                        $('#precio_venta').prop('disabled', false);
                        $('#precio_venta').removeClass('campo-bloqueado');
                        $('#precio_venta_origen').text('');
                        $('#es_ingrediente').prop('disabled', false);
                        $('#es_producto_final').prop('disabled', false);
                    }
                    
                    $('#modalTitulo').html('<i class="fas fa-edit me-2"></i> Editar Producto');
                    $('#modalProducto').modal('show');
                }
            }
        });
    });
    
    // ============================================================
    // ELIMINAR PRODUCTO
    // ============================================================
    $(document).on('click', '.btn-eliminar', function() {
        let id = $(this).data('id');
        let nombre = $(this).closest('tr').find('td:eq(1) strong').text();
        if(confirm('⚠️ ¿Seguro que deseas eliminar "' + nombre + '"?\nSe moverá a la papelera.')) {
            $.ajax({
                url: API_URL,
                method: 'DELETE',
                data: JSON.stringify({ id: id }),
                contentType: 'application/json',
                xhrFields: { withCredentials: true },
                success: function(response) {
                    if(response.success) {
                        alert('✅ Producto eliminado');
                        location.reload();
                    } else {
                        alert('❌ Error: ' + response.message);
                    }
                }
            });
        }
    });
    
    // ============================================================
    // GUARDAR PRODUCTO
    // ============================================================
    $('#formProducto').off('submit').on('submit', function(e) {
        e.preventDefault();
        
        if(guardandoProducto) return;
        
        let btn = $(this).find('button[type="submit"]');
        let originalText = btn.html();
        btn.prop('disabled', true);
        btn.html('<i class="fas fa-spinner fa-spin me-2"></i> Guardando...');
        guardandoProducto = true;
        
        // Recolectar datos
        let formData = {
            id: $('#producto_id').val(),
            nombre: $('#nombre').val().trim(),
            descripcion: $('#descripcion').val().trim(),
            categoria_id: $('#categoria_id').val(),
            precio_compra: $('#precio_compra').val(),
            precio_venta: $('#precio_venta').val(),
            stock: $('#stock').val(),
            stock_minimo: $('#stock_minimo').val(),
            tipo_producto: $('#tipo_producto').val(),
            es_ingrediente: $('#es_ingrediente').val(),
            es_producto_final: $('#es_producto_final').val(),
            unidad_base: $('#unidad_base').val(),
            unidad_compra: $('#unidad_compra').val(),
            contenido_unidad: $('#contenido_unidad').val()
        };
        
        console.log('📤 Enviando datos:', formData);
        
        $.ajax({
            url: API_URL,
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            xhrFields: { withCredentials: true },
            success: function(response) {
                btn.prop('disabled', false);
                btn.html(originalText);
                guardandoProducto = false;
                
                if(response.success) {
                    alert('✅ Producto guardado correctamente');
                    location.reload();
                } else {
                    alert('❌ Error: ' + response.message);
                }
            },
            error: function(xhr) {
                console.error('❌ Error:', xhr);
                btn.prop('disabled', false);
                btn.html(originalText);
                guardandoProducto = false;
                alert('❌ Error al guardar el producto');
            }
        });
    });
    
    // ============================================================
    // EVENTOS DE UNIDADES
    // ============================================================
    $(document).on('change', '#unidad_base, #unidad_compra', function() {
        actualizarContenidoUnidad();
    });
    
    $(document).on('input', '#contenido_unidad', function() {
        actualizarContenidoUnidad();
    });
});

// ============================================================
// FUNCIONES PARA CATEGORÍAS (FUERA DEL DOCUMENT.READY)
// ============================================================
window.abrirNuevaCategoria = function() {
    $('#nueva_categoria_nombre').val('');
    $('#nueva_categoria_descripcion').val('');
    $('#modalNuevaCategoria').modal('show');
};

window.guardarNuevaCategoria = function() {
    let nombre = $('#nueva_categoria_nombre').val().trim();
    if(nombre === '') {
        alert('⚠️ El nombre de la categoría es obligatorio');
        return;
    }
    
    let btn = document.querySelector('#modalNuevaCategoria .btn-primary');
    let originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Guardando...';
    btn.disabled = true;
    
    $.ajax({
        url: '/mi-negocio-web/api/categorias.php',
        method: 'POST',
        data: JSON.stringify({
            nombre: nombre,
            descripcion: $('#nueva_categoria_descripcion').val().trim()
        }),
        contentType: 'application/json',
        xhrFields: { withCredentials: true },
        success: function(response) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            
            if(response.success) {
                alert('✅ Categoría creada correctamente');
                $('#modalNuevaCategoria').modal('hide');
                
                let select = document.getElementById('categoria_id');
                let option = document.createElement('option');
                option.value = response.id;
                option.textContent = nombre;
                option.selected = true;
                select.appendChild(option);
                
                let filtro = document.getElementById('filtroCategoria');
                if(filtro) {
                    let opt = document.createElement('option');
                    opt.value = response.id;
                    opt.textContent = nombre;
                    filtro.appendChild(opt);
                }
            } else {
                alert('❌ Error: ' + response.message);
            }
        },
        error: function() {
            btn.innerHTML = originalText;
            btn.disabled = false;
            alert('❌ Error al guardar la categoría');
        }
    });
};

console.log('✅ productos.js actualizado con recetas');