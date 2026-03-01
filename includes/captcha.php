<?php
/**
 * Portal de Denuncias - CAPTCHA Matemático
 */

class SimpleCaptcha {

    public static function generate(): array {
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $operators = ['+', '-', '×'];
        $operator = $operators[array_rand($operators)];

        switch ($operator) {
            case '+': $answer = $num1 + $num2; break;
            case '-':
                if ($num1 < $num2) { $temp = $num1; $num1 = $num2; $num2 = $temp; }
                $answer = $num1 - $num2; break;
            case '×':
                $num1 = rand(1, 5); $num2 = rand(1, 5);
                $answer = $num1 * $num2; break;
        }

        $question = "$num1 $operator $num2 = ?";
        $token = bin2hex(random_bytes(16));

        $_SESSION['captcha'] = ['answer' => $answer, 'token' => $token, 'created' => time()];

        return ['question' => $question, 'token' => $token];
    }

    public static function validate(string $userAnswer, string $token): bool {
        if (!isset($_SESSION['captcha'])) return false;
        $captcha = $_SESSION['captcha'];
        if ($captcha['token'] !== $token) return false;
        if (time() - $captcha['created'] > 300) { unset($_SESSION['captcha']); return false; }
        $isValid = (int)$userAnswer === $captcha['answer'];
        unset($_SESSION['captcha']);
        return $isValid;
    }

    public static function render(): string {
        $captcha = self::generate();
        return '
        <div class="captcha-container mb-3">
            <label class="form-label fw-semibold">Verificación de seguridad *</label>
            <div class="input-group">
                <span class="input-group-text bg-light fw-bold" style="min-width: 150px;">
                    <i class="bi bi-calculator me-2"></i>' . $captcha['question'] . '
                </span>
                <input type="number" name="captcha_answer" class="form-control" placeholder="Respuesta" required>
                <input type="hidden" name="captcha_token" value="' . $captcha['token'] . '">
            </div>
            <div class="form-text">Resuelve la operación matemática para continuar</div>
        </div>';
    }
}

// API endpoint
if (basename($_SERVER['PHP_SELF']) === 'captcha.php' && isset($_GET['action'])) {
    session_start();
    header('Content-Type: application/json');
    if ($_GET['action'] === 'generate') {
        echo json_encode(SimpleCaptcha::generate());
        exit;
    }
}
