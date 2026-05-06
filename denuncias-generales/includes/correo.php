<?php
/**
 * Portal Denuncias Ciudadanas Empresa Portuaria Coquimbo - Servicio de Correo
 *
 * Compatible con Gmail, Microsoft 365 / Outlook / Hotmail y cualquier SMTP.
 * Usa PHPMailer cuando está disponible (Composer); fallback SMTP nativo.
 *
 * Proveedores comunes (configurar en variables de entorno o .env):
 *
 * Gmail:
 *   SMTP_HOST=smtp.gmail.com  SMTP_PORT=587  SMTP_ENCRYPTION=tls
 *   SMTP_PASS = Contraseña de aplicación (https://myaccount.google.com/apppasswords)
 *
 * Microsoft 365 / Outlook empresarial:
 *   SMTP_HOST=smtp.office365.com  SMTP_PORT=587  SMTP_ENCRYPTION=tls
 *
 * Outlook.com / Hotmail personal:
 *   SMTP_HOST=smtp-mail.outlook.com  SMTP_PORT=587  SMTP_ENCRYPTION=tls
 *
 * Yahoo Mail:
 *   SMTP_HOST=smtp.mail.yahoo.com  SMTP_PORT=587  SMTP_ENCRYPTION=tls
 *
 * Servidor propio / corporativo:
 *   SMTP_HOST=mail.midominio.cl  SMTP_PORT=587  SMTP_ENCRYPTION=tls
 */

if (!defined('GENERALES_APP')) {
    die('Acceso directo no permitido');
}

if (!function_exists('_smtpPresetFromUser')) {
    function _smtpPresetFromUser(string $user): array {
        $domain  = strtolower(substr($user, strrpos($user, '@') + 1));
        $presets = [
            'gmail.com'    => ['smtp.gmail.com',           587, 'tls'],
            'outlook.com'  => ['smtp-mail.outlook.com',    587, 'tls'],
            'hotmail.com'  => ['smtp-mail.outlook.com',    587, 'tls'],
            'live.com'     => ['smtp-mail.outlook.com',    587, 'tls'],
            'live.cl'      => ['smtp-mail.outlook.com',    587, 'tls'],
            'msn.com'      => ['smtp-mail.outlook.com',    587, 'tls'],
            'yahoo.com'    => ['smtp.mail.yahoo.com',      587, 'tls'],
            'yahoo.es'     => ['smtp.mail.yahoo.com',      587, 'tls'],
            'zoho.com'     => ['smtp.zoho.com',            587, 'tls'],
            'zohomail.com' => ['smtp.zoho.com',            587, 'tls'],
            'icloud.com'   => ['smtp.mail.me.com',         587, 'tls'],
            'me.com'       => ['smtp.mail.me.com',         587, 'tls'],
        ];
        return $presets[$domain] ?? ['smtp.gmail.com', 587, 'tls'];
    }
}

$_smtpUser   = getenv('SMTP_USER') ?: '';
$_smtpPreset = !empty($_smtpUser) ? _smtpPresetFromUser($_smtpUser) : ['smtp.gmail.com', 587, 'tls'];

