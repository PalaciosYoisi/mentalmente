<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cita Agendada - Mentalmente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body text-center">
                        <h2 class="text-success mb-4">¡Cita Agendada con Éxito!</h2>
                        <p class="lead">Hemos recibido tu solicitud de cita. En breve recibirás un correo electrónico con los detalles y el formulario de consentimiento informado.</p>
                        <p>Por favor, revisa tu bandeja de entrada y sigue las instrucciones para completar el proceso.</p>
                        <div class="mt-4">
                            <a href="index.php" class="btn btn-primary">Volver al Inicio</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 