<?php
/**
 * Portal de Denuncias EPCO - Servicio de Correo (Gmail SMTP)
 * 
 * Envía correos usando SMTP de Gmail con autenticación.
 * Requiere configurar SMTP_USER y SMTP_PASS en las variables de entorno.
 * Para Gmail, usar una "Contraseña de aplicación" (App Password).
 */

if (!defined('DENUNCIAS_APP')) {
    die('Acceso directo no permitido');
}

// =============================================
// CONFIGURACIÓN SMTP
// =============================================
define('SMTP_ENABLED', filter_var(getenv('SMTP_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN));
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'denuncias@epco.cl');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Canal de Denuncias - EPCO');
define('SMTP_ADMIN_EMAIL', getenv('SMTP_ADMIN_EMAIL') ?: '');

/**
 * Enviar correo usando SMTP de Gmail (socket directo, sin librerías externas)
 */
function sendEmail(string $to, string $subject, string $htmlBody): bool {
    if (!SMTP_ENABLED || empty(SMTP_USER) || empty(SMTP_PASS)) {
        error_log("[Correo] SMTP deshabilitado o sin configurar. To: $to | Subject: $subject");
        return false;
    }

    try {
        $smtp = new SmtpMailer(SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_ENCRYPTION);
        return $smtp->send(SMTP_FROM_EMAIL, SMTP_FROM_NAME, $to, $subject, $htmlBody);
    } catch (Exception $e) {
        error_log("[Correo] Error enviando correo: " . $e->getMessage());
        return false;
    }
}

/**
 * Clase SMTP simple para enviar correos sin dependencias externas
 */
class SmtpMailer {
    private $socket;
    private string $host;
    private int $port;
    private string $user;
    private string $pass;
    private string $encryption;

    public function __construct(string $host, int $port, string $user, string $pass, string $encryption = 'tls') {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->encryption = $encryption;
    }

    public function send(string $fromEmail, string $fromName, string $to, string $subject, string $htmlBody): bool {
        $this->connect();
        $this->authenticate();
        $this->sendMessage($fromEmail, $fromName, $to, $subject, $htmlBody);
        $this->quit();
        return true;
    }

    private function connect(): void {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        if ($this->encryption === 'ssl') {
            $this->socket = stream_socket_client(
                "ssl://{$this->host}:{$this->port}",
                $errno, $errstr, 30,
                STREAM_CLIENT_CONNECT, $context
            );
        } else {
            $this->socket = stream_socket_client(
                "tcp://{$this->host}:{$this->port}",
                $errno, $errstr, 30,
                STREAM_CLIENT_CONNECT, $context
            );
        }

        if (!$this->socket) {
            throw new Exception("No se pudo conectar a {$this->host}:{$this->port} - $errstr ($errno)");
        }

        $this->readResponse(220);
        $this->sendCommand("EHLO " . gethostname(), 250);

        if ($this->encryption === 'tls') {
            $this->sendCommand("STARTTLS", 220);
            $crypto = stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
            if (!$crypto) {
                throw new Exception("Error al iniciar TLS");
            }
            $this->sendCommand("EHLO " . gethostname(), 250);
        }
    }

    private function authenticate(): void {
        $this->sendCommand("AUTH LOGIN", 334);
        $this->sendCommand(base64_encode($this->user), 334);
        $this->sendCommand(base64_encode($this->pass), 235);
    }

    private function sendMessage(string $fromEmail, string $fromName, string $to, string $subject, string $htmlBody): void {
        $this->sendCommand("MAIL FROM:<{$fromEmail}>", 250);
        $this->sendCommand("RCPT TO:<{$to}>", 250);
        $this->sendCommand("DATA", 354);

        $boundary = md5(uniqid(time()));
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n";
        $headers .= "To: <{$to}>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $headers .= "X-Mailer: Canal-Denuncias-EPCO/1.0\r\n";
        $headers .= "\r\n";

        // Versión texto plano
        $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], "\n", $htmlBody));
        $textBody = html_entity_decode($textBody, ENT_QUOTES, 'UTF-8');

        $message = $headers;
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($textBody)) . "\r\n";
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $message .= "--{$boundary}--\r\n";
        $message .= ".";

        $this->sendCommand($message, 250);
    }

    private function quit(): void {
        try {
            $this->sendCommand("QUIT", 221);
        } catch (Exception $e) {
            // Ignorar errores al cerrar
        }
        if ($this->socket) {
            fclose($this->socket);
        }
    }

    private function sendCommand(string $command, int $expectedCode): string {
        fwrite($this->socket, $command . "\r\n");
        return $this->readResponse($expectedCode);
    }

    private function readResponse(int $expectedCode): string {
        $response = '';
        $timeout = 10;
        $start = time();
        
        while (true) {
            if (time() - $start > $timeout) {
                throw new Exception("Timeout esperando respuesta SMTP");
            }
            
            $line = fgets($this->socket, 4096);
            if ($line === false) {
                throw new Exception("Conexión SMTP cerrada inesperadamente");
            }
            
            $response .= $line;
            
            // Respuesta completa si el 4to carácter es un espacio
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        
        $code = (int)substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new Exception("SMTP Error: esperaba $expectedCode, recibió $code - $response");
        }
        
        return $response;
    }
}

