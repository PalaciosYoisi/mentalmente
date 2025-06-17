<?php
require_once 'vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['token'], $_POST['firma'])) {
    header('Location: index.php');
    exit;
}

// Definir rutas absolutas
$base_path = __DIR__;
$storage_path = $base_path . DIRECTORY_SEPARATOR . 'storage';
$signatures_path = $storage_path . DIRECTORY_SEPARATOR . 'signatures';
$consents_path = $storage_path . DIRECTORY_SEPARATOR . 'consents';

// Cargar configuración de correo
$mail_config = require_once 'config/mail_config.php';

try {
    $db = new PDO(
        "mysql:host=localhost;dbname=clinica_mentalmente",
        "root",
        ""
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $token = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_STRING);
    $firma = $_POST['firma'];

    // Verificar token y obtener información
    $stmt = $db->prepare("
        SELECT c.*, t.id as token_id, t.token
        FROM citas c 
        JOIN tokens_consentimiento t ON c.id = t.cita_id 
        WHERE t.token = ? AND t.fecha_uso IS NULL AND t.fecha_expiracion > NOW()
    ");
    $stmt->execute([$token]);
    $cita = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cita) {
        throw new Exception("Token inválido o expirado");
    }

    // Iniciar transacción
    $db->beginTransaction();

    try {
        // Guardar la firma como imagen
        $firma_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $firma));
        $firma_nombre = uniqid() . '_firma.png';
        $firma_ruta = $signatures_path . DIRECTORY_SEPARATOR . $firma_nombre;
        file_put_contents($firma_ruta, $firma_data);

        // Crear PDF del consentimiento
        require_once 'vendor/tecnickcom/tcpdf/tcpdf.php';
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Configuración del PDF
        $pdf->SetCreator('Mentalmente');
        $pdf->SetAuthor('Clínica Mentalmente');
        $pdf->SetTitle('Consentimiento Informado');
        
        // Eliminar cabecera y pie de página por defecto
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Agregar página
        $pdf->AddPage();
        
        // Establecer fuente
        $pdf->SetFont('helvetica', '', 12);

        // Contenido del PDF
        $html = '
            <h1 style="text-align: center; color: #2c3e50;">Consentimiento Informado para Atención Psicológica</h1>
            
            <div style="margin-top: 20px;">
                <h2>Información del Paciente</h2>
                <p><strong>Nombre del Paciente:</strong> ' . $cita['nombre_paciente'] . '</p>
                <p><strong>Fecha de la Consulta:</strong> ' . date('d/m/Y H:i', strtotime($cita['fecha_cita'])) . '</p>
                <p><strong>Modalidad de Atención:</strong> ' . $cita['modalidad'] . '</p>
            </div>

            <div style="margin-top: 20px;">
                <h2>Propósito y Naturaleza del Servicio</h2>
                <p>Este documento tiene como objetivo informarle sobre la naturaleza de la atención psicológica que recibirá y obtener su consentimiento informado para proceder con la misma. La psicoterapia es un proceso de evaluación, diagnóstico y tratamiento que aborda diferentes aspectos de la salud mental y el bienestar emocional.</p>
            </div>

            <div style="margin-top: 20px;">
                <h2>Declaración de Consentimiento</h2>
                <p>Por medio del presente documento, declaro que:</p>
                <ol>
                    <li>He sido informado(a) de manera clara y comprensible sobre la naturaleza y el propósito de la atención psicológica que recibiré.</li>
                    
                    <li>Entiendo que la información compartida durante las sesiones es estrictamente confidencial y será tratada según las normas éticas profesionales y la legislación vigente sobre protección de datos personales, con las siguientes excepciones:
                        <ul>
                            <li>Cuando exista riesgo inminente para mi vida o la de terceros</li>
                            <li>Cuando exista una orden judicial</li>
                            <li>Cuando sea necesario para proteger a menores de edad o personas vulnerables</li>
                        </ul>
                    </li>
                    
                    <li>Comprendo que el proceso terapéutico puede abordar aspectos personales y emocionales que podrían generar incomodidad temporal como parte del proceso de sanación y crecimiento.</li>
                    
                    <li>He sido informado(a) sobre mi derecho a:
                        <ul>
                            <li>Hacer preguntas sobre el proceso terapéutico</li>
                            <li>Decidir sobre las intervenciones sugeridas</li>
                            <li>Solicitar informes sobre mi proceso</li>
                            <li>Finalizar la terapia cuando lo considere necesario</li>
                        </ul>
                    </li>
                    
                    <li>Autorizo el registro y almacenamiento de la información clínica necesaria para mi tratamiento, entendiendo que esta será manejada con estricta confidencialidad y seguridad.</li>
                    
                    <li>En el caso de sesiones virtuales, me comprometo a:
                        <ul>
                            <li>Garantizar un espacio privado y libre de interrupciones</li>
                            <li>Mantener una conexión a internet estable</li>
                            <li>No realizar grabaciones de las sesiones</li>
                        </ul>
                    </li>
                </ol>
            </div>

            <div style="margin-top: 20px;">
                <h2>Honorarios y Política de Cancelación</h2>
                <p>He sido informado(a) sobre los honorarios profesionales y la política de cancelación de citas. Entiendo que debo notificar con al menos 24 horas de anticipación si necesito cancelar o reprogramar una cita.</p>
            </div>

            <div style="margin-top: 20px;">
                <h2>Firma del Consentimiento</h2>
                <p>Al firmar este documento, confirmo que he leído, entendido y aceptado todas las condiciones anteriormente mencionadas.</p>
                <p>Fecha y hora de firma: ' . date('d/m/Y H:i:s') . '</p>
            </div>
            
            <div style="margin-top: 20px;">
                <p><strong>Firma del Paciente:</strong></p>
            </div>
        ';

        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Agregar la firma
        $pdf->Image($firma_ruta, 30, $pdf->GetY(), 60);

        // Guardar el PDF
        $pdf_nombre = uniqid() . '_consentimiento.pdf';
        $pdf_ruta = $consents_path . DIRECTORY_SEPARATOR . $pdf_nombre;
        $pdf->Output($pdf_ruta, 'F');

        // Registrar consentimiento en la base de datos
        $stmt = $db->prepare("
            INSERT INTO consentimientos (id_cita, firma, pdf_path) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$cita['id'], file_get_contents($firma_ruta), 'storage/consents/' . $pdf_nombre]);

        // Marcar token como usado
        $stmt = $db->prepare("
            UPDATE tokens_consentimiento 
            SET fecha_uso = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$cita['token_id']]);

        // Enviar el PDF por correo al paciente
        require 'vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host = $mail_config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $mail_config['username'];
            $mail->Password = $mail_config['password'];
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $mail_config['port'];
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($mail_config['username'], $mail_config['from_name']);
            $mail->addAddress($cita['correo_paciente'], $cita['nombre_paciente']);
            
            $mail->Subject = 'Copia de su Consentimiento Informado Firmado - Clínica Mentalmente';
            $mail->isHTML(true);
            $mail->Body = "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <h2>Consentimiento Informado Firmado</h2>
                    <p>Estimado/a {$cita['nombre_paciente']},</p>
                    <p>Gracias por firmar el consentimiento informado. Adjunto encontrará una copia del documento firmado para sus registros.</p>
                    <p>Detalles de su cita:</p>
                    <ul>
                        <li><strong>Fecha:</strong> " . date('d/m/Y H:i', strtotime($cita['fecha_cita'])) . "</li>
                        <li><strong>Modalidad:</strong> {$cita['modalidad']}</li>
                    </ul>
                    <p>Si tiene alguna pregunta o necesita reprogramar su cita, no dude en contactarnos.</p>
                    <br>
                    <p>Atentamente,<br>
                    Clínica Mentalmente</p>
                </body>
                </html>";
            $mail->AltBody = "Estimado/a {$cita['nombre_paciente']},\n\n"
                          . "Gracias por firmar el consentimiento informado. Adjunto encontrará una copia del documento firmado para sus registros.\n\n"
                          . "Detalles de su cita:\n"
                          . "- Fecha: " . date('d/m/Y H:i', strtotime($cita['fecha_cita'])) . "\n"
                          . "- Modalidad: {$cita['modalidad']}\n\n"
                          . "Si tiene alguna pregunta o necesita reprogramar su cita, no dude en contactarnos.\n\n"
                          . "Atentamente,\n"
                          . "Clínica Mentalmente";
            
            $mail->addAttachment($pdf_ruta);
            
            $mail->send();
            error_log("Correo enviado exitosamente a: " . $cita['correo_paciente']);
        } catch (Exception $e) {
            error_log("Error enviando email a {$cita['correo_paciente']}: {$mail->ErrorInfo}");
        }

        $db->commit();

        // Redirigir a página de éxito
        header('Location: exito.php');
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    // Redirigir a página de error
    header('Location: error.php?mensaje=' . urlencode($e->getMessage()));
    exit;
} 