define('SMTP_ENABLED',    filter_var(getenv('SMTP_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN));
define('SMTP_HOST',       getenv('SMTP_HOST') ?: $_smtpPreset[0]);
define('SMTP_PORT',       (int)(getenv('SMTP_PORT') ?: $_smtpPreset[1]));
define('SMTP_USER',       $_smtpUser);
define('SMTP_PASS',       getenv('SMTP_PASS') ?: '');
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: $_smtpPreset[2]);
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'denuncias@epco.cl');
define('SMTP_FROM_NAME',  getenv('SMTP_FROM_NAME')  ?: 'Portal Ciudadano - Empresa Portuaria Coquimbo');
define('SMTP_ADMIN_EMAIL',getenv('SMTP_ADMIN_EMAIL') ?: '');

function sendEmail(string $to, string $subject, string $htmlBody): bool {
    if (!SMTP_ENABLED || empty(SMTP_USER) || empty(SMTP_PASS)) {
        log_email($to, $subject, false, 'SMTP disabled or not configured');
        return false;
    }

    // Usar PHPMailer si está instalado via Composer
    $autoloader = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoloader)) {
        require_once $autoloader;
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $verifyPeer = filter_var(getenv('SMTP_VERIFY_SSL') ?: 'true', FILTER_VALIDATE_BOOLEAN);

            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->Port       = SMTP_PORT;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = SMTP_ENCRYPTION === 'ssl'
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPOptions = ['ssl' => [
                'verify_peer'       => $verifyPeer,
                'verify_peer_name'  => $verifyPeer,
                'allow_self_signed' => !$verifyPeer,
            ]];
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = 15;

            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags(str_replace(
                ['<br>', '<br/>', '<br />', '</p>', '</li>'], "\n", $htmlBody
            ));

            return $mail->send();
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            log_email($to, $subject, false, 'PHPMailer error: ' . $e->getMessage());
            return false;
        }
    }

    // Fallback: implementación SMTP nativa
    try {
        $smtp = new SmtpMailer(SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_ENCRYPTION);
        $sent = $smtp->send(SMTP_FROM_EMAIL, SMTP_FROM_NAME, $to, $subject, $htmlBody);
        if ($sent) {
            log_email($to, $subject, true);
        } else {
            log_email($to, $subject, false, 'SmtpMailer send returned false');
        }
        return $sent;
    } catch (Exception $e) {
        log_email($to, $subject, false, 'SmtpMailer error: ' . $e->getMessage());
        return false;
    }
}

class SmtpMailer {
    private $socket;
    private string $host;
    private int $port;
    private string $user;
    private string $pass;
    private string $encryption;

    public function __construct(string $host, int $port, string $user, string $pass, string $encryption = 'tls') {
        $this->host = $host; $this->port = $port; $this->user = $user;
        $this->pass = $pass; $this->encryption = $encryption;
    }

    public function send(string $fromEmail, string $fromName, string $to, string $subject, string $htmlBody): bool {
        $this->connect(); $this->authenticate();
        $this->sendMessage($fromEmail, $fromName, $to, $subject, $htmlBody);
        $this->quit();
        return true;
    }

