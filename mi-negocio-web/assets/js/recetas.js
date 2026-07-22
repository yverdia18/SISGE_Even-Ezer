// ============================================================
// RECETAS.JS - MÓDULO DE RECETAS
// ============================================================

console.log('✅ recetas.js cargado');

// ============================================================
// FUNCIONES GLOBALES
// ============================================================

/**
 * Calcula el costo de un ingrediente según la fórmula:
 * Costo = (Cantidad_receta / Contenido_unidad_compra) × Precio_compra
 */
function calcularCostoIngrediente(row) {
    const select = row.querySelector('.ingrediente-select');
    const selectedOption = select.options[select.selectedIndex];
    
    if(!selectedOption || !selectedOption.value) {
        row.querySelector('.costo-ingrediente').value = '$0.0000';
        calcularTotales();
        return;
    }
    
    const cantidad = parseFloat(row.querySelector('.cantidad-ingrediente').value) || 0;
    const precio = parseFloat(selectedOption.dataset.precio) || 0;
    const contenido = parseFloat(selectedOption.dataset.contenido) || 1;
    const merma = parseFloat(row.querySelector('.merma-ingrediente').value) || 0;
    
    // Obtener unidad seleccionada por el usuario
    const unidadSelect = row.querySelector('.unidad-ingrediente');
    const unidadSeleccionada = unidadSelect ? unidadSelect.value : 'g';
    
    // Obtener unidad base del producto
    const unidadBase = selectedOption.dataset.unidadBase || 'g';
    
    console.log('🔍 Calculando costo:', {
        producto: selectedOption.text,
        cantidad: cantidad,
        precio: precio,
        contenido: contenido,
        unidadSeleccionada: unidadSeleccionada,
        unidadBase: unidadBase,
        merma: merma
    });
    
    // ✅ FÓRMULA: (Cantidad / Contenido) × Precio
    let costo = 0;
    if(contenido > 0 && cantidad > 0 && precio > 0) {
        costo = (cantidad / contenido) * precio;
    }
    
    // Aplicar merma
    const costoConMerma = costo * (1 + merma / 100);
    
    // Mostrar resultado
    row.querySelector('.costo-ingrediente').value = '$' + costoConMerma.toFixed(4);
    
    console.log('✅ Costo calculado:', costoConMerma.toFixed(4));
    
    calcularTotales();
}

function calcularTotales() {
    let totalCosto = 0;
    document.querySelectorAll('.costo-ingrediente').forEach(function(input) {
        const valor = parseFloat(input.value.replace('$', '')) || 0;
        totalCosto += valor;
    });
    
    const rendimiento = parseFloat(document.getElementById('rendimiento')?.value) || 1;
    const margen = parseFloat(document.getElementById('margen_ganancia')?.value) || 0;
    const costoUnitario = rendimiento > 0 ? totalCosto / rendimiento : 0;
    const precioVenta = (margen > 0 && costoUnitario > 0) ? costoUnitario / (1 - (margen / 100)) : 0;
    
    const costoTotalDisplay = document.getElementById('costoTotalDisplay');
    const costoUnitarioDisplay = document.getElementById('costoUnitarioDisplay');
    const precioVentaDisplay = document.getElementById('precioVentaDisplay');
    
    if(costoTotalDisplay) costoTotalDisplay.textContent = '$' + totalCosto.toFixed(2);
    if(costoUnitarioDisplay) costoUnitarioDisplay.textContent = '$' + costoUnitario.toFixed(2);
    if(precioVentaDisplay) precioVentaDisplay.textContent = '$' + precioVenta.toFixed(2);
}

function resetFormReceta() {
    const form = document.getElementById('formReceta');
    if(form) form.reset();
    
    document.getElementById('receta_id').value = '';
    document.getElementById('modalTitulo').textContent = 'Nueva Receta';
    document.getElementById('unidad_medida').value = 'racion';
    document.getElementById('margen_ganancia').value = '40';
    document.getElementById('rendimiento').value = '1';
    
    const tbody = document.getElementById('ingredientesBody');
    if(tbody) {
        const rows = tbody.querySelectorAll('tr:not(#ingredienteTemplate)');
        rows.forEach(row => row.remove());
    }
    
    agregarFilaIngrediente();
    calcularTotales();
}

