<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enlace Inválido - Mentalmente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
        }
        .error-container {
            text-align: center;
            padding: 40px;
            max-width: 600px;
        }
        .error-icon {
            font-size: 64px;
            color: #dc3545;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h2>Enlace Inválido o Expirado</h2>
        <p class="text-muted mt-3">
            El enlace para firmar el consentimiento informado es inválido o ha expirado. 
            Esto puede deberse a que:
        </p>
        <ul class="text-start text-muted mt-3">
            <li>El enlace ya fue utilizado para firmar el consentimiento</li>
            <li>Han pasado más de 24 horas desde que se generó el enlace</li>
            <li>El enlace es incorrecto</li>
        </ul>
        <p class="mt-4">
            Por favor, contacte a la clínica para obtener un nuevo enlace de firma.
        </p>
        <a href="/" class="btn btn-primary mt-3">Volver al Inicio</a>
    </div>
</body>
</html> 