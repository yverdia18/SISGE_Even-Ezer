// ============================================================
// DASHBOARD.JS - VERSIÓN LOCAL (SIN INTERNET)
// ============================================================

const API_URL = '/mi-negocio-web/api/dashboard.php';
let chartVentas = null;
let intervaloActualizacion = null;
const TIEMPO_ACTUALIZACION = 30000; // 30 segundos

// ============================================================
// INICIALIZAR
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ dashboard.js cargado (local)');
    
    // Cargar datos iniciales
    cargarEstadisticas();
    cargarVentasSemana();
    cargarTopProductos();
    cargarAlertasStock();
    cargarUltimasVentas();
    
    // Actualizar cada 30 segundos
    intervaloActualizacion = setInterval(function() {
        console.log('🔄 Actualizando dashboard...');
        cargarEstadisticas();
        cargarVentasSemana();
        cargarTopProductos();
        cargarAlertasStock();
        cargarUltimasVentas();
        const el = document.getElementById('ultima_actualizacion');
        if(el) el.textContent = 'Actualizado: ' + new Date().toLocaleTimeString();
    }, TIEMPO_ACTUALIZACION);
});

// ============================================================
// 1. ESTADÍSTICAS
// ============================================================
function cargarEstadisticas() {
    fetch(API_URL + '?action=stats', {
        credentials: 'include'
    })
    .then(response => response.json())
    .then(response => {
        if(response.success) {
            const data = response.data;
            animarNumero('statProductos', data.productos);
            animarNumero('statVentasHoy', data.ventas_hoy);
            animarNumero('statStockBajo', data.stock_bajo);
            animarNumero('statGanancia', data.ganancia, true);
            
            const montoEl = document.getElementById('statMontoVentas');
            if(montoEl) montoEl.textContent = '$' + data.monto_ventas.toFixed(2);
            
            const stockEl = document.getElementById('statStockBajo');
            if(stockEl) {
                if(data.stock_bajo > 0) {
                    stockEl.className = 'fw-bold mb-0 text-warning';
                } else {
                    stockEl.className = 'fw-bold mb-0 text-success';
                }
            }
        }
    })
    .catch(error => console.error('❌ Error estadísticas:', error));
}

// ============================================================
// 2. VENTAS SEMANALES - GRÁFICO CON CHART.JS LOCAL
// ============================================================
function cargarVentasSemana() {
    fetch(API_URL + '?action=ventas_semana', {
        credentials: 'include'
    })
    .then(response => response.json())
    .then(response => {
        if(response.success) {
            const data = response.data;
            crearGraficoVentas(data.labels, data.montos);
        }
    })
    .catch(error => console.error('❌ Error ventas semanales:', error));
}

