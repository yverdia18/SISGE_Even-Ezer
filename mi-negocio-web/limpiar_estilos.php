<?php
// limpiar_estilos.php - Script para eliminar bloques <style> de todos los archivos PHP

// =============================================
// CONFIGURACIÓN
// =============================================
$directorio_raiz = __DIR__; // Carpeta donde está este script
$extensiones = ['php'];     // Extensiones a procesar
$archivos_excluir = [
    'limpiar_estilos.php',  // Este mismo archivo
    'config.php',
    'db.php',
    'functions.php'
];
$carpetas_excluir = [
    'vendor',
    'node_modules',
    'cache',
    'logs'
];

// =============================================
// FUNCIÓN PARA LIMPIAR BLOQUES <style>
// =============================================
function limpiarBloquesStyle($contenido) {
    // Patrón para encontrar bloques <style>...</style>
    // Incluye tanto <style> como <style type="text/css">
    $patron = '/<style[^>]*>.*?<\/style>/is';
    
    // Reemplazar por una línea en blanco (comentario indicando que se eliminó)
    $contenido_limpio = preg_replace_callback($patron, function($matches) {
        // Extraer el contenido del style para saber qué se eliminó
        $contenido_style = $matches[0];
        // Si el bloque tiene algo de texto, dejarlo como comentario
        if (strlen($contenido_style) > 50) {
            return "<!-- Bloque <style> eliminado por limpiador -->\n";
        }
        return '';
    }, $contenido);
    
    return $contenido_limpio;
}

// =============================================
// FUNCIÓN PARA ESCANEAR DIRECTORIOS RECURSIVAMENTE
// =============================================
function escanearDirectorio($dir, &$archivos_procesados = []) {
    global $carpetas_excluir, $archivos_excluir, $extensiones;
    
    if (!is_dir($dir)) {
        return $archivos_procesados;
    }
    
    $items = scandir($dir);
    
    foreach ($items as $item) {
        // Saltar directorios especiales
        if ($item === '.' || $item === '..') {
            continue;
        }
        
        $ruta = $dir . DIRECTORY_SEPARATOR . $item;
        
        // Saltar carpetas excluidas
        if (is_dir($ruta) && in_array($item, $carpetas_excluir)) {
            continue;
        }
        
        if (is_dir($ruta)) {
            // Escanear subdirectorios
            escanearDirectorio($ruta, $archivos_procesados);
        } else {
            // Procesar archivos
            $extension = pathinfo($item, PATHINFO_EXTENSION);
            if (in_array($extension, $extensiones) && !in_array($item, $archivos_excluir)) {
                $archivos_procesados[] = $ruta;
            }
        }
    }
    
    return $archivos_procesados;
}

// =============================================
// FUNCIÓN PARA PROCESAR UN ARCHIVO
// =============================================
function procesarArchivo($ruta_archivo) {
    echo "📄 Procesando: " . $ruta_archivo . "\n";
    
    // Leer contenido del archivo
    $contenido = file_get_contents($ruta_archivo);
    if ($contenido === false) {
        echo "   ❌ Error al leer el archivo\n";
        return false;
    }
    
    // Contar bloques <style> antes de limpiar
    preg_match_all('/<style[^>]*>.*?<\/style>/is', $contenido, $matches);
    $num_estilos = count($matches[0]);
    
    if ($num_estilos === 0) {
        echo "   ℹ️  No se encontraron bloques <style>\n";
        return true;
    }
    
    // Limpiar bloques <style>
    $contenido_limpio = limpiarBloquesStyle($contenido);
    
    // Verificar si hubo cambios
    if ($contenido_limpio === $contenido) {
        echo "   ⚠️  No se pudo limpiar el archivo\n";
        return false;
    }
    
    // Guardar el archivo limpio
    if (file_put_contents($ruta_archivo, $contenido_limpio) === false) {
        echo "   ❌ Error al guardar el archivo\n";
        return false;
    }
    
    echo "   ✅ Limpiado: " . $num_estilos . " bloque(s) <style> eliminados\n";
    return true;
}

