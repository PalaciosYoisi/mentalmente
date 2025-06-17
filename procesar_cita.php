<?php
require 'vendor/autoload.php';

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Conexión a la base de datos
try {
    $db = new PDO(
        "mysql:host=localhost;dbname=clinica_mentalmente",
        "root",
        ""
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar y limpiar datos
    $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING);
    $correo = filter_input(INPUT_POST, 'correo', FILTER_SANITIZE_EMAIL);
    $telefono = filter_input(INPUT_POST, 'telefono', FILTER_SANITIZE_STRING);
    $fecha = filter_input(INPUT_POST, 'fecha', FILTER_SANITIZE_STRING);
    $hora = filter_input(INPUT_POST, 'hora', FILTER_SANITIZE_STRING);
    $modalidad = filter_input(INPUT_POST, 'modalidad', FILTER_SANITIZE_STRING);
    $metodo_pago = filter_input(INPUT_POST, 'metodo_pago', FILTER_SANITIZE_STRING);

    try {
        // Primero guardamos el paciente
        $stmt = $db->prepare("INSERT INTO pacientes (nombre, correo, telefono) VALUES (?, ?, ?)");
        $stmt->execute([$nombre, $correo, $telefono]);

        // Crear la cita directamente con los datos del paciente
        $fecha_hora = $fecha . ' ' . $hora;
        $stmt = $db->prepare("INSERT INTO citas (nombre_paciente, correo_paciente, fecha_cita, modalidad, forma_pago, estado) VALUES (?, ?, ?, ?, ?, 'pendiente')");
        $stmt->execute([$nombre, $correo, $fecha_hora, $modalidad, $metodo_pago]);
        $cita_id = $db->lastInsertId();

        // Generar token para consentimiento
        $token = bin2hex(random_bytes(32));
        $stmt = $db->prepare("INSERT INTO tokens_consentimiento (cita_id, token, fecha_expiracion) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
        $stmt->execute([$cita_id, $token]);

        // Actualizar el enlace de consentimiento en la cita
        $enlace_consentimiento = "consentimiento.php?token=" . $token;
        $stmt = $db->prepare("UPDATE citas SET enlace_consentimiento = ? WHERE id = ?");
        $stmt->execute([$enlace_consentimiento, $cita_id]);

        // Redirigir a página de éxito
        header('Location: exito.php');
        exit;
    } catch (Exception $e) {
        die("Error al procesar la cita: " . $e->getMessage());
    }
} else {
    header('Location: index.php');
    exit;
} 