// =============================================
// PLANTILLAS DE CORREO
// =============================================

/**
 * Generar plantilla HTML base para correos
 */
function emailTemplate(string $title, string $content): string {
    return '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f0f4f8; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f0f4f8; padding: 30px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0a2540, #1e3a5f); padding: 30px; border-radius: 12px 12px 0 0; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 22px; font-weight: 700;">Canal de Denuncias</h1>
                            <p style="color: rgba(255,255,255,0.7); margin: 5px 0 0; font-size: 13px;">Empresa Portuaria Coquimbo · Ley Karin N° 21.643</p>
                        </td>
                    </tr>
                    <!-- Title -->
                    <tr>
                        <td style="background: #ffffff; padding: 25px 30px 10px; border-left: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;">
                            <h2 style="color: #0a2540; margin: 0; font-size: 18px; font-weight: 700;">' . htmlspecialchars($title) . '</h2>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="background: #ffffff; padding: 15px 30px 30px; border-left: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;">
                            ' . $content . '
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background: #0a2540; padding: 20px 30px; border-radius: 0 0 12px 12px; text-align: center;">
                            <p style="color: rgba(255,255,255,0.6); margin: 0; font-size: 12px;">
                                Este es un correo automático del Canal de Denuncias.<br>
                                En cumplimiento de la Ley N° 21.643 (Ley Karin) · Información confidencial.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
}

/**
 * Notificar a administradores sobre una nueva denuncia
 */
