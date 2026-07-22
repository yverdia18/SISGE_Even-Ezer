<?php
// test_login.php - Diagnóstico de login
require_once 'includes/config.php';
require_once 'includes/db.php';

$db = Database::getInstance()->getConnection();

// 1. Verificar que el usuario existe
$email = 'admin@minegocio.com';
$result = $db->query("SELECT id, nombre, email, password_hash, rol FROM usuarios WHERE email = '$email'");

if($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "<h3>✅ Usuario encontrado</h3>";
    echo "<pre>";
    print_r($user);
    echo "</pre>";
    
    // 2. Probar la contraseña
    $password = 'admin123';
    if(password_verify($password, $user['password_hash'])) {
        echo "<h3 style='color:green;'>✅ La contraseña 'admin123' es CORRECTA</h3>";
    } else {
        echo "<h3 style='color:red;'>❌ La contraseña 'admin123' es INCORRECTA</h3>";
        echo "<br>Vamos a actualizar la contraseña...";
        
        // 3. Actualizar la contraseña
        $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE usuarios SET password_hash = ? WHERE email = ?");
        $stmt->bind_param("ss", $new_hash, $email);
        if($stmt->execute()) {
            echo "<br>✅ Contraseña actualizada correctamente";
            echo "<br>Ahora intenta iniciar sesión con: admin@minegocio.com / admin123";
        }
    }
} else {
    echo "<h3 style='color:red;'>❌ Usuario NO encontrado</h3>";
    echo "<br>Creando usuario...";
    
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol, activo) VALUES (?, ?, ?, 'admin', 1)");
    $stmt->bind_param("sss", 'Administrador', $email, $hash);
    if($stmt->execute()) {
        echo "<br>✅ Usuario creado correctamente";
        echo "<br>Ahora inicia sesión con: admin@minegocio.com / admin123";
    }
}
?>