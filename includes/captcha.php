<?php
/**
 * Portal de Denuncias - CAPTCHA Matemático Reforzado
 *
 * Mejoras de seguridad vs versión anterior:
 *  - Operaciones compuestas con rangos ampliados (más difícil de resolver por regex/bot)
 *  - Honeypot field oculto (los bots lo rellenan, los humanos no)
 *  - Binding del token a la IP del cliente (previene reutilización cross-IP)
 *  - Tiempo mínimo de 2 segundos antes de poder enviar (anti-bot automático)
 *  - Token consumido siempre en validate() (previene replay attacks)
 *  - Expiración estricta de 5 minutos
 */
class SimpleCaptcha {

    public static function generate(): array {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $type = rand(0, 3);

        switch ($type) {
            case 0: // Suma con rangos más amplios
                $a = rand(8, 30); $b = rand(8, 30);
                $question = "$a + $b";
                $answer   = $a + $b;
                break;
            case 1: // Resta siempre positiva
                $a = rand(20, 55); $b = rand(5, $a - 1);
                $question = "$a − $b";
                $answer   = $a - $b;
                break;
            case 2: // Multiplicación (rangos más exigentes)
                $a = rand(4, 9); $b = rand(4, 9);
                $question = "$a × $b";
                $answer   = $a * $b;
                break;
            case 3: // Operación compuesta: (a + b) × c
                $a = rand(2, 8); $b = rand(2, 8); $c = rand(2, 5);
                $question = "($a + $b) × $c";
                $answer   = ($a + $b) * $c;
                break;
        }

        $token = bin2hex(random_bytes(16));
        $ip    = $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $_SESSION['captcha'] = [
            'answer'  => $answer,
            'token'   => $token,
            'created' => time(),
            'ip_hash' => hash('sha256', $ip . $token), // Binding a IP
        ];

        return ['question' => "¿Cuánto es {$question}?", 'token' => $token];
    }

    public static function validate(string $userAnswer, string $token): bool {
        if (session_status() === PHP_SESSION_NONE) session_start();

        if (!isset($_SESSION['captcha'])) return false;
        $captcha = $_SESSION['captcha'];
        unset($_SESSION['captcha']); // Consumir siempre (prevenir replay)

        // Verificar token con comparación segura
        if (empty($captcha['token']) || !hash_equals($captcha['token'], $token)) return false;

        // Verificar expiración (5 min)
        if ((time() - ($captcha['created'] ?? 0)) > 300) return false;

        // Anti-bot: el formulario debe haberse rellenado en al menos 2 segundos
        if ((time() - ($captcha['created'] ?? 0)) < 2) return false;

        // Verificar binding de IP
        $ip      = $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $expected = hash('sha256', $ip . $token);
        if (!hash_equals($captcha['ip_hash'] ?? '', $expected)) return false;

        return (int)$userAnswer === (int)($captcha['answer'] ?? PHP_INT_MAX);
    }

    public static function render(): string {
        $captcha = self::generate();
        $q = htmlspecialchars($captcha['question'], ENT_QUOTES, 'UTF-8');
        $t = htmlspecialchars($captcha['token'],    ENT_QUOTES, 'UTF-8');

        return '
        <div class="captcha-container mb-3">
            <!-- Honeypot: campo invisible. Los bots lo rellenan; los humanos no lo ven. -->
            <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;opacity:0;pointer-events:none;">
                <label for="_url_check">URL</label>
                <input type="text" id="_url_check" name="website" tabindex="-1" autocomplete="off" value="">
            </div>
            <label class="form-label fw-semibold">Verificación de seguridad *</label>
            <div class="input-group">
                <span class="input-group-text bg-light fw-bold" style="min-width:225px;">
                    <i class="bi bi-calculator me-2"></i>' . $q . '
                </span>
                <input type="number" name="captcha_answer" class="form-control"
                    placeholder="Tu respuesta" required autocomplete="off"
                    inputmode="numeric">
                <input type="hidden" name="captcha_token" value="' . $t . '">
            </div>
            <div class="form-text">Resuelve la operación matemática para continuar.</div>
        </div>';
    }
}

// API endpoint para regenerar captcha vía AJAX
if (basename($_SERVER['PHP_SELF']) === 'captcha.php' && isset($_GET['action'])) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    header('Content-Type: application/json; charset=utf-8');
    if ($_GET['action'] === 'generate') {
        echo json_encode(SimpleCaptcha::generate());
        exit;
    }
}