function agregarFilaIngrediente() {
    const template = document.getElementById('ingredienteTemplate');
    if(!template) return;
    
    const tbody = document.getElementById('ingredientesBody');
    const rows = tbody.querySelectorAll('tr:not(#ingredienteTemplate)');
    const nuevoNumero = rows.length + 1;
    
    const clone = template.cloneNode(true);
    clone.id = '';
    clone.style.display = '';
    clone.querySelector('td:first-child').textContent = nuevoNumero;
    clone.querySelector('.ingrediente-select').value = '';
    clone.querySelector('.cantidad-ingrediente').value = '';
    clone.querySelector('.unidad-ingrediente').value = 'g';
    clone.querySelector('.merma-ingrediente').value = '0';
    clone.querySelector('.costo-ingrediente').value = '$0.0000';
    
    tbody.appendChild(clone);
}

// ============================================================
// INICIALIZAR
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('📊 DOM listo');
    console.log('🔍 Botones .btn-editar:', document.querySelectorAll('.btn-editar').length);
    
    // Si no hay ingredientes, agregar una fila
    const tbody = document.getElementById('ingredientesBody');
    if(tbody) {
        const rows = tbody.querySelectorAll('tr:not(#ingredienteTemplate)');
        if(rows.length === 0 && document.getElementById('ingredienteTemplate')) {
            agregarFilaIngrediente();
        }
    }
    
    // ============================================================
    // EDITAR RECETA
    // ============================================================
    document.querySelectorAll('.btn-editar').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const id = this.dataset.id;
            console.log('🟢 EDITAR - ID:', id);
            
            if(!id) {
                alert('❌ Error: No se encontró el ID');
                return;
            }
            
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            this.disabled = true;
            
            fetch('../api/recetas.php?id=' + id)
                .then(response => {
                    if(!response.ok) {
                        throw new Error('Error HTTP: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('✅ Datos recibidos:', data);
                    
                    this.innerHTML = originalText;
                    this.disabled = false;
                    
                    const receta = data.data || data;
                    
                    if(!receta || !receta.id) {
                        alert('❌ Error: No se encontró la receta');
                        return;
                    }
                    
                    // Llenar formulario
                    document.getElementById('receta_id').value = receta.id;
                    document.getElementById('nombre').value = receta.nombre || '';
                    document.getElementById('descripcion').value = receta.descripcion || '';
                    document.getElementById('producto_id').value = receta.producto_id || '';
                    document.getElementById('rendimiento').value = receta.rendimiento || 1;
                    document.getElementById('unidad_medida').value = receta.unidad_medida || 'racion';
                    document.getElementById('tiempo_preparacion').value = receta.tiempo_preparacion || 0;
                    document.getElementById('instrucciones').value = receta.instrucciones || '';
                    document.getElementById('margen_ganancia').value = receta.margen_ganancia || 40;
                    document.getElementById('modalTitulo').textContent = 'Editar Receta: ' + receta.nombre;
                    
                    // Limpiar ingredientes
                    const tbody = document.getElementById('ingredientesBody');
                    const rows = tbody.querySelectorAll('tr:not(#ingredienteTemplate)');
                    rows.forEach(row => row.remove());
                    
                    // Cargar ingredientes
                    if(receta.ingredientes && receta.ingredientes.length > 0) {
                        let contador = 0;
                        receta.ingredientes.forEach(function(ing) {
                            contador++;
                            
                            const template = document.getElementById('ingredienteTemplate');
                            const clone = template.cloneNode(true);
                            clone.id = '';
                            clone.style.display = '';
                            
                            clone.querySelector('td:first-child').textContent = contador;
                            clone.querySelector('.ingrediente-select').value = ing.ingrediente_id || '';
                            clone.querySelector('.cantidad-ingrediente').value = ing.cantidad || '';
                            
                            // Unidad - buscar en el select
                            const unidadSelect = clone.querySelector('.unidad-ingrediente');
                            const unidad = ing.unidad || 'g';
                            const optionExists = Array.from(unidadSelect.options).some(opt => opt.value === unidad);
                            unidadSelect.value = optionExists ? unidad : 'g';
                            
                            clone.querySelector('.merma-ingrediente').value = ing.merma || 0;
                            
                            // Recalcular costo
                            calcularCostoIngrediente(clone);
                            
                            tbody.appendChild(clone);
                        });
                    } else {
                        agregarFilaIngrediente();
                    }
                    
                    calcularTotales();
                    
                    const modal = new bootstrap.Modal(document.getElementById('modalReceta'));
                    modal.show();
                })
                .catch(error => {
                    console.error('❌ Error:', error);
                    this.innerHTML = originalText;
                    this.disabled = false;
                    alert('❌ Error al cargar la receta: ' + error.message);
                });
        });
    });
    
    // ============================================================
    // PRODUCIR RECETA
    // ============================================================
    document.querySelectorAll('.btn-producir').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const id = this.dataset.id;
            const nombre = this.dataset.nombre || 'Receta';
            const rendimiento = this.dataset.rendimiento || '1';
            const producto = this.dataset.producto || 'Sin producto';
            
            console.log('🟢 PRODUCIR - ID:', id);
            
            if(!id) {
                alert('❌ Error: No se encontró el ID');
                return;
            }
            
            document.getElementById('producir_receta_id').value = id;
            document.getElementById('producirNombreReceta').textContent = '📋 ' + nombre;
            document.getElementById('producirRendimiento').textContent = rendimiento + ' unidades';
            document.getElementById('producirProducto').textContent = producto;
            document.getElementById('btnConfirmarProduccion').disabled = false;
            
            const modal = new bootstrap.Modal(document.getElementById('modalProducir'));
            modal.show();
        });
    });
    
    // ============================================================
    // CONFIRMAR PRODUCCIÓN
    // ============================================================
    document.getElementById('btnConfirmarProduccion')?.addEventListener('click', function() {
        const receta_id = document.getElementById('producir_receta_id').value;
        
        if(!receta_id) {
            alert('❌ Error: No se encontró el ID');
            return;
        }
        
        const btn = this;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Produciendo...';
        
        fetch('../api/recetas.php', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'producir', receta_id: receta_id })
        })
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            
            if(data.success) {
                alert('✅ Producción completada');
                const modal = bootstrap.Modal.getInstance(document.getElementById('modalProducir'));
                if(modal) modal.hide();
                location.reload();
            } else {
                alert('❌ Error: ' + data.message);
            }
        })
        .catch(error => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            alert('❌ Error al producir');
        });
    });
    
    // ============================================================
    // ELIMINAR RECETA
    // ============================================================
    document.querySelectorAll('.btn-eliminar').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const id = this.dataset.id;
            
            if(!id) {
                alert('❌ Error: No se encontró el ID');
                return;
            }
            
            if(confirm('¿Estás seguro de eliminar esta receta?')) {
                const btn = this;
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                btn.disabled = true;
                
                fetch('../api/recetas.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        alert('✅ Receta eliminada');
                        location.reload();
                    } else {
                        alert('❌ Error: ' + data.message);
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                })
                .catch(error => {
                    alert('❌ Error al eliminar');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
            }
        });
    });
    
    // ============================================================
    // AGREGAR INGREDIENTE
    // ============================================================
    document.getElementById('agregarIngrediente')?.addEventListener('click', function() {
        agregarFilaIngrediente();
    });
    
    // ============================================================
    // ELIMINAR INGREDIENTE
    // ============================================================
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.eliminar-ingrediente');
        if(!btn) return;
        
        const row = btn.closest('tr');
        const tbody = document.getElementById('ingredientesBody');
        const rows = tbody.querySelectorAll('tr:not(#ingredienteTemplate)');
        
        if(rows.length > 1) {
            row.remove();
            rows.forEach(function(r, index) {
                r.querySelector('td:first-child').textContent = index + 1;
            });
            calcularTotales();
        } else {
            alert('Debe haber al menos un ingrediente');
        }
    });
    
    // ============================================================
    // CÁLCULO DE COSTOS EN TIEMPO REAL
    // ============================================================
    document.addEventListener('change', function(e) {
        const select = e.target.closest('.ingrediente-select');
        if(select) {
            const row = select.closest('tr');
            calcularCostoIngrediente(row);
            return;
        }
        
        const unidadSelect = e.target.closest('.unidad-ingrediente');
        if(unidadSelect) {
            const row = unidadSelect.closest('tr');
            calcularCostoIngrediente(row);
            return;
        }
    });
    
    document.addEventListener('input', function(e) {
        const input = e.target.closest('.cantidad-ingrediente') || e.target.closest('.merma-ingrediente');
        if(input) {
            const row = input.closest('tr');
            calcularCostoIngrediente(row);
            return;
        }
        
        if(e.target.id === 'rendimiento' || e.target.id === 'margen_ganancia') {
            calcularTotales();
        }
    });
    
    // ============================================================
    // GUARDAR RECETA
    // ============================================================
    document.getElementById('formReceta')?.addEventListener('submit', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('🟢 Enviando formulario...');
        
        // Validar campos requeridos
        const nombre = document.getElementById('nombre').value.trim();
        if(!nombre) {
            alert('❌ El nombre de la receta es obligatorio');
            document.getElementById('nombre').focus();
            return;
        }
        
        const rendimiento = parseFloat(document.getElementById('rendimiento').value) || 0;
        if(rendimiento <= 0) {
            alert('❌ El rendimiento debe ser mayor a 0');
            document.getElementById('rendimiento').focus();
            return;
        }
        
        // Recolectar datos
        const data = {
            id: document.getElementById('receta_id').value || null,
            nombre: document.getElementById('nombre').value.trim(),
            descripcion: document.getElementById('descripcion').value.trim(),
            producto_id: document.getElementById('producto_id').value || null,
            rendimiento: parseFloat(document.getElementById('rendimiento').value) || 1,
            unidad_medida: document.getElementById('unidad_medida').value,
            tiempo_preparacion: parseInt(document.getElementById('tiempo_preparacion').value) || 0,
            instrucciones: document.getElementById('instrucciones').value.trim(),
            margen_ganancia: parseFloat(document.getElementById('margen_ganancia').value) || 0,
            ingredientes: []
        };
        
        // Recolectar ingredientes
        const rows = document.querySelectorAll('#ingredientesBody tr:not(#ingredienteTemplate)');
        
        rows.forEach(function(row) {
            const ingrediente_id = row.querySelector('.ingrediente-select')?.value;
            const cantidad = parseFloat(row.querySelector('.cantidad-ingrediente')?.value) || 0;
            const unidad = row.querySelector('.unidad-ingrediente')?.value || 'g';
            const merma = parseFloat(row.querySelector('.merma-ingrediente')?.value) || 0;
            
            if(ingrediente_id && cantidad > 0) {
                data.ingredientes.push({
                    ingrediente_id: parseInt(ingrediente_id),
                    cantidad: cantidad,
                    unidad: unidad,
                    merma: merma
                });
            }
        });
        
        console.log('📦 Datos a enviar:', data);
        
        if(data.ingredientes.length === 0) {
            alert('❌ Debes agregar al menos un ingrediente con cantidad válida');
            return;
        }
        
        // Deshabilitar botón
        const btn = document.getElementById('btnGuardarReceta');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Guardando...';
        
        // Enviar datos
        fetch('../api/recetas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => response.text())  // Primero obtener como texto para debug
        .then(text => {
            console.log('📄 Respuesta del servidor (texto):', text);
            
            // Intentar parsear JSON
            try {
                const result = JSON.parse(text);
                console.log('✅ Respuesta parseada:', result);
                
                const btn = document.getElementById('btnGuardarReceta');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-2"></i> Guardar Receta';
                
                if(result.success) {
                    alert('✅ ' + result.message);
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalReceta'));
                    if(modal) modal.hide();
                    location.reload();
                } else {
                    alert('❌ Error: ' + (result.message || 'Error desconocido'));
                }
            } catch(e) {
                console.error('❌ Error al parsear JSON:', e);
                console.log('📄 Texto recibido:', text);
                
                const btn = document.getElementById('btnGuardarReceta');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-2"></i> Guardar Receta';
                alert('❌ Error del servidor. Revisa la consola para más detalles.\n\n' + text.substring(0, 500));
            }
        })
        .catch(error => {
            console.error('❌ Error en la petición:', error);
            const btn = document.getElementById('btnGuardarReceta');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save me-2"></i> Guardar Receta';
            alert('❌ Error al guardar la receta: ' + error.message);
        });
    });
});

console.log('✅ recetas.js listo');