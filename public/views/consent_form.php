<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma de Consentimiento Informado - Mentalmente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <style>
        .signature-container {
            border: 1px solid #ccc;
            border-radius: 4px;
            margin: 20px 0;
        }
        #signature-pad {
            width: 100%;
            height: 200px;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Consentimiento Informado</h3>
                    </div>
                    <div class="card-body">
                        <h4>Información de la Cita</h4>
                        <p><strong>Paciente:</strong> <?php echo htmlspecialchars($appointment['name']); ?></p>
                        <p><strong>Fecha y Hora:</strong> <?php echo htmlspecialchars($appointment['date_time']); ?></p>
                        <p><strong>Modalidad:</strong> <?php echo htmlspecialchars($appointment['modality']); ?></p>

                        <hr>

                        <h4>Declaración de Consentimiento</h4>
                        <p>Yo, <?php echo htmlspecialchars($appointment['name']); ?>, declaro que:</p>
                        <ol>
                            <li>He sido informado sobre la naturaleza y propósito de la consulta psicológica.</li>
                            <li>Entiendo que la información compartida es confidencial y será tratada según las leyes vigentes.</li>
                            <li>Autorizo el registro y almacenamiento de la información clínica necesaria.</li>
                            <li>He sido informado sobre mis derechos como paciente.</li>
                            <li>Acepto participar voluntariamente en el proceso terapéutico.</li>
                        </ol>

                        <div class="signature-container">
                            <canvas id="signature-pad"></canvas>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button class="btn btn-secondary" id="clear-signature">Limpiar Firma</button>
                            <button class="btn btn-primary" id="save-consent">Firmar y Enviar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const canvas = document.getElementById('signature-pad');
            const signaturePad = new SignaturePad(canvas, {
                backgroundColor: 'rgb(255, 255, 255)'
            });

            // Adjust canvas size
            function resizeCanvas() {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext("2d").scale(ratio, ratio);
                signaturePad.clear();
            }

            window.addEventListener("resize", resizeCanvas);
            resizeCanvas();

            // Clear signature
            document.getElementById('clear-signature').addEventListener('click', function() {
                signaturePad.clear();
            });

            // Save consent
            document.getElementById('save-consent').addEventListener('click', function() {
                if (signaturePad.isEmpty()) {
                    alert('Por favor firme el consentimiento antes de enviarlo.');
                    return;
                }

                const signatureData = signaturePad.toDataURL();
                
                fetch('/consent/sign', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        token: '<?php echo $_GET["token"]; ?>',
                        signature: signatureData
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.message) {
                        alert('Consentimiento firmado exitosamente.');
                        window.location.href = '/consent/success';
                    } else {
                        alert('Error al guardar el consentimiento: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al procesar la firma.');
                });
            });
        });
    </script>
</body>
</html> 