    private function connect(): void {
        $verifyPeer = filter_var(getenv('SMTP_VERIFY_SSL') ?: 'true', FILTER_VALIDATE_BOOLEAN);
        $context = stream_context_create(['ssl' => [
            'verify_peer' => $verifyPeer, 'verify_peer_name' => $verifyPeer,
            'allow_self_signed' => !$verifyPeer
        ]]);
        $proto = $this->encryption === 'ssl' ? "ssl://" : "tcp://";
        $this->socket = stream_socket_client("{$proto}{$this->host}:{$this->port}", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
        if (!$this->socket) throw new Exception("Conexión fallida: $errstr ($errno)");
        $this->readResponse(220);
        $this->sendCommand("EHLO " . gethostname(), 250);
        if ($this->encryption === 'tls') {
            $this->sendCommand("STARTTLS", 220);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
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
        $this->sendCommand("MAIL FROM:<$fromEmail>", 250);
        $this->sendCommand("RCPT TO:<$to>", 250);
        $this->sendCommand("DATA", 354);
        $boundary = md5(uniqid('', true));
        $message  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$fromEmail>\r\n";
        $message .= "To: $to\r\n";
        $message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n\r\n";
        $message .= "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $message .= "--$boundary--\r\n.\r\n";
        $this->sendCommand($message, 250);
    }

    private function quit(): void {
        try { $this->sendCommand("QUIT", 221); } catch (Exception $e) {}
        if ($this->socket) fclose($this->socket);
    }

    private function sendCommand(string $command, int $expectedCode): string {
        fwrite($this->socket, $command . "\r\n");
        return $this->readResponse($expectedCode);
    }

    private function readResponse(int $expectedCode): string {
        $response = ''; $start = time();
        while (true) {
            if (time() - $start > 10) throw new Exception("Timeout SMTP");
            $line = fgets($this->socket, 4096);
            if ($line === false) throw new Exception("Conexión SMTP cerrada");
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $code = (int)substr($response, 0, 3);
        if ($code !== $expectedCode) throw new Exception("SMTP Error: esperaba $expectedCode, recibió $code - $response");
        return $response;
    }
}

// ============================================================
// PLANTILLAS DE CORREO (verde en lugar de azul)
// ============================================================

function emailTemplate(string $title, string $content): string {
    return '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background-color:#f0f4f8;font-family:\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0f4f8;padding:30px 0;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
<tr><td style="background:linear-gradient(135deg,#1a6591,#2380b0);padding:30px;border-radius:12px 12px 0 0;text-align:center;">
    <h1 style="color:#ffffff;margin:0;font-size:22px;font-weight:700;">Canal de Denuncias</h1>
    <p style="color:rgba(255,255,255,0.7);margin:5px 0 0;font-size:13px;">Empresa Portuaria Coquimbo · Legislación Chilena</p>
</td></tr>
<tr><td style="background:#ffffff;padding:25px 30px 10px;border-left:1px solid #e2e8f0;border-right:1px solid #e2e8f0;">
    <h2 style="color:#1a6591;margin:0;font-size:18px;font-weight:700;">' . htmlspecialchars($title) . '</h2>
</td></tr>
<tr><td style="background:#ffffff;padding:15px 30px 30px;border-left:1px solid #e2e8f0;border-right:1px solid #e2e8f0;">' . $content . '</td></tr>
<tr><td style="background:#1a6591;padding:20px 30px;border-radius:0 0 12px 12px;text-align:center;">
    <p style="color:rgba(255,255,255,0.6);margin:0;font-size:12px;">Este es un correo automático del Canal de Denuncias.<br>Empresa Portuaria Coquimbo · Información confidencial.</p>
</td></tr>
</table></td></tr></table>
</body></html>';
}

function notifyAdminsNewComplaint(int $complaintId, string $complaintNumber, string $complaintType, bool $isAnonymous): void {
    global $pdo;
    $stmt = $pdo->query("SELECT email, name, role, position, department FROM users WHERE role IN ('investigador','admin','superadmin') AND is_active = 1 AND email IS NOT NULL AND email <> ''");
    $admins = $stmt->fetchAll();
    $fecha     = date('d/m/Y H:i');
    $appUrl    = getenv('APP_URL') ?: 'http://localhost:8093';

    $content = '
        <p style="color:#374151;font-size:14px;line-height:1.7;">Se ha recibido una <strong>nueva denuncia en el Portal General</strong> y requiere revisión del equipo investigador.</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-radius:8px;padding:20px;margin:15px 0;">
            <tr><td style="padding:8px 15px;color:#6b7280;font-size:13px;width:40%;">N° de Denuncia:</td>
                <td style="padding:8px 15px;color:#1a6591;font-weight:700;font-size:14px;">' . htmlspecialchars($complaintNumber) . '</td></tr>
            <tr><td style="padding:8px 15px;color:#6b7280;font-size:13px;">Modalidad:</td>
                <td style="padding:8px 15px;color:#1a6591;font-size:14px;">' . ($isAnonymous ? 'Anónima' : 'Identificada') . '</td></tr>
            <tr><td style="padding:8px 15px;color:#6b7280;font-size:13px;">Fecha:</td>
                <td style="padding:8px 15px;color:#1a6591;font-size:14px;">' . $fecha . '</td></tr>
        </table>
        <p style="color:#64748b;font-size:13px;line-height:1.6;margin:0 0 12px;">Ingresa al panel para revisar antecedentes, asignar investigador y comenzar el proceso.</p>
        <div style="text-align:center;margin:25px 0 10px;">
            <a href="' . $appUrl . '/acceso" style="background:linear-gradient(135deg,#1a6591,#2380b0);color:#ffffff;text-decoration:none;padding:12px 30px;border-radius:8px;font-weight:600;font-size:14px;display:inline-block;">Ir al Dashboard</a>
        </div>';

    $subject = "Nueva Denuncia Portal General: $complaintNumber";
    $html    = emailTemplate('Nueva denuncia recibida - Portal General', $content);

    foreach ($admins as $admin) {
        if (isComplaintConflict($complaintId, $admin)) {
            continue;
        }
        sendEmail($admin['email'], $subject, $html);
    }
}

function notifyComplainant(string $email, string $complaintNumber, string $complaintType): void {
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) return;
    $appUrl    = getenv('APP_URL') ?: 'http://localhost:8093';

    $content = '
        <p style="color:#374151;font-size:14px;line-height:1.7;">Su denuncia ha sido <strong>registrada exitosamente</strong> en el Canal de Denuncias de la Empresa Portuaria Coquimbo.</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e8f0f6;border:1px solid #b3d4e8;border-radius:8px;padding:20px;margin:15px 0;">
            <tr><td style="padding:8px 15px;color:#6b7280;font-size:13px;width:45%;">Código de seguimiento:</td>
                <td style="padding:8px 15px;color:#1a6591;font-weight:700;font-size:16px;">' . htmlspecialchars($complaintNumber) . '</td></tr>
            <tr><td style="padding:8px 15px;color:#6b7280;font-size:13px;">Fecha:</td>
                <td style="padding:8px 15px;color:#1a6591;font-size:14px;">' . date('d/m/Y H:i') . '</td></tr>
        </table>
        <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:15px;margin:15px 0;">
            <p style="color:#92400e;font-size:13px;margin:0;font-weight:600;">Importante: Guarde este código. Es la única forma de consultar el estado de su denuncia.</p>
        </div>
        <div style="text-align:center;margin:25px 0 10px;">
            <a href="' . $appUrl . '/seguimiento?codigo=' . urlencode($complaintNumber) . '" style="background:linear-gradient(135deg,#1a6591,#2380b0);color:#ffffff;text-decoration:none;padding:12px 30px;border-radius:8px;font-weight:600;font-size:14px;display:inline-block;">Consultar Estado</a>
        </div>
        <div style="border-top:1px solid #e5e7eb;padding-top:15px;margin-top:20px;">
            <p style="color:#6b7280;font-size:12px;line-height:1.6;">
                <strong>Sus derechos como denunciante:</strong><br>
                • Protección frente a represalias por presentar esta denuncia<br>
                • Su caso será revisado de forma confidencial e imparcial<br>
                • Para denuncias de consumidor puede acudir también a SERNAC<br>
                • Tiene derecho a ser informado/a del resultado
            </p>
        </div>';

    sendEmail($email, "Denuncia registrada: $complaintNumber", emailTemplate('Confirmación de Denuncia', $content));
}

function notifyStatusChange(int $complaintId, string $newStatus): void {
    global $pdo;
    $stmt = $pdo->prepare("SELECT complaint_number, complaint_type, is_anonymous, reporter_email_encrypted, reporter_email_nonce FROM complaints WHERE id = ?");
    $stmt->execute([$complaintId]);
    $complaint = $stmt->fetch();
    if (!$complaint) return;

    // Si es anónima solo notificar si dejó email de contacto
    $enc   = getEncryptionService();
    $email = $enc->decrypt($complaint['reporter_email_encrypted'], $complaint['reporter_email_nonce']);
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) return;

    $statuses  = COMPLAINT_STATUSES;
    $statusCfg = $statuses[$newStatus] ?? ['label' => $newStatus, 'color' => 'secondary'];
    $appUrl    = getenv('APP_URL') ?: 'http://localhost:8093';
    $content = '
        <p style="color:#374151;font-size:14px;line-height:1.7;">El estado de su denuncia <strong>' . htmlspecialchars($complaint['complaint_number']) . '</strong> ha sido actualizado.</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-radius:8px;padding:20px;margin:15px 0;">
            <tr><td style="padding:8px 15px;color:#6b7280;font-size:13px;width:40%;">N° Denuncia:</td>
                <td style="padding:8px 15px;color:#1a6591;font-weight:700;font-size:14px;">' . htmlspecialchars($complaint['complaint_number']) . '</td></tr>
            <tr><td style="padding:8px 15px;color:#6b7280;font-size:13px;">Nuevo estado:</td>
                <td style="padding:8px 15px;font-size:14px;font-weight:700;color:#1a6591;">' . htmlspecialchars($statusCfg['label']) . '</td></tr>
        </table>
        <div style="text-align:center;margin:25px 0 10px;">
            <a href="' . $appUrl . '/seguimiento?codigo=' . urlencode($complaint['complaint_number']) . '" style="background:linear-gradient(135deg,#1a6591,#2380b0);color:#ffffff;text-decoration:none;padding:12px 30px;border-radius:8px;font-weight:600;font-size:14px;display:inline-block;">Ver Estado</a>
        </div>';

    sendEmail($email, "Actualización denuncia: " . $complaint['complaint_number'], emailTemplate('Estado Actualizado', $content));
}