function crearGraficoVentas(labels, montos) {
    const ctx = document.getElementById('chartVentas');
    if(!ctx) return;
    
    // Destruir gráfico anterior si existe
    if(chartVentas) {
        chartVentas.destroy();
    }
    
    // Verificar que Chart.js está disponible
    if(typeof Chart === 'undefined') {
        console.warn('⚠️ Chart.js no está cargado');
        return;
    }
    
    const canvas = ctx.getContext('2d');
    
    // Colores
    const gradiente = canvas.createLinearGradient(0, 0, 0, 200);
    gradiente.addColorStop(0, 'rgba(16, 185, 129, 0.7)');
    gradiente.addColorStop(1, 'rgba(16, 185, 129, 0.05)');
    
    chartVentas = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels.length > 0 ? labels : ['Sin datos'],
            datasets: [{
                label: 'Ingresos ($)',
                data: montos.length > 0 ? montos : [0],
                backgroundColor: gradiente,
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 2,
                borderRadius: 6,
                barPercentage: 0.6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '$' + context.raw.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toFixed(0);
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

// ============================================================
// 3. TOP PRODUCTOS
// ============================================================
function cargarTopProductos() {
    fetch(API_URL + '?action=top_productos', {
        credentials: 'include'
    })
    .then(response => response.json())
    .then(response => {
        if(response.success) {
            const productos = response.data;
            const container = document.getElementById('topProductosContainer');
            if(!container) return;
            
            let html = '';
            
            if(productos.length === 0) {
                html = `
                    <div class="text-center py-4 text-secondary">
                        <i class="fas fa-info-circle me-2"></i>
                        No hay productos vendidos aún
                    </div>
                `;
            } else {
                const colores = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6'];
                const maxVendido = productos[0]?.total_vendido || 1;
                
                productos.forEach(function(p, index) {
                    const porcentaje = (p.total_vendido / maxVendido) * 100;
                    const color = colores[index % colores.length];
                    
                    html += `
                        <div class="d-flex align-items-center mb-3">
                            <div class="me-2" style="width: 28px;">
                                <span class="badge bg-secondary rounded-circle" style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;">
                                    ${index + 1}
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold">${p.nombre}</span>
                                    <span class="text-muted small">${p.total_vendido} und</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar" style="width: ${porcentaje}%; background-color: ${color};"></div>
                                </div>
                            </div>
                        </div>
                    `;
                });
            }
            
            container.innerHTML = html;
        }
    })
    .catch(error => {
        console.error('❌ Error top productos:', error);
        const container = document.getElementById('topProductosContainer');
        if(container) {
            container.innerHTML = `
                <div class="text-center py-4 text-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Error al cargar datos
                </div>
            `;
        }
    });
}

// ============================================================
// 4. ALERTAS DE STOCK BAJO
// ============================================================
function cargarAlertasStock() {
    fetch(API_URL + '?action=stock_bajo', {
        credentials: 'include'
    })
    .then(response => response.json())
    .then(response => {
        if(response.success) {
            const productos = response.data;
            const container = document.getElementById('alertasStockContainer');
            if(!container) return;
            
            let html = '';
            
            if(productos.length === 0) {
                html = `
                    <div class="alert alert-success mb-0">
                        <i class="fas fa-check-circle me-2"></i>
                        ✅ Todos los productos tienen stock suficiente
                    </div>
                `;
            } else {
                html = `
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Stock</th>
                                    <th>Mínimo</th>
                                    <th>Faltante</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;
                    
                    productos.forEach(function(p) {
                        const faltante = p.stock_minimo - p.stock;
                        const urgencia = faltante > 5 ? 'danger' : 'warning';
                        
                        html += `
                            <tr>
                                <td><strong>${p.nombre}</strong></td>
                                <td><span class="badge bg-${urgencia}">${p.stock}</span></td>
                                <td>${p.stock_minimo}</td>
                                <td class="text-danger">${faltante}</td>
                                <td>
                                    <a href="pages/compras.php" class="btn btn-sm btn-primary">
                                        <i class="fas fa-shopping-cart"></i> Comprar
                                    </a>
                                </td>
                            </tr>
                        `;
                    });
                    
                    html += `
                            </tbody>
                        </table>
                    </div>
                `;
            }
            
            container.innerHTML = html;
        }
    })
    .catch(error => {
        console.error('❌ Error alertas stock:', error);
        const container = document.getElementById('alertasStockContainer');
        if(container) {
            container.innerHTML = `
                <div class="alert alert-danger mb-0">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Error al cargar alertas de stock
                </div>
            `;
        }
    });
}

// ============================================================
// 5. ÚLTIMAS VENTAS
// ============================================================
function cargarUltimasVentas() {
    fetch(API_URL + '?action=ultimas_ventas', {
        credentials: 'include'
    })
    .then(response => response.json())
    .then(response => {
        if(response.success) {
            const ventas = response.data;
            const tbody = document.querySelector('#tablaUltimasVentas');
            if(!tbody) return;
            
            let html = '';
            
            if(ventas.length === 0) {
                html = `
                    <tr>
                        <td colspan="4" class="text-center text-secondary py-3">
                            <i class="fas fa-info-circle me-2"></i>
                            No hay ventas registradas
                        </td>
                    </tr>
                `;
            } else {
                ventas.forEach(function(v) {
                    const fecha = new Date(v.fecha);
                    html += `
                        <tr>
                            <td>${v.id}</td>
                            <td>${v.cliente || 'Cliente general'}</td>
                            <td class="fw-bold text-success">$${parseFloat(v.total).toFixed(2)}</td>
                            <td>${fecha.toLocaleString()}</td>
                        </tr>
                    `;
                });
            }
            
            tbody.innerHTML = html;
        }
    })
    .catch(error => console.error('❌ Error últimas ventas:', error));
}

// ============================================================
// 6. ANIMACIÓN DE NÚMEROS
// ============================================================
function animarNumero(id, target, esMoneda = false) {
    const element = document.getElementById(id);
    if(!element) return;
    
    const current = parseFloat(element.textContent.replace(/[$,]/g, '')) || 0;
    const duration = 800;
    const startTime = performance.now();
    const startValue = current;
    const endValue = target;
    
    function updateNumber(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easeOut = 1 - Math.pow(1 - progress, 3);
        const currentValue = startValue + (endValue - startValue) * easeOut;
        
        if(esMoneda) {
            element.textContent = '$' + currentValue.toFixed(2);
        } else {
            element.textContent = Math.round(currentValue);
        }
        
        if(progress < 1) {
            requestAnimationFrame(updateNumber);
        } else {
            if(esMoneda) {
                element.textContent = '$' + endValue.toFixed(2);
            } else {
                element.textContent = Math.round(endValue);
            }
        }
    }
    
    requestAnimationFrame(updateNumber);
}

// ============================================================
// 7. REFRESCAR MANUAL
// ============================================================
function refrescarDashboard() {
    console.log('🔄 Refrescando dashboard manualmente...');
    cargarEstadisticas();
    cargarVentasSemana();
    cargarTopProductos();
    cargarAlertasStock();
    cargarUltimasVentas();
    const el = document.getElementById('ultima_actualizacion');
    if(el) el.textContent = 'Actualizado: ' + new Date().toLocaleTimeString();
}

window.refrescarDashboard = refrescarDashboard;

// ============================================================
// 8. DETENER ACTUALIZACIONES
// ============================================================
window.addEventListener('beforeunload', function() {
    if(intervaloActualizacion) {
        clearInterval(intervaloActualizacion);
    }
});

console.log('✅ dashboard.js cargado correctamente (local)');