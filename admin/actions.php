<?php
session_start();
require_once '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Verificar si el administrador está logueado
if (!isset($_SESSION['admin_id'])) {
    die(json_encode(['error' => 'No autorizado']));
}

try {
    $db = new PDO(
        "mysql:host=localhost;dbname=clinica_mentalmente",
        "root",
        ""
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_POST['action'] ?? '';
    $response = [];

    switch ($action) {
        case 'get_cita_details':
            $cita_id = filter_input(INPUT_POST, 'cita_id', FILTER_SANITIZE_NUMBER_INT);
            $stmt = $db->prepare("
                SELECT c.*, cons.pdf_path, cons.fecha_firma, tc.token, tc.fecha_expiracion
                FROM citas c
                LEFT JOIN consentimientos cons ON c.id = cons.id_cita
                LEFT JOIN tokens_consentimiento tc ON c.id = tc.cita_id
                WHERE c.id = ?
            ");
            $stmt->execute([$cita_id]);
            $response = $stmt->fetch(PDO::FETCH_ASSOC);
            break;

        case 'reenviar_consentimiento':
            $cita_id = filter_input(INPUT_POST, 'cita_id', FILTER_SANITIZE_NUMBER_INT);
            
            // Obtener datos de la cita
            $stmt = $db->prepare("SELECT nombre_paciente, correo_paciente FROM citas WHERE id = ?");
            $stmt->execute([$cita_id]);
            $cita = $stmt->fetch(PDO::FETCH_ASSOC);

            // Generar nuevo token
            $token = bin2hex(random_bytes(32));
            
            // Actualizar o insertar token
            $stmt = $db->prepare("
                INSERT INTO tokens_consentimiento (cita_id, token, fecha_expiracion) 
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
                ON DUPLICATE KEY UPDATE 
                    token = VALUES(token),
                    fecha_expiracion = VALUES(fecha_expiracion)
            ");
            $stmt->execute([$cita_id, $token]);

            // Generar enlace
            $enlace_consentimiento = "http://" . $_SERVER['HTTP_HOST'] . "/mentalmente/consentimiento.php?token=" . $token;
            
            // Actualizar enlace en la cita
            $stmt = $db->prepare("UPDATE citas SET enlace_consentimiento = ? WHERE id = ?");
            $stmt->execute([$enlace_consentimiento, $cita_id]);

            // Enviar correo
            $mail_config = require_once '../config/mail_config.php';
            $mail = new PHPMailer(true);
            
            try {
                $mail->isSMTP();
                $mail->Host = $mail_config['host'];
                $mail->SMTPAuth = true;
                $mail->Username = $mail_config['username'];
                $mail->Password = $mail_config['password'];
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $mail_config['port'];
                $mail->setFrom($mail_config['username'], $mail_config['from_name']);
                $mail->addAddress($cita['correo_paciente'], $cita['nombre_paciente']);
                $mail->CharSet = 'UTF-8';
                $mail->isHTML(true);
                $mail->Subject = "Nuevo enlace para Consentimiento Informado - Mentalmente";
                
                $mensaje = "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <h2>Nuevo enlace para Consentimiento Informado</h2>
                    <p>Estimado/a {$cita['nombre_paciente']},</p>
                    <p>Se ha generado un nuevo enlace para firmar el consentimiento informado.</p>
                    <p style='margin: 20px 0;'>
                        <a href='{$enlace_consentimiento}' 
                           style='background-color: #4CAF50; 
                                  color: white; 
                                  padding: 12px 25px; 
                                  text-decoration: none; 
                                  border-radius: 5px; 
                                  display: inline-block;'>
                            Firmar Consentimiento Informado
                        </a>
                    </p>
                    <p><strong>Importante:</strong> Este enlace expirará en 24 horas.</p>
                    <br>
                    <p>Atentamente,<br>
                    Clínica Mentalmente</p>
                </body>
                </html>";

                $mail->Body = $mensaje;
                $mail->AltBody = strip_tags($mensaje);
                $mail->send();
                
                $response = ['success' => true, 'message' => 'Enlace reenviado correctamente'];
            } catch (Exception $e) {
                $response = ['error' => 'Error al enviar el correo: ' . $mail->ErrorInfo];
            }
            break;

        case 'export_excel':
            require_once '../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php';
            require_once '../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Writer/Xlsx.php';

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Encabezados
            $headers = ['Paciente', 'Correo', 'Fecha', 'Modalidad', 'Forma de Pago', 'Estado', 'Consentimiento'];
            $sheet->fromArray([$headers], NULL, 'A1');

            // Datos
            $stmt = $db->query("
                SELECT c.nombre_paciente, c.correo_paciente, c.fecha_cita, 
                       c.modalidad, c.forma_pago, c.estado,
                       CASE 
                           WHEN cons.pdf_path IS NOT NULL THEN 'Firmado'
                           WHEN c.enlace_consentimiento IS NOT NULL THEN 'Pendiente'
                           ELSE 'No disponible'
                       END as estado_consentimiento
                FROM citas c
                LEFT JOIN consentimientos cons ON c.id = cons.id_cita
                ORDER BY c.fecha_cita DESC
            ");
            $row = 2;
            while ($cita = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $sheet->fromArray([array_values($cita)], NULL, 'A' . $row);
                $row++;
            }

            // Formato
            foreach(range('A','G') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Guardar archivo
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $filename = 'citas_' . date('Y-m-d_H-i-s') . '.xlsx';
            $filepath = '../storage/exports/' . $filename;
            $writer->save($filepath);

            $response = [
                'success' => true,
                'file' => $filename,
                'download_url' => '/mentalmente/storage/exports/' . $filename
            ];
            break;

        default:
            $response = ['error' => 'Acción no válida'];
    }

    echo json_encode($response);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
} 