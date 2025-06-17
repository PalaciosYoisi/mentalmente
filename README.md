# Mentalmente - Sistema de Gestión de Consentimientos y Citas

Sistema digital para la gestión de consentimientos informados y citas de una clínica psicológica.

## Descripción

Este sistema permite a los pacientes agendar citas psicológicas y gestionar sus consentimientos informados de manera digital. El sistema incluye funcionalidades para:
- Agendar citas (presenciales o virtuales)
- Gestionar consentimientos informados
- Generar PDFs de consentimientos firmados
- Enviar notificaciones por correo electrónico
- Administrar pacientes y sus documentos

## Requisitos del Sistema

- PHP >= 7.4
- MySQL/MariaDB
- Composer
- Servidor web (Apache/Nginx)

## Estructura del Proyecto

```
mentalmente/
├── admin/               # Panel de administración
├── config/             # Archivos de configuración
├── public/             # Archivos públicos (CSS, JS, imágenes)
├── src/                # Código fuente principal
├── storage/            # Almacenamiento de archivos generados
├── vendor/             # Dependencias de Composer
├── .env                # Variables de entorno
├── composer.json       # Configuración de Composer
├── index.php           # Punto de entrada principal
└── README.md           # Este archivo
```

## Base de Datos

El sistema utiliza una base de datos MySQL con las siguientes tablas principales:

1. `citas`
   - Gestiona las citas de los pacientes
   - Campos: id, nombre_paciente, correo_paciente, fecha_cita, modalidad, forma_pago, estado

2. `consentimientos`
   - Almacena los consentimientos firmados
   - Campos: id, id_cita, firma, pdf_path, fecha_firma

3. `pacientes`
   - Información de los pacientes
   - Campos: id, nombre, correo, telefono, fecha_creacion

4. `tokens_consentimiento`
   - Gestiona los tokens para firmar consentimientos
   - Campos: id, cita_id, token, fecha_expiracion, fecha_uso

5. `administradores`
   - Gestiona los usuarios administradores del sistema
   - Campos: id, nombre, correo, password, rol, fecha_creacion, ultimo_acceso

## Dependencias Principales

- phpmailer/phpmailer: Para el envío de correos electrónicos
- dompdf/dompdf: Para la generación de PDFs
- vlucas/phpdotenv: Para la gestión de variables de entorno
- tecnickcom/tcpdf: Para la generación de PDFs adicionales
- phpoffice/phpspreadsheet: Para la manipulación de hojas de cálculo

## Instalación

1. Clonar el repositorio
2. Ejecutar `composer install`
3. Configurar el archivo `.env` con las credenciales de la base de datos
4. Importar la base de datos usando el archivo `clinica_mentalmente.sql`
5. Configurar los permisos de escritura en la carpeta `storage/`

## Funcionalidades Principales

### Sistema de Citas
- Formulario de agendamiento de citas
- Selección de modalidad (presencial/virtual)
- Gestión de métodos de pago
- Notificaciones automáticas

### Sistema de Consentimientos
- Generación de consentimientos informados
- Firma digital de documentos
- Almacenamiento seguro de documentos
- Envío de enlaces para firma

### Panel de Administración
- Gestión de citas
- Visualización de consentimientos
- Administración de pacientes
- Reportes y estadísticas
- Gestión de usuarios administradores

## Seguridad

- Uso de tokens seguros para consentimientos
- Almacenamiento seguro de firmas
- Validación de datos en formularios
- Protección contra inyección SQL
- Gestión de sesiones segura