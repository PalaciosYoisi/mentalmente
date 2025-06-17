<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;
use App\Models\Appointment;
use App\Models\Consent;
use App\Services\EmailService;
use App\Services\PDFService;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Basic routing
$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Remove query string and base path if any
$request = strtok($request, '?');
$request = str_replace('/mentalmente', '', $request);

// Define routes
switch ($request) {
    case '/':
        require __DIR__ . '/views/home.php';
        break;
        
    case '/appointment/create':
        if ($method === 'POST') {
            $appointment = new Appointment();
            $data = [
                'patient_id' => $_POST['patient_id'],
                'date_time' => $_POST['date_time'],
                'modality' => $_POST['modality'],
                'payment_method' => $_POST['payment_method']
            ];
            
            if ($appointment->create($data)) {
                http_response_code(201);
                echo json_encode(['message' => 'Appointment created successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create appointment']);
            }
        }
        break;
        
    case '/appointment/confirm':
        if ($method === 'POST') {
            $appointment = new Appointment();
            $id = $_POST['appointment_id'];
            
            if ($appointment->updateStatus($id, 'confirmed')) {
                $appointmentData = $appointment->getById($id);
                
                // Generate unique token for consent signing
                $token = bin2hex(random_bytes(32));
                $db = Connection::getInstance()->getConnection();
                $stmt = $db->prepare("INSERT INTO consent_tokens (appointment_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
                $stmt->execute([$id, $token]);
                
                // Send confirmation email with consent link
                $emailService = new EmailService();
                if ($emailService->sendConsentLink($appointmentData, $token)) {
                    http_response_code(200);
                    echo json_encode(['message' => 'Appointment confirmed and email sent']);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to send confirmation email']);
                }
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to confirm appointment']);
            }
        }
        break;
        
    case '/consent/sign':
        if ($method === 'GET') {
            $token = $_GET['token'] ?? '';
            
            // Verify token
            $db = Connection::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT a.*, p.name, p.email 
                FROM consent_tokens ct
                JOIN appointments a ON ct.appointment_id = a.id
                JOIN patients p ON a.patient_id = p.id
                WHERE ct.token = ? AND ct.expires_at > NOW() AND ct.used_at IS NULL
            ");
            $stmt->execute([$token]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($appointment) {
                require __DIR__ . '/views/consent_form.php';
            } else {
                http_response_code(404);
                require __DIR__ . '/views/invalid_token.php';
            }
        } elseif ($method === 'POST') {
            $token = $_POST['token'];
            $signatureData = $_POST['signature'];
            
            // Verify token again
            $db = Connection::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT ct.appointment_id, a.patient_id 
                FROM consent_tokens ct
                JOIN appointments a ON ct.appointment_id = a.id
                WHERE ct.token = ? AND ct.expires_at > NOW() AND ct.used_at IS NULL
            ");
            $stmt->execute([$token]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($data) {
                // Save signature image
                $signatureImage = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $signatureData));
                $signaturePath = 'storage/signatures/' . uniqid() . '.png';
                file_put_contents(__DIR__ . '/../' . $signaturePath, $signatureImage);
                
                // Generate PDF
                $pdfService = new PDFService();
                $appointment = (new Appointment())->getById($data['appointment_id']);
                $pdfPath = $pdfService->generateConsentPDF($appointment, $signaturePath);
                
                // Save consent record
                $consent = new Consent();
                $saved = $consent->create([
                    'appointment_id' => $data['appointment_id'],
                    'patient_id' => $data['patient_id'],
                    'signature_path' => $signaturePath,
                    'pdf_path' => $pdfPath,
                    'ip_address' => $_SERVER['REMOTE_ADDR']
                ]);
                
                if ($saved) {
                    // Mark token as used
                    $stmt = $db->prepare("UPDATE consent_tokens SET used_at = NOW() WHERE token = ?");
                    $stmt->execute([$token]);
                    
                    http_response_code(200);
                    echo json_encode(['message' => 'Consent signed successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to save consent']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid or expired token']);
            }
        }
        break;
        
    default:
        http_response_code(404);
        require __DIR__ . '/views/404.php';
        break;
} 