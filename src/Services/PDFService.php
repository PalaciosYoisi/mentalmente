<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

class PDFService {
    private $dompdf;

    public function __construct() {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        
        $this->dompdf = new Dompdf($options);
    }

    public function generateConsentPDF($appointment, $signaturePath) {
        $html = $this->getConsentTemplate($appointment, $signaturePath);
        
        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', 'portrait');
        $this->dompdf->render();

        $output = $this->dompdf->output();
        $filename = 'consent_' . $appointment['id'] . '_' . date('Y-m-d_H-i-s') . '.pdf';
        $filepath = __DIR__ . '/../../storage/consents/' . $filename;
        
        file_put_contents($filepath, $output);
        
        return $filename;
    }

    private function getConsentTemplate($appointment, $signaturePath) {
        return "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='utf-8'>
                <title>Consentimiento Informado - Mentalmente</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                    .header { text-align: center; margin-bottom: 30px; }
                    .signature { margin-top: 50px; }
                    .footer { margin-top: 30px; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='header'>
                    <h1>Consentimiento Informado</h1>
                    <h2>Mentalmente - Servicios de Psicología</h2>
                </div>

                <p>Fecha: " . date('Y-m-d') . "</p>
                <p>Paciente: {$appointment['name']}</p>
                <p>Modalidad de Atención: {$appointment['modality']}</p>

                <h3>Declaración de Consentimiento</h3>

                <p>Yo, {$appointment['name']}, identificado con documento de identidad, declaro que:</p>

                <ol>
                    <li>He sido informado sobre la naturaleza y propósito de la consulta psicológica.</li>
                    <li>Entiendo que la información compartida es confidencial y será tratada según las leyes vigentes.</li>
                    <li>Autorizo el registro y almacenamiento de la información clínica necesaria.</li>
                    <li>He sido informado sobre mis derechos como paciente.</li>
                    <li>Acepto participar voluntariamente en el proceso terapéutico.</li>
                </ol>

                <div class='signature'>
                    <p>Firma del Paciente:</p>
                    <img src='{$signaturePath}' width='200px' height='100px'>
                    <p>Fecha y Hora de Firma: " . date('Y-m-d H:i:s') . "</p>
                </div>

                <div class='footer'>
                    <p>Este documento fue firmado digitalmente y es legalmente válido.</p>
                    <p>IP de Firma: {$_SERVER['REMOTE_ADDR']}</p>
                </div>
            </body>
            </html>
        ";
    }
} 