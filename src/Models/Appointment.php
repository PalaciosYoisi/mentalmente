<?php

namespace App\Models;

use App\Database\Connection;
use PDO;

class Appointment {
    private $db;

    public function __construct() {
        $this->db = Connection::getInstance()->getConnection();
    }

    public function create($data) {
        $sql = "INSERT INTO appointments (
            patient_id, 
            date_time, 
            modality, 
            payment_method, 
            status
        ) VALUES (
            :patient_id, 
            :date_time, 
            :modality, 
            :payment_method, 
            'pending'
        )";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'patient_id' => $data['patient_id'],
            'date_time' => $data['date_time'],
            'modality' => $data['modality'],
            'payment_method' => $data['payment_method']
        ]);
    }

    public function updateStatus($id, $status) {
        $sql = "UPDATE appointments SET status = :status WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function getPendingAppointments() {
        $sql = "SELECT a.*, p.name, p.email 
                FROM appointments a 
                JOIN patients p ON a.patient_id = p.id 
                WHERE a.status = 'pending'";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $sql = "SELECT a.*, p.name, p.email 
                FROM appointments a 
                JOIN patients p ON a.patient_id = p.id 
                WHERE a.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
} 