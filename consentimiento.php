<?php
require_once 'vendor/autoload.php';

try {
    $db = new PDO(
        "mysql:host=localhost;dbname=clinica_mentalmente",
        "root",
        ""
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);
    $error = null;

    if (!$token) {
        $error = "Token no proporcionado";
    } else {
        // Verificar token y obtener información de la cita
        $stmt = $db->prepare("
            SELECT c.*, t.fecha_expiracion 
            FROM citas c 
            JOIN tokens_consentimiento t ON c.id = t.cita_id 
            WHERE t.token = ? AND t.fecha_uso IS NULL AND t.fecha_expiracion > NOW()
        ");
        $stmt->execute([$token]);
        $cita = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cita) {
            $error = "El enlace ha expirado o no es válido";
        }
    }
} catch (PDOException $e) {
    $error = "Error de conexión: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consentimiento Informado - Mentalmente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        #signature-pad {
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: #fff;
        }
        .signature-container {
            border: 2px solid #dee2e6;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
            background-color: #f8f9fa;
        }
        .signature-label {
            font-weight: bold;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        .consent-section {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .consent-section h4 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .consent-list {
            padding-left: 20px;
        }
        .consent-list li {
            margin-bottom: 10px;
        }
        .consent-sublist {
            margin-top: 10px;
            padding-left: 30px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php else: ?>
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            <h2 class="text-center mb-0">Consentimiento Informado para Atención Psicológica</h2>
                        </div>
                        <div class="card-body">
                            <div class="consent-section">
                                <h4>Información del Paciente</h4>
                                <p><strong>Nombre:</strong> <?php echo htmlspecialchars($cita['nombre_paciente']); ?></p>
                                <p><strong>Fecha de Cita:</strong> <?php echo date('d/m/Y H:i', strtotime($cita['fecha_cita'])); ?></p>
                                <p><strong>Modalidad:</strong> <?php echo htmlspecialchars($cita['modalidad']); ?></p>
                            </div>

                            <div class="consent-section">
                                <h4>Propósito y Naturaleza del Servicio</h4>
                                <p>Este documento tiene como objetivo informarle sobre la naturaleza de la atención psicológica que recibirá y obtener su consentimiento informado para proceder con la misma. La psicoterapia es un proceso de evaluación, diagnóstico y tratamiento que aborda diferentes aspectos de la salud mental y el bienestar emocional.</p>
                            </div>

                            <div class="consent-section">
                                <h4>Declaración de Consentimiento</h4>
                                <p>Por medio del presente documento, declaro que:</p>
                                <ol class="consent-list">
                                    <li>He sido informado(a) de manera clara y comprensible sobre la naturaleza y el propósito de la atención psicológica que recibiré.</li>
                                    
                                    <li>Entiendo que la información compartida durante las sesiones es estrictamente confidencial y será tratada según las normas éticas profesionales y la legislación vigente sobre protección de datos personales, con las siguientes excepciones:
                                        <ul class="consent-sublist">
                                            <li>Cuando exista riesgo inminente para mi vida o la de terceros</li>
                                            <li>Cuando exista una orden judicial</li>
                                            <li>Cuando sea necesario para proteger a menores de edad o personas vulnerables</li>
                                        </ul>
                                    </li>
                                    
                                    <li>Comprendo que el proceso terapéutico puede abordar aspectos personales y emocionales que podrían generar incomodidad temporal como parte del proceso de sanación y crecimiento.</li>
                                    
                                    <li>He sido informado(a) sobre mi derecho a:
                                        <ul class="consent-sublist">
                                            <li>Hacer preguntas sobre el proceso terapéutico</li>
                                            <li>Decidir sobre las intervenciones sugeridas</li>
                                            <li>Solicitar informes sobre mi proceso</li>
                                            <li>Finalizar la terapia cuando lo considere necesario</li>
                                        </ul>
                                    </li>
                                    
                                    <li>Autorizo el registro y almacenamiento de la información clínica necesaria para mi tratamiento, entendiendo que esta será manejada con estricta confidencialidad y seguridad.</li>
                                    
                                    <li>En el caso de sesiones virtuales, me comprometo a:
                                        <ul class="consent-sublist">
                                            <li>Garantizar un espacio privado y libre de interrupciones</li>
                                            <li>Mantener una conexión a internet estable</li>
                                            <li>No realizar grabaciones de las sesiones</li>
                                        </ul>
                                    </li>
                                </ol>
                            </div>

                            <div class="consent-section">
                                <h4>Honorarios y Política de Cancelación</h4>
                                <p>He sido informado(a) sobre los honorarios profesionales y la política de cancelación de citas. Entiendo que debo notificar con al menos 24 horas de anticipación si necesito cancelar o reprogramar una cita.</p>
                            </div>

                            <form id="consentimientoForm" action="procesar_consentimiento.php" method="POST">
                                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                <input type="hidden" name="firma" id="firmaInput">
                                
                                <div class="consent-section">
                                    <div class="form-check mb-4">
                                        <input class="form-check-input" type="checkbox" id="aceptar" required>
                                        <label class="form-check-label" for="aceptar">
                                            He leído, entendido y acepto todas las condiciones mencionadas en este consentimiento informado
                                        </label>
                                    </div>
                                    
                                    <div class="signature-container">
                                        <div class="signature-label">Por favor, firme aquí para confirmar su consentimiento:</div>
                                        <canvas id="signature-pad" class="w-100" height="200"></canvas>
                                        <div class="mt-3">
                                            <button type="button" class="btn btn-secondary btn-sm" id="clear">Limpiar Firma</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">Firmar y Aceptar Consentimiento</button>
                                    <a href="#" class="btn btn-outline-secondary">Cancelar</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <script>
        const canvas = document.getElementById('signature-pad');
        const signaturePad = new SignaturePad(canvas);
        const clearButton = document.getElementById('clear');
        const form = document.getElementById('consentimientoForm');
        const firmaInput = document.getElementById('firmaInput');
        const checkboxAceptar = document.getElementById('aceptar');

        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.height * ratio;
            canvas.getContext("2d").scale(ratio, ratio);
            signaturePad.clear();
        }

        window.addEventListener("resize", resizeCanvas);
        resizeCanvas();

        clearButton.addEventListener('click', () => {
            signaturePad.clear();
        });

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            
            if (!checkboxAceptar.checked) {
                alert('Por favor, confirme que ha leído y acepta los términos del consentimiento');
                return;
            }
            
            if (signaturePad.isEmpty()) {
                alert('Por favor, proporcione su firma');
                return;
            }
            
            firmaInput.value = signaturePad.toDataURL();
            form.submit();
        });
    </script>
</body>
</html> 