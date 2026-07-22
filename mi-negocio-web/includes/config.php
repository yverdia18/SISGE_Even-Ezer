<?php
// includes/config.php

// =============================================
// CONFIGURACIÓN DE LA BASE DE DATOS
// =============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'mi_negocio');

// =============================================
// CONFIGURACIÓN GENERAL
// =============================================
define('SITE_NAME', 'Mi Negocio');

// =============================================
// URL BASE - CORREGIDA PARA DETECTAR AUTOMÁTICAMENTE
// =============================================

// Detectar protocolo (HTTP o HTTPS)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

// Detectar el nombre del servidor y puerto
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Detectar la carpeta del proyecto automáticamente
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$script_dir = dirname($script_name);

// Si estamos en la raíz, usar '/'
if ($script_dir == '/' || $script_dir == '\\') {
    $base_path = '/';
} else {
    // Eliminar la parte de /pages/ o /api/ si existe
    $base_path = str_replace(['/pages', '/api', '/includes'], '', $script_dir);
    // Asegurar que termina con /
    if (substr($base_path, -1) !== '/') {
        $base_path .= '/';
    }
}

// Construir la URL base
define('SITE_URL', $protocol . $host . $base_path);

// =============================================
// CONFIGURACIÓN DE SESIÓN
// =============================================

// Iniciar sesión SOLO si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =============================================
// CONFIGURACIÓN DE ZONA HORARIA Y ERRORES
// =============================================
date_default_timezone_set('America/Mexico_City');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// =============================================
// FUNCIÓN DE AYUDA PARA URLS
// =============================================

/**
 * Genera una URL absoluta para el proyecto
 * @param string $path Ruta relativa (ej: 'pages/productos.php')
 * @return string URL completa
 */
function url($path = '') {
    return SITE_URL . ltrim($path, '/');
}

// =============================================
// VARIABLES DE ENTORNO (opcional)
// =============================================

// Definir entorno de desarrollo
define('ENVIRONMENT', 'development'); // 'development' o 'production'

// En producción, desactivar errores
if (ENVIRONMENT === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// =============================================
// CONFIGURACIÓN DE SEGURIDAD (opcional)
// =============================================

// Definir clave secreta para JWT o cifrado (si lo usas)
// define('SECRET_KEY', 'tu_clave_secreta_aqui');

// Definir tiempo de expiración de sesión (en segundos)
define('SESSION_TIMEOUT', 1800); // 1 hora