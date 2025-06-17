<?php
require_once '../vendor/autoload.php';

try {
    $db = new PDO(
        "mysql:host=localhost;dbname=clinica_mentalmente",
        "root",
        ""
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Crear la tabla si no existe
    $db->exec("
        CREATE TABLE IF NOT EXISTS `administradores` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nombre` varchar(255) NOT NULL,
            `email` varchar(255) NOT NULL,
            `password` varchar(255) NOT NULL,
            `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");

    // Datos del administrador
    $nombre = "Administrador";
    $email = "admin@mentalmente.com";
    $password = "admin123";
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Verificar si el admin ya existe
    $stmt = $db->prepare("SELECT id FROM administradores WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        // Actualizar la contraseña si el admin ya existe
        $stmt = $db->prepare("UPDATE administradores SET password = ? WHERE email = ?");
        $stmt->execute([$hashed_password, $email]);
        echo "Administrador actualizado correctamente.<br>";
    } else {
        // Crear nuevo admin si no existe
        $stmt = $db->prepare("INSERT INTO administradores (nombre, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$nombre, $email, $hashed_password]);
        echo "Administrador creado correctamente.<br>";
    }

    echo "Credenciales de acceso:<br>";
    echo "Email: " . $email . "<br>";
    echo "Contraseña: " . $password . "<br>";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?> 