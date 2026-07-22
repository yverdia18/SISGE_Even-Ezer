<?php
// api/usuarios.php
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/db.php';

// Verificar autenticación y rol de administrador
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if($_SESSION['user_rol'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Permisos insuficientes']);
    exit;
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        if(isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $result = $db->query("SELECT id, nombre, email, rol, activo, bloqueado FROM usuarios WHERE id = $id");
            $usuario = $result->fetch_assoc();
            echo json_encode(['success' => true, 'data' => $usuario]);
        } else {
            $result = $db->query("SELECT id, nombre, email, rol, activo, bloqueado FROM usuarios ORDER BY id DESC");
            $usuarios = [];
            while($row = $result->fetch_assoc()) {
                $usuarios[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $usuarios]);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validaciones
        if(empty($data['nombre']) || empty($data['email'])) {
            echo json_encode(['success' => false, 'message' => 'Nombre y email son obligatorios']);
            exit;
        }
        
        if(!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Email no válido']);
            exit;
        }
        
        if(isset($data['id']) && $data['id'] > 0) {
            // Actualizar usuario existente
            $id = $data['id'];
            
            // Verificar si el email ya existe (excluyendo el usuario actual)
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $data['email'], $id);
            $stmt->execute();
            if($stmt->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'El email ya está en uso']);
                exit;
            }
            
            if(!empty($data['password'])) {
                // Actualizar con contraseña nueva
                $hash = password_hash($data['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE usuarios SET nombre = ?, email = ?, password_hash = ?, rol = ?, activo = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $data['nombre'], $data['email'], $hash, $data['rol'], $data['activo'], $id);
            } else {
                // Actualizar sin cambiar contraseña
                $stmt = $db->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ?, activo = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $data['nombre'], $data['email'], $data['rol'], $data['activo'], $id);
            }
        } else {
            // Crear nuevo usuario
            // Verificar si el email ya existe
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->bind_param("s", $data['email']);
            $stmt->execute();
            if($stmt->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'El email ya está registrado']);
                exit;
            }
            
            $password = $data['password'] ?? '123456';
            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol, activo) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $data['nombre'], $data['email'], $hash, $data['rol'], $data['activo']);
        }
        
        if($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Usuario guardado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $stmt->error]);
        }
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id']);
        
        // No permitir eliminar el propio usuario
        if($id == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'No puedes eliminar tu propio usuario']);
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
        }
        break;
        
    case 'PATCH':
        // Resetear contraseña
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id']);
        
        // Generar nueva contraseña aleatoria
        $nueva_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
        $hash = password_hash($nueva_password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $id);
        
        if($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Contraseña reseteada correctamente',
                'new_password' => $nueva_password
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al resetear la contraseña']);
        }
        break;
}
?>