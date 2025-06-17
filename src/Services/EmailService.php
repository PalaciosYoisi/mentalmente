<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mailer;

    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->setupMailer();
    }

    private function setupMailer() {
        $this->mailer->isSMTP();
        $this->mailer->Host = getenv('MAIL_HOST');
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = getenv('MAIL_USERNAME');
        $this->mailer->Password = getenv('MAIL_PASSWORD');
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = getenv('MAIL_PORT');
        $this->mailer->setFrom(getenv('MAIL_FROM'), getenv('MAIL_FROM_NAME'));
        $this->mailer->isHTML(true);
    }

    public function sendAppointmentConfirmation($appointment) {
        try {
            $this->mailer->addAddress($appointment['email'], $appointment['name']);
            $this->mailer->Subject = 'Confirmación de Cita - Mentalmente';
            
            $body = $this->getAppointmentConfirmationTemplate($appointment);
            $this->mailer->Body = $body;
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Error sending email: " . $e->getMessage());
            return false;
        }
    }

    public function sendConsentLink($appointment, $token) {
        try {
            $this->mailer->addAddress($appointment['email'], $appointment['name']);
            $this->mailer->Subject = 'Firma de Consentimiento Informado - Mentalmente';
            
            $consentUrl = getenv('APP_URL') . "/consent/sign?token=" . $token;
            $body = $this->getConsentEmailTemplate($appointment, $consentUrl);
            $this->mailer->Body = $body;
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Error sending consent email: " . $e->getMessage());
            return false;
        }
    }

    private function getAppointmentConfirmationTemplate($appointment) {
        return "
            <h2>¡Cita Confirmada!</h2>
            <p>Estimado/a {$appointment['name']},</p>
            <p>Su cita ha sido confirmada para el {$appointment['date_time']} en modalidad {$appointment['modality']}.</p>
            <p>Pronto recibirá un correo con el enlace para firmar el consentimiento informado.</p>
            <p>Gracias por confiar en nosotros.</p>
            <p>Equipo Mentalmente</p>
        ";
    }

    private function getConsentEmailTemplate($appointment, $consentUrl) {
        return "
            <h2>Firma de Consentimiento Informado</h2>
            <p>Estimado/a {$appointment['name']},</p>
            <p>Para proceder con su cita, necesitamos que firme el consentimiento informado.</p>
            <p>Por favor haga clic en el siguiente enlace para acceder al formulario de firma:</p>
            <p><a href='{$consentUrl}'>Firmar Consentimiento</a></p>
            <p>Este enlace es único y personal. No lo comparta con nadie.</p>
            <p>Gracias por su confianza.</p>
            <p>Equipo Mentalmente</p>
        ";
    }
} 