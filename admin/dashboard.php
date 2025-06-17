<?php
session_start();

// Verificar si el administrador está logueado
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Cargar configuración de correo
$mail_config = require_once '../config/mail_config.php';

try {
    $db = new PDO(
        "mysql:host=localhost;dbname=clinica_mentalmente",
        "root",
        ""
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Procesar acciones de citas
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['cita_id'])) {
        $cita_id = filter_input(INPUT_POST, 'cita_id', FILTER_SANITIZE_NUMBER_INT);
        $action = $_POST['action'];

        if ($action === 'aceptar' || $action === 'rechazar') {
            $estado = ($action === 'aceptar') ? 'aceptada' : 'rechazada';
            $stmt = $db->prepare("UPDATE citas SET estado = ? WHERE id = ?");
            $stmt->execute([$estado, $cita_id]);

            // Obtener datos de la cita para el correo
            $stmt = $db->prepare("SELECT nombre_paciente, correo_paciente, fecha_cita, modalidad FROM citas WHERE id = ?");
            $stmt->execute([$cita_id]);
            $cita = $stmt->fetch(PDO::FETCH_ASSOC);

            // Si la cita fue aceptada, generar token para consentimiento
            if ($estado === 'aceptada') {
                // Generar token único
                $token = bin2hex(random_bytes(32));
                
                // Guardar token en la base de datos
                $stmt = $db->prepare("INSERT INTO tokens_consentimiento (cita_id, token, fecha_expiracion) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
                $stmt->execute([$cita_id, $token]);
                
                // Generar enlace de consentimiento
                $enlace_consentimiento = "http://" . $_SERVER['HTTP_HOST'] . "/mentalmente/consentimiento.php?token=" . $token;
                
                // Actualizar la cita con el enlace
                $stmt = $db->prepare("UPDATE citas SET enlace_consentimiento = ? WHERE id = ?");
                $stmt->execute([$enlace_consentimiento, $cita_id]);
            }

            // Configurar PHPMailer
            $mail = new PHPMailer(true);
            try {
                // Configuración del servidor
                $mail->isSMTP();
                $mail->Host = $mail_config['host'];
                $mail->SMTPAuth = true;
                $mail->Username = $mail_config['username'];
                $mail->Password = $mail_config['password'];
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $mail_config['port'];

                // Configuración del correo
                $mail->setFrom($mail_config['username'], $mail_config['from_name']);
                $mail->addAddress($cita['correo_paciente'], $cita['nombre_paciente']);
                $mail->CharSet = 'UTF-8';

                // Contenido
                $mail->isHTML(true);
                $mail->Subject = "Estado de tu cita - Mentalmente";
                
                $mensaje = "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <h2>¡Su cita ha sido confirmada!</h2>
                    <p>Estimado/a {$cita['nombre_paciente']},</p>
                    <p>Nos complace informarle que su cita ha sido <strong>aceptada</strong>.</p>
                    <p>Detalles de su cita:</p>
                    <ul>
                        <li><strong>Fecha:</strong> " . date('d/m/Y H:i', strtotime($cita['fecha_cita'])) . "</li>
                        <li><strong>Modalidad:</strong> {$cita['modalidad']}</li>
                    </ul>
                    <p><strong>Paso importante:</strong> Para completar el proceso de agendamiento, necesitamos que firme el consentimiento informado haciendo clic en el siguiente botón:</p>
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
                    <p><strong>Importante:</strong> Este enlace expirará en 24 horas. Por favor, complete la firma antes de ese tiempo.</p>
                    <p>Si tiene alguna pregunta o necesita reprogramar su cita, no dude en contactarnos.</p>
                    <br>
                    <p>Atentamente,<br>
                    Clínica Mentalmente</p>
                </body>
                </html>";

                $mail->Body = $mensaje;
                $mail->AltBody = strip_tags($mensaje);

                $mail->send();
                $mensaje_correo = "Correo enviado correctamente a " . $cita['correo_paciente'];
            } catch (Exception $e) {
                $mensaje_correo = "Error al enviar el correo: {$mail->ErrorInfo}";
            }
        }
    }

    // Obtener estadísticas
    $stats = [
        'total_citas' => $db->query("SELECT COUNT(*) FROM citas")->fetchColumn(),
        'citas_pendientes' => $db->query("SELECT COUNT(*) FROM citas WHERE estado = 'pendiente'")->fetchColumn(),
        'citas_aceptadas' => $db->query("SELECT COUNT(*) FROM citas WHERE estado = 'aceptada'")->fetchColumn(),
        'consentimientos_firmados' => $db->query("SELECT COUNT(*) FROM consentimientos")->fetchColumn()
    ];

    // Obtener lista de citas con información de consentimientos
    $stmt = $db->query("
        SELECT 
            c.id,
            c.nombre_paciente,
            c.correo_paciente,
            c.fecha_cita,
            c.modalidad,
            c.forma_pago,
            c.estado,
            c.enlace_consentimiento,
            cons.pdf_path,
            cons.fecha_firma
        FROM citas c
        LEFT JOIN consentimientos cons ON c.id = cons.id_cita
        ORDER BY c.fecha_cita DESC
    ");
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrativo - Mentalmente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .table th {
            white-space: nowrap;
        }
        .modal-xl {
            max-width: 90%;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-heart-pulse-fill me-2"></i>
                Mentalmente - Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#dashboard">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#citas">
                            <i class="bi bi-calendar-check"></i> Citas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#consentimientos">
                            <i class="bi bi-file-earmark-text"></i> Consentimientos
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <span class="navbar-text me-3 text-white">
                        <i class="bi bi-person-circle me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['admin_nombre']); ?>
                    </span>
                    <a href="logout.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <?php if (isset($mensaje_correo)): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php echo $mensaje_correo; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-calendar2"></i> Total Citas
                        </h5>
                        <h2 class="mb-0"><?php echo $stats['total_citas']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-warning text-dark">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-clock"></i> Pendientes
                        </h5>
                        <h2 class="mb-0"><?php echo $stats['citas_pendientes']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-check-circle"></i> Aceptadas
                        </h5>
                        <h2 class="mb-0"><?php echo $stats['citas_aceptadas']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-file-earmark-check"></i> Consentimientos
                        </h5>
                        <h2 class="mb-0"><?php echo $stats['consentimientos_firmados']; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros y Búsqueda -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <select class="form-select" id="filtroEstado">
                            <option value="">Todos los estados</option>
                            <option value="pendiente">Pendientes</option>
                            <option value="aceptada">Aceptadas</option>
                            <option value="rechazada">Rechazadas</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filtroModalidad">
                            <option value="">Todas las modalidades</option>
                            <option value="presencial">Presencial</option>
                            <option value="virtual">Virtual</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control" id="busqueda" placeholder="Buscar por nombre o correo...">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" onclick="aplicarFiltros()">
                            <i class="bi bi-search"></i> Filtrar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Citas -->
        <div class="card">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Gestión de Citas</h3>
                    <button class="btn btn-success" onclick="exportarExcel()">
                        <i class="bi bi-file-earmark-excel"></i> Exportar a Excel
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Paciente</th>
                                <th>Correo</th>
                                <th>Fecha</th>
                                <th>Modalidad</th>
                                <th>Forma de Pago</th>
                                <th>Estado</th>
                                <th>Consentimiento</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($citas as $cita): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cita['nombre_paciente']); ?></td>
                                <td><?php echo htmlspecialchars($cita['correo_paciente']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($cita['fecha_cita'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $cita['modalidad'] === 'presencial' ? 'primary' : 'info'; ?>">
                                        <?php echo htmlspecialchars($cita['modalidad']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($cita['forma_pago']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $cita['estado'] === 'pendiente' ? 'warning' : 
                                            ($cita['estado'] === 'aceptada' ? 'success' : 'danger'); 
                                    ?>">
                                        <?php echo htmlspecialchars($cita['estado']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($cita['pdf_path']): ?>
                                        <button class="btn btn-info btn-sm" onclick="verConsentimiento('<?php echo htmlspecialchars($cita['pdf_path']); ?>')">
                                            <i class="bi bi-file-earmark-pdf"></i> Ver PDF
                                        </button>
                                    <?php elseif ($cita['enlace_consentimiento']): ?>
                                        <span class="badge bg-warning text-dark">Pendiente de firma</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No disponible</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <?php if ($cita['estado'] === 'pendiente'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="cita_id" value="<?php echo $cita['id']; ?>">
                                                <button type="submit" name="action" value="aceptar" class="btn btn-success btn-sm" title="Aceptar">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                                <button type="submit" name="action" value="rechazar" class="btn btn-danger btn-sm" title="Rechazar">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <button class="btn btn-primary btn-sm" onclick="verDetalles(<?php echo $cita['id']; ?>)" title="Ver detalles">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if ($cita['estado'] === 'aceptada'): ?>
                                            <button class="btn btn-warning btn-sm" onclick="reenviarConsentimiento(<?php echo $cita['id']; ?>)" title="Reenviar consentimiento">
                                                <i class="bi bi-envelope"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para ver PDF -->
    <div class="modal fade" id="pdfModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Consentimiento Informado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <iframe id="pdfViewer" style="width: 100%; height: 80vh;" frameborder="0"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function verConsentimiento(pdfPath) {
            if (pdfPath.startsWith('storage/')) {
                pdfPath = '/mentalmente/' + pdfPath;
            }
            document.getElementById('pdfViewer').src = pdfPath;
            new bootstrap.Modal(document.getElementById('pdfModal')).show();
        }

        function aplicarFiltros() {
            const estado = document.getElementById('filtroEstado').value;
            const modalidad = document.getElementById('filtroModalidad').value;
            const busqueda = document.getElementById('busqueda').value.toLowerCase();

            document.querySelectorAll('tbody tr').forEach(tr => {
                let mostrar = true;

                if (estado && !tr.querySelector(`td:nth-child(6)`).textContent.trim().includes(estado)) {
                    mostrar = false;
                }

                if (modalidad && !tr.querySelector(`td:nth-child(4)`).textContent.trim().includes(modalidad)) {
                    mostrar = false;
                }

                if (busqueda) {
                    const nombre = tr.querySelector('td:nth-child(1)').textContent.toLowerCase();
                    const correo = tr.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    if (!nombre.includes(busqueda) && !correo.includes(busqueda)) {
                        mostrar = false;
                    }
                }

                tr.style.display = mostrar ? '' : 'none';
            });
        }

        function exportarExcel() {
            $.ajax({
                url: 'actions.php',
                method: 'POST',
                data: {
                    action: 'export_excel'
                },
                success: function(response) {
                    const data = JSON.parse(response);
                    if (data.success) {
                        window.location.href = data.download_url;
                    } else {
                        alert('Error al exportar: ' + data.error);
                    }
                },
                error: function() {
                    alert('Error al realizar la exportación');
                }
            });
        }

        function verDetalles(citaId) {
            $.ajax({
                url: 'actions.php',
                method: 'POST',
                data: {
                    action: 'get_cita_details',
                    cita_id: citaId
                },
                success: function(response) {
                    const data = JSON.parse(response);
                    if (data.error) {
                        alert('Error: ' + data.error);
                        return;
                    }

                    // Crear contenido HTML para el modal
                    let detallesHTML = `
                        <div class="modal fade" id="detallesModal" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Detalles de la Cita</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="mb-3">Información del Paciente</h6>
                                                <p><strong>Nombre:</strong> ${data.nombre_paciente}</p>
                                                <p><strong>Correo:</strong> ${data.correo_paciente}</p>
                                                <p><strong>Fecha:</strong> ${new Date(data.fecha_cita).toLocaleString()}</p>
                                                <p><strong>Modalidad:</strong> ${data.modalidad}</p>
                                                <p><strong>Forma de Pago:</strong> ${data.forma_pago}</p>
                                                <p><strong>Estado:</strong> ${data.estado}</p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="mb-3">Estado del Consentimiento</h6>`;

                    if (data.pdf_path) {
                        detallesHTML += `
                            <p><strong>Estado:</strong> <span class="badge bg-success">Firmado</span></p>
                            <p><strong>Fecha de Firma:</strong> ${new Date(data.fecha_firma).toLocaleString()}</p>
                            <button class="btn btn-info btn-sm" onclick="verConsentimiento('${data.pdf_path}')">
                                <i class="bi bi-file-earmark-pdf"></i> Ver PDF
                            </button>`;
                    } else if (data.enlace_consentimiento) {
                        detallesHTML += `
                            <p><strong>Estado:</strong> <span class="badge bg-warning text-dark">Pendiente de Firma</span></p>
                            <p><strong>Enlace:</strong> <a href="${data.enlace_consentimiento}" target="_blank">Ver enlace</a></p>
                            <p><strong>Expira:</strong> ${new Date(data.fecha_expiracion).toLocaleString()}</p>`;
                    } else {
                        detallesHTML += `
                            <p><strong>Estado:</strong> <span class="badge bg-secondary">No disponible</span></p>`;
                    }

                    detallesHTML += `
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>`;

                    // Eliminar modal anterior si existe
                    $('#detallesModal').remove();
                    
                    // Agregar nuevo modal y mostrarlo
                    $('body').append(detallesHTML);
                    new bootstrap.Modal(document.getElementById('detallesModal')).show();
                },
                error: function() {
                    alert('Error al obtener los detalles de la cita');
                }
            });
        }

        function reenviarConsentimiento(citaId) {
            if (confirm('¿Desea reenviar el enlace del consentimiento informado?')) {
                $.ajax({
                    url: 'actions.php',
                    method: 'POST',
                    data: {
                        action: 'reenviar_consentimiento',
                        cita_id: citaId
                    },
                    success: function(response) {
                        const data = JSON.parse(response);
                        if (data.success) {
                            alert('Enlace reenviado correctamente');
                            location.reload();
                        } else {
                            alert('Error: ' + data.error);
                        }
                    },
                    error: function() {
                        alert('Error al reenviar el consentimiento');
                    }
                });
            }
        }

        // Inicializar tooltips de Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    </script>
</body>
</html> 