function notifyAdminsNewComplaint(string $complaintNumber, string $complaintType, bool $isAnonymous): void {
    global $pdo;

    // Obtener emails de admins e investigadores activos
    $stmt = $pdo->query("SELECT email, name FROM users WHERE role IN ('admin', 'investigador') AND is_active = 1");
    $admins = $stmt->fetchAll();

    // También enviar al email admin configurado
    $adminEmail = SMTP_ADMIN_EMAIL;

    $typeLabel = COMPLAINT_TYPES[$complaintType]['label'] ?? $complaintType;
    $fecha = date('d/m/Y H:i');

    $content = '
        <p style="color: #374151; font-size: 14px; line-height: 1.7;">
            Se ha recibido una <strong>nueva denuncia</strong> en el Canal de Denuncias que requiere su atención.
        </p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f8fafc; border-radius: 8px; padding: 20px; margin: 15px 0;">
            <tr>
                <td style="padding: 8px 15px; color: #6b7280; font-size: 13px; width: 40%;">N° de Denuncia:</td>
                <td style="padding: 8px 15px; color: #0a2540; font-weight: 700; font-size: 14px;">' . htmlspecialchars($complaintNumber) . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 15px; color: #6b7280; font-size: 13px;">Tipo:</td>
                <td style="padding: 8px 15px; color: #0a2540; font-weight: 600; font-size: 14px;">' . htmlspecialchars($typeLabel) . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 15px; color: #6b7280; font-size: 13px;">Modalidad:</td>
                <td style="padding: 8px 15px; color: #0a2540; font-size: 14px;">' . ($isAnonymous ? 'Anónima' : 'Identificada') . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 15px; color: #6b7280; font-size: 13px;">Fecha de registro:</td>
                <td style="padding: 8px 15px; color: #0a2540; font-size: 14px;">' . $fecha . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 15px; color: #6b7280; font-size: 13px;">Estado:</td>
                <td style="padding: 8px 15px; font-size: 14px;">
                    <span style="background: #dbeafe; color: #1e40af; padding: 3px 10px; border-radius: 4px; font-weight: 600; font-size: 12px;">Recibida</span>
                </td>
            </tr>
        </table>
        <p style="color: #374151; font-size: 14px; line-height: 1.7;">
            Ingrese al <strong>Dashboard</strong> para revisar los detalles y asignar la investigación correspondiente.
        </p>
        <div style="text-align: center; margin: 25px 0 10px;">
            <a href="' . (getenv('APP_URL') ?: 'http://localhost:8091') . '/acceso" style="display: inline-block; background: linear-gradient(135deg, #0a2540, #1e3a5f); color: #ffffff; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; font-size: 14px;">
                Ir al Dashboard
            </a>
        </div>';

    $subject = "Nueva Denuncia: $complaintNumber - $typeLabel";
    $html = emailTemplate('Nueva Denuncia Recibida', $content);

    // Enviar a cada admin/investigador
    foreach ($admins as $admin) {
        sendEmail($admin['email'], $subject, $html);
    }

    // Enviar al email admin adicional si está configurado
    if (!empty($adminEmail)) {
        $alreadySent = array_column($admins, 'email');
        if (!in_array($adminEmail, $alreadySent)) {
            sendEmail($adminEmail, $subject, $html);
        }
    }
}

/**
 * Enviar confirmación al denunciante (si proporcionó email)
 */
function notifyComplainant(string $email, string $complaintNumber, string $complaintType): void {
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $typeLabel = COMPLAINT_TYPES[$complaintType]['label'] ?? $complaintType;

    $content = '
        <p style="color: #374151; font-size: 14px; line-height: 1.7;">
            Su denuncia ha sido <strong>registrada exitosamente</strong> en el Canal de Denuncias de la Empresa Portuaria Coquimbo.
        </p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 20px; margin: 15px 0;">
            <tr>
                <td style="padding: 8px 15px; color: #6b7280; font-size: 13px; width: 45%;">Código de seguimiento:</td>
                <td style="padding: 8px 15px; color: #166534; font-weight: 700; font-size: 16px;">' . htmlspecialchars($complaintNumber) . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 15px; color: #6b7280; font-size: 13px;">Tipo de denuncia:</td>
                <td style="padding: 8px 15px; color: #0a2540; font-weight: 600; font-size: 14px;">' . htmlspecialchars($typeLabel) . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 15px; color: #6b7280; font-size: 13px;">Fecha:</td>
                <td style="padding: 8px 15px; color: #0a2540; font-size: 14px;">' . date('d/m/Y H:i') . '</td>
            </tr>
        </table>
        
        <div style="background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 15px; margin: 15px 0;">
            <p style="color: #92400e; font-size: 13px; margin: 0; font-weight: 600;">
                Importante: Guarde este código de seguimiento. Es la única forma de consultar el estado de su denuncia.
            </p>
        </div>

        <p style="color: #374151; font-size: 14px; line-height: 1.7;">
            Puede consultar el estado de su denuncia en cualquier momento usando su código de seguimiento:
        </p>
        <div style="text-align: center; margin: 25px 0 10px;">
            <a href="' . (getenv('APP_URL') ?: 'http://localhost:8091') . '/seguimiento?codigo=' . urlencode($complaintNumber) . '" style="display: inline-block; background: linear-gradient(135deg, #0a2540, #1e3a5f); color: #ffffff; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; font-size: 14px;">
                Consultar Estado
            </a>
        </div>
        
        <div style="border-top: 1px solid #e5e7eb; padding-top: 15px; margin-top: 20px;">
            <p style="color: #6b7280; font-size: 12px; line-height: 1.6;">
                <strong>Sus derechos según la Ley Karin (N° 21.643):</strong><br>
                • Protección contra represalias por presentar esta denuncia<br>
                • Su caso será investigado de forma confidencial e imparcial<br>
                • La investigación tiene un plazo máximo de 30 días<br>
                • Tiene derecho a ser informado/a del resultado
            </p>
        </div>';

    $subject = "Denuncia registrada: $complaintNumber";
    $html = emailTemplate('Confirmación de Denuncia', $content);

    sendEmail($email, $subject, $html);
}

/**
 * Notificar al denunciante sobre cambio de estado
 */
function notifyStatusChange(int $complaintId, string $newStatus): void {
    global $pdo;

    // Obtener datos de la denuncia
    $stmt = $pdo->prepare("SELECT complaint_number, complaint_type, is_anonymous, reporter_email_encrypted, reporter_email_nonce FROM complaints WHERE id = ?");
    $stmt->execute([$complaintId]);
    $complaint = $stmt->fetch();

    if (!$complaint || $complaint['is_anonymous']) {
        return;
    }

    // Desencriptar email del denunciante
    $enc = getEncryptionService();
    $email = $enc->decrypt($complaint['reporter_email_encrypted'], $complaint['reporter_email_nonce']);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $statusConfig = COMPLAINT_STATUSES[$newStatus] ?? ['label' => $newStatus];
    $typeLabel = COMPLAINT_TYPES[$complaint['complaint_type']]['label'] ?? $complaint['complaint_type'];

    $statusColors = [
        'recibida' => '#dbeafe',
        'en_investigacion' => '#fef3c7',
        'resuelta' => '#dcfce7',
        'desestimada' => '#f3f4f6',
        'archivada' => '#f3f4f6'
    ];

    $statusTextColors = [
        'recibida' => '#1e40af',
        'en_investigacion' => '#92400e',
        'resuelta' => '#166534',
        'desestimada' => '#374151',
        'archivada' => '#374151'
    ];

    $bgColor = $statusColors[$newStatus] ?? '#f3f4f6';
    $textColor = $statusTextColors[$newStatus] ?? '#374151';

    $content = '
        <p style="color: #374151; font-size: 14px; line-height: 1.7;">
            Le informamos que el estado de su denuncia ha sido actualizado.
        </p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f8fafc; border-radius: 8px; padding: 20px; margin: 15px 0;">
            <tr>
                <td style="padding: 8px 15px; color: #6b7280; font-size: 13px; width: 45%;">N° de Denuncia:</td>
                <td style="padding: 8px 15px; color: #0a2540; font-weight: 700; font-size: 14px;">' . htmlspecialchars($complaint['complaint_number']) . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 15px; color: #6b7280; font-size: 13px;">Tipo:</td>
                <td style="padding: 8px 15px; color: #0a2540; font-size: 14px;">' . htmlspecialchars($typeLabel) . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 15px; color: #6b7280; font-size: 13px;">Nuevo estado:</td>
                <td style="padding: 8px 15px; font-size: 14px;">
                    <span style="background: ' . $bgColor . '; color: ' . $textColor . '; padding: 4px 12px; border-radius: 4px; font-weight: 700; font-size: 13px;">' . htmlspecialchars($statusConfig['label']) . '</span>
                </td>
            </tr>
        </table>
        <p style="color: #374151; font-size: 14px; line-height: 1.7;">
            Puede consultar más detalles de su denuncia utilizando su código de seguimiento.
        </p>
        <div style="text-align: center; margin: 25px 0 10px;">
            <a href="' . (getenv('APP_URL') ?: 'http://localhost:8091') . '/seguimiento?codigo=' . urlencode($complaint['complaint_number']) . '" style="display: inline-block; background: linear-gradient(135deg, #0a2540, #1e3a5f); color: #ffffff; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; font-size: 14px;">
                Ver Estado de Denuncia
            </a>
        </div>';

    $subject = "Actualización denuncia {$complaint['complaint_number']}: {$statusConfig['label']}";
    $html = emailTemplate('Actualización de Estado', $content);

    sendEmail($email, $subject, $html);
}