// =============================================
// FUNCIÓN PARA CREAR BACKUP
// =============================================
function crearBackup($archivos) {
    $backup_dir = __DIR__ . DIRECTORY_SEPARATOR . 'backup_estilos_' . date('Ymd_His');
    
    if (!mkdir($backup_dir, 0777, true)) {
        echo "❌ No se pudo crear el directorio de backup\n";
        return false;
    }
    
    echo "\n📦 Creando backup en: " . $backup_dir . "\n";
    
    foreach ($archivos as $archivo) {
        $ruta_relativa = str_replace(__DIR__, '', $archivo);
        $ruta_backup = $backup_dir . $ruta_relativa;
        $dir_backup = dirname($ruta_backup);
        
        if (!is_dir($dir_backup)) {
            mkdir($dir_backup, 0777, true);
        }
        
        if (copy($archivo, $ruta_backup)) {
            echo "   ✅ Backup: " . $ruta_relativa . "\n";
        }
    }
    
    return true;
}

// =============================================
// EJECUCIÓN PRINCIPAL
// =============================================

echo "========================================\n";
echo "  🧹 LIMPIADOR DE BLOQUES <style>\n";
echo "========================================\n\n";

// 1. Escanear directorios
echo "🔍 Escaneando archivos...\n";
$archivos = escanearDirectorio($directorio_raiz);
echo "   📁 " . count($archivos) . " archivos PHP encontrados\n\n";

// 2. Crear backup
echo "📦 Creando backup antes de limpiar...\n";
$backup_creado = crearBackup($archivos);
if (!$backup_creado) {
    echo "⚠️  No se pudo crear el backup. ¿Continuar? (s/n): ";
    $confirmar = trim(fgets(STDIN));
    if (strtolower($confirmar) !== 's') {
        echo "❌ Proceso cancelado por el usuario\n";
        exit;
    }
}

echo "\n========================================\n";
echo "  🧹 Limpiando archivos...\n";
echo "========================================\n\n";

// 3. Procesar archivos
$archivos_limpiados = 0;
$total_estilos_eliminados = 0;

foreach ($archivos as $archivo) {
    echo "📄 Procesando: " . str_replace(__DIR__, '', $archivo) . "\n";
    
    $contenido = file_get_contents($archivo);
    if ($contenido === false) {
        echo "   ❌ Error al leer el archivo\n";
        continue;
    }
    
    preg_match_all('/<style[^>]*>.*?<\/style>/is', $contenido, $matches);
    $num_estilos = count($matches[0]);
    
    if ($num_estilos === 0) {
        echo "   ℹ️  No se encontraron bloques <style>\n\n";
        continue;
    }
    
    // Mostrar ejemplo del contenido eliminado (opcional)
    if ($num_estilos > 0 && strlen($matches[0][0]) < 500) {
        echo "   📝 Contenido eliminado:\n";
        echo "   " . substr($matches[0][0], 0, 150) . "...\n";
    }
    
    $contenido_limpio = limpiarBloquesStyle($contenido);
    
    if (file_put_contents($archivo, $contenido_limpio) !== false) {
        echo "   ✅ " . $num_estilos . " bloque(s) <style> eliminados\n\n";
        $archivos_limpiados++;
        $total_estilos_eliminados += $num_estilos;
    } else {
        echo "   ❌ Error al guardar el archivo\n\n";
    }
}

// =============================================
// RESUMEN FINAL
// =============================================
echo "========================================\n";
echo "  📊 RESUMEN\n";
echo "========================================\n";
echo "   📁 Archivos procesados: " . count($archivos) . "\n";
echo "   🧹 Archivos limpiados: " . $archivos_limpiados . "\n";
echo "   🗑️  Bloques <style> eliminados: " . $total_estilos_eliminados . "\n";
echo "   📦 Backup creado en: backup_estilos_" . date('Ymd_His') . "\n";
echo "========================================\n";
echo "\n✅ Proceso completado.\n";
echo "⚠️  Recuerda: Si algo falla, restaura los archivos desde el backup.\n";
echo "\n";
?>