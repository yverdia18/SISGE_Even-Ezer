<?php
// logout.php
require_once 'includes/config.php';

// Cerrar sesión
session_destroy();
header('Location: login.php');
exit;
?>