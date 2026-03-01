<?php
/**
 * Portal de Denuncias EPCO - Acceso al Dashboard
 * Página de entrada al panel de administración / investigación
 */
$pageTitle = 'Acceso al Dashboard';
require_once __DIR__ . '/../includes/bootstrap.php';

// Si ya está logueado, redirigir al panel
if (isLoggedIn()) {
    header('Location: /panel');
    exit;
}

$error = '';
$success = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Token de seguridad inválido. Recarga la página.';
    } else {
        $identifier = sanitize($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($identifier) || empty($password)) {
            $error = 'Completa todos los campos.';
        } elseif (login($identifier, $password)) {
            logActivity($_SESSION['user_id'], 'login', 'user', $_SESSION['user_id'], 'Inicio de sesión desde página de acceso');
            header('Location: /panel');
            exit;
        } else {
            $error = 'Credenciales incorrectas o cuenta bloqueada.';
            logActivity(null, 'login_fallido', null, null, "Intento con: $identifier");
        }
    }
}

require_once __DIR__ . '/../includes/encabezado.php';
?>

<style>
    /* =============================================
     * ACCESO AL DASHBOARD - ESTILOS
     * ============================================= */
    .access-page {
        min-height: 100vh;
        position: relative;
        overflow: hidden;
    }

    /* Navbar */
    .access-navbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1000;
        background: rgba(10, 37, 64, 0.9);
        backdrop-filter: blur(12px);
        height: 60px;
        display: flex;
        align-items: center;
        padding: 0 30px;
        justify-content: space-between;
    }

    .access-navbar-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
        text-decoration: none;
        font-weight: 700;
        font-size: 1.1rem;
    }

    .access-navbar-brand i {
        font-size: 1.3rem;
        opacity: 0.9;
    }

    .access-navbar-links {
        display: flex;
        gap: 20px;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .access-navbar-links a {
        color: rgba(255,255,255,0.7);
        text-decoration: none;
        font-size: 0.9rem;
        transition: color 0.3s;
    }

    .access-navbar-links a:hover {
        color: white;
    }

    /* Hero split layout */
    .access-hero {
        display: flex;
        min-height: 100vh;
        padding-top: 60px;
    }

    /* Panel izquierdo - Info */
    .access-info {
        flex: 1;
        background: linear-gradient(160deg, #0a2540 0%, #0f3d66 40%, #1a5276 100%);
        padding: 60px 50px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }

    .access-info::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(14, 165, 233, 0.08) 0%, transparent 70%);
        border-radius: 50%;
    }

    .access-info::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(5, 150, 105, 0.06) 0%, transparent 70%);
        border-radius: 50%;
    }

    .access-info-content {
        position: relative;
        z-index: 1;
        max-width: 550px;
    }

    .access-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(5, 150, 105, 0.15);
        border: 1px solid rgba(5, 150, 105, 0.3);
        color: #34d399;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-bottom: 25px;
    }

    .access-title {
        font-size: 2.2rem;
        font-weight: 800;
        color: white;
        line-height: 1.2;
        margin-bottom: 15px;
    }

    .access-subtitle {
        font-size: 1.05rem;
        color: rgba(255,255,255,0.6);
        line-height: 1.7;
        margin-bottom: 40px;
    }

    /* Feature cards */
    .feature-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 35px;
    }

    .feature-item {
        background: rgba(255,255,255,0.04);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 14px;
        padding: 20px;
        transition: all 0.3s ease;
    }

    .feature-item:hover {
        background: rgba(255,255,255,0.08);
        border-color: rgba(255,255,255,0.15);
        transform: translateY(-3px);
    }

    .feature-item-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        color: white;
        margin-bottom: 12px;
    }

    .feature-item h6 {
        color: white;
        font-weight: 700;
        font-size: 0.9rem;
        margin-bottom: 4px;
    }

    .feature-item p {
        color: rgba(255,255,255,0.5);
        font-size: 0.78rem;
        margin: 0;
        line-height: 1.5;
    }

    /* Roles info */
    .roles-info {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .role-tag {
        display: flex;
        align-items: center;
        gap: 6px;
        background: rgba(255,255,255,0.06);
        padding: 8px 14px;
        border-radius: 8px;
        color: rgba(255,255,255,0.7);
        font-size: 0.82rem;
    }

    .role-tag i {
        font-size: 0.9rem;
    }

    .role-tag.admin { color: #f87171; }
    .role-tag.investigador { color: #fbbf24; }
    .role-tag.viewer { color: #60a5fa; }

    /* Panel derecho - Login */
    .access-login {
        width: 480px;
        min-width: 480px;
        background: #f8fafc;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 60px 40px;
        position: relative;
    }

    .login-container {
        width: 100%;
        max-width: 380px;
    }

    .login-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .login-icon {
        width: 70px;
        height: 70px;
        background: linear-gradient(135deg, #0a2540, #1e3a5f);
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 18px;
        box-shadow: 0 10px 30px rgba(10, 37, 64, 0.3);
    }

    .login-icon i {
        font-size: 1.8rem;
        color: white;
    }

    .login-header h4 {
        font-weight: 700;
        color: #0a2540;
        margin-bottom: 5px;
    }

    .login-header p {
        color: #94a3b8;
        font-size: 0.9rem;
    }

    /* Form */
    .login-form .form-label {
        font-weight: 600;
        color: #334155;
        font-size: 0.85rem;
    }

    .login-form .input-group {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }

    .login-form .input-group-text {
        background: #f1f5f9;
        border: 2px solid #e2e8f0;
        border-right: none;
        color: #64748b;
    }

    .login-form .form-control {
        border: 2px solid #e2e8f0;
        border-left: none;
        padding: 12px 15px;
        font-size: 0.95rem;
    }

    .login-form .form-control:focus {
        border-color: #0a2540;
        box-shadow: none;
    }

    .login-form .form-control:focus + .input-group-text,
    .login-form .input-group:focus-within .input-group-text {
        border-color: #0a2540;
    }

    .btn-login {
        background: linear-gradient(135deg, #0a2540, #1e3a5f);
        color: white;
        border: none;
        padding: 14px 30px;
        font-weight: 700;
        font-size: 1rem;
        border-radius: 12px;
        transition: all 0.3s ease;
        width: 100%;
    }

    .btn-login:hover {
        background: linear-gradient(135deg, #0d3157, #254b73);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(10, 37, 64, 0.35);
    }

    .btn-login:active {
        transform: translateY(0);
    }

    .login-divider {
        display: flex;
        align-items: center;
        gap: 15px;
        margin: 25px 0;
        color: #94a3b8;
        font-size: 0.82rem;
    }

    .login-divider::before,
    .login-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #e2e8f0;
    }

    /* Security badges */
    .security-features {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 25px;
    }

    .security-item {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #64748b;
        font-size: 0.82rem;
    }

    .security-item i {
        width: 28px;
        height: 28px;
        background: #ecfdf5;
        color: #059669;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        flex-shrink: 0;
    }

    /* Alert custom */
    .alert-access {
        border-radius: 12px;
        border: none;
        padding: 12px 16px;
        font-size: 0.88rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Bottom link */
    .back-link {
        position: absolute;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%);
        color: #94a3b8;
        text-decoration: none;
        font-size: 0.85rem;
        transition: color 0.3s;
    }

    .back-link:hover {
        color: #0a2540;
    }

    /* Stats ribbon */
    .stats-ribbon {
        display: flex;
        gap: 30px;
        padding: 20px 0;
        border-top: 1px solid rgba(255,255,255,0.1);
        margin-top: 35px;
    }

    .stats-ribbon-item {
        text-align: center;
    }

    .stats-ribbon-item .number {
        font-size: 1.4rem;
        font-weight: 800;
        color: white;
    }

    .stats-ribbon-item .label {
        font-size: 0.72rem;
        color: rgba(255,255,255,0.5);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Responsive */
    @media (max-width: 992px) {
        .access-hero {
            flex-direction: column;
        }

        .access-info {
            padding: 40px 25px;
        }

        .access-login {
            width: 100%;
            min-width: 100%;
            padding: 40px 25px;
        }

        .access-title {
            font-size: 1.6rem;
        }

        .feature-grid {
            grid-template-columns: 1fr;
        }

        .access-navbar-links {
            display: none;
        }

        .back-link {
            position: static;
            transform: none;
            display: block;
            text-align: center;
            margin-top: 20px;
        }
    }

    /* Password visibility toggle */
    .btn-eye {
        background: #f1f5f9;
        border: 2px solid #e2e8f0;
        border-left: none;
        color: #64748b;
        cursor: pointer;
    }

    .btn-eye:hover {
        background: #e2e8f0;
        color: #334155;
    }
</style>

<div class="access-page">

    <!-- Navbar -->
    <nav class="access-navbar">
        <a href="/" class="access-navbar-brand">
            <i class="bi bi-shield-lock-fill"></i>
            <span>Canal de Denuncias <span style="font-weight: 300;">EPCO</span></span>
        </a>
        <ul class="access-navbar-links">
            <li><a href="/"><i class="bi bi-house me-1"></i>Inicio</a></li>
            <li><a href="/nueva_denuncia"><i class="bi bi-pencil-square me-1"></i>Realizar Denuncia</a></li>
            <li><a href="/seguimiento"><i class="bi bi-search me-1"></i>Seguimiento</a></li>
        </ul>
    </nav>

    <div class="access-hero">

        <!-- Panel Izquierdo: Información del Dashboard -->
        <div class="access-info">
            <div class="access-info-content">
                <div class="access-badge fade-in">
                    <i class="bi bi-lock-fill"></i>
                    Acceso Restringido · Personal Autorizado
                </div>

                <h1 class="access-title fade-in">
                    Dashboard de<br>Gestión de Denuncias
                </h1>

                <p class="access-subtitle fade-in">
                    Panel centralizado para la administración, investigación y seguimiento de 
                    todas las denuncias recibidas a través del Canal de Integridad, conforme 
                    a la Ley N° 21.643 (Ley Karin).
                </p>

                <!-- Funcionalidades -->
                <div class="feature-grid fade-in">
                    <div class="feature-item">
                        <div class="feature-item-icon" style="background: linear-gradient(135deg, #0369a1, #0ea5e9);">
                            <i class="bi bi-speedometer2"></i>
                        </div>
                        <h6>Panel de Control</h6>
                        <p>KPIs en tiempo real, gráficos de tendencia y estadísticas por tipo de denuncia.</p>
                    </div>
                    <div class="feature-item">
                        <div class="feature-item-icon" style="background: linear-gradient(135deg, #059669, #34d399);">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <h6>Datos Encriptados</h6>
                        <p>AES-256 en toda la información sensible. Solo personal autorizado puede desencriptar.</p>
                    </div>
                    <div class="feature-item">
                        <div class="feature-item-icon" style="background: linear-gradient(135deg, #7c3aed, #a78bfa);">
                            <i class="bi bi-search"></i>
                        </div>
                        <h6>Investigación</h6>
                        <p>Gestión de casos, notas confidenciales, asignación de investigadores y resoluciones.</p>
                    </div>
                    <div class="feature-item">
                        <div class="feature-item-icon" style="background: linear-gradient(135deg, #dc2626, #f87171);">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <h6>Reportes y Auditoría</h6>
                        <p>Registro completo de actividad, reportes exportables y trazabilidad total.</p>
                    </div>
                </div>

                <!-- Roles -->
                <div class="fade-in">
                    <p class="text-white-50 small mb-2 fw-semibold" style="letter-spacing: 1px; text-transform: uppercase; font-size: 0.7rem;">Roles con acceso</p>
                    <div class="roles-info">
                        <div class="role-tag admin">
                            <i class="bi bi-shield-fill-check"></i>
                            Administrador
                        </div>
                        <div class="role-tag investigador">
                            <i class="bi bi-person-badge"></i>
                            Investigador
                        </div>
                        <div class="role-tag viewer">
                            <i class="bi bi-eye"></i>
                            Visor
                        </div>
                    </div>
                </div>

                <!-- Stats -->
                <?php
                try {
                    $dashStats = $pdo->query("
                        SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'recibida' THEN 1 ELSE 0 END) as recibidas,
                            SUM(CASE WHEN status = 'resuelta' THEN 1 ELSE 0 END) as resueltas
                        FROM complaints
                    ")->fetch();
                } catch (Exception $e) {
                    $dashStats = ['total' => 0, 'recibidas' => 0, 'resueltas' => 0];
                }
                ?>
                <div class="stats-ribbon fade-in">
                    <div class="stats-ribbon-item">
                        <div class="number"><?= $dashStats['total'] ?? 0 ?></div>
                        <div class="label">Denuncias</div>
                    </div>
                    <div class="stats-ribbon-item">
                        <div class="number"><?= $dashStats['recibidas'] ?? 0 ?></div>
                        <div class="label">Pendientes</div>
                    </div>
                    <div class="stats-ribbon-item">
                        <div class="number"><?= $dashStats['resueltas'] ?? 0 ?></div>
                        <div class="label">Resueltas</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel Derecho: Login -->
        <div class="access-login">
            <div class="login-container">
                <div class="login-header">
                    <div class="login-icon">
                        <i class="bi bi-box-arrow-in-right"></i>
                    </div>
                    <h4>Iniciar Sesión</h4>
                    <p>Ingresa tus credenciales para acceder al dashboard</p>
                </div>

                <?php $flash = getFlashMessage(); if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?> alert-access mb-3">
                    <i class="bi bi-info-circle"></i>
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-danger alert-access mb-3">
                    <i class="bi bi-exclamation-triangle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="/acceso" class="login-form">
                    <?= csrfInput() ?>

                    <div class="mb-3">
                        <label for="identifier" class="form-label">Usuario o Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="identifier" id="identifier" class="form-control" 
                                   placeholder="Tu usuario o email" required autofocus
                                   value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" id="password" class="form-control" 
                                   placeholder="Tu contraseña" required>
                            <button type="button" class="btn btn-eye" onclick="togglePassword()">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-login">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Acceder al Dashboard
                    </button>
                </form>

                <div class="login-divider">Seguridad del portal</div>

                <div class="security-features">
                    <div class="security-item">
                        <i class="bi bi-shield-check"></i>
                        <span>Conexión segura con CSRF Protection</span>
                    </div>
                    <div class="security-item">
                        <i class="bi bi-lock-fill"></i>
                        <span>Bloqueo automático tras 5 intentos fallidos</span>
                    </div>
                    <div class="security-item">
                        <i class="bi bi-clock-history"></i>
                        <span>Registro de toda la actividad del sistema</span>
                    </div>
                    <div class="security-item">
                        <i class="bi bi-key-fill"></i>
                        <span>Encriptación AES-256 de datos sensibles</span>
                    </div>
                </div>
            </div>

            <a href="/" class="back-link">
                <i class="bi bi-arrow-left me-1"></i>Volver al portal público
            </a>
        </div>

    </div>
</div>

<script>
function togglePassword() {
    const p = document.getElementById('password');
    const i = document.getElementById('eyeIcon');
    if (p.type === 'password') {
        p.type = 'text';
        i.className = 'bi bi-eye-slash';
    } else {
        p.type = 'password';
        i.className = 'bi bi-eye';
    }
}

// Focus animation
document.querySelectorAll('.login-form .form-control').forEach(input => {
    input.addEventListener('focus', function() {
        this.closest('.input-group').style.boxShadow = '0 4px 15px rgba(10, 37, 64, 0.15)';
    });
    input.addEventListener('blur', function() {
        this.closest('.input-group').style.boxShadow = '0 2px 8px rgba(0,0,0,0.04)';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
