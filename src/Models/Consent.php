<?php

namespace App\Models;

use App\Database\Connection;
use PDO;

class Consent {
    private $db;

    public function __construct() {
        $this->db = Connection::getInstance()->getConnection();
    }

    public function create($data) {
        $sql = "INSERT INTO consents (
            appointment_id,
            patient_id,
            signature_path,
            pdf_path,
            signed_at,
            ip_address
        ) VALUES (
            :appointment_id,
            :patient_id,
            :signature_path,
            :pdf_path,
            NOW(),
            :ip_address
        )";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'appointment_id' => $data['appointment_id'],
            'patient_id' => $data['patient_id'],
            'signature_path' => $data['signature_path'],
            'pdf_path' => $data['pdf_path'],
            'ip_address' => $data['ip_address']
        ]);
    }

    public function getByAppointmentId($appointmentId) {
        $sql = "SELECT c.*, p.name, p.email 
                FROM consents c 
                JOIN patients p ON c.patient_id = p.id 
                WHERE c.appointment_id = :appointment_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['appointment_id' => $appointmentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function verifySignature($appointmentId) {
        $sql = "SELECT COUNT(*) FROM consents WHERE appointment_id = :appointment_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['appointment_id' => $appointmentId]);
        return $stmt->fetchColumn() > 0;
    }
} 