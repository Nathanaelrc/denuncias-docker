<?php
/**
 * Portal de Denuncias EPCO - Acceso al Dashboard
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if (isLoggedIn()) {
    header('Location: /panel');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Token de seguridad inválido.';
    } else {
        $identifier = sanitize($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($identifier) || empty($password)) {
            $error = 'Completa todos los campos.';
        } elseif (login($identifier, $password)) {
            logActivity($_SESSION['user_id'], 'login', 'user', $_SESSION['user_id'], 'Login desde acceso');
            header('Location: /panel');
            exit;
        } else {
            $error = 'Credenciales incorrectas o cuenta bloqueada.';
            logActivity(null, 'login_fallido', null, null, "Intento: " . $identifier);
        }
    }
}

// Stats seguras
$dashStats = ['total' => 0, 'recibidas' => 0, 'resueltas' => 0];
try {
    if (isset($pdo)) {
        $dashStats = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='recibida' THEN 1 ELSE 0 END) as recibidas, SUM(CASE WHEN status='resuelta' THEN 1 ELSE 0 END) as resueltas FROM complaints")->fetch();
    }
} catch (Exception $e) { /* tabla puede no existir */ }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Dashboard - Canal de Denuncias EPCO</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/img/Logo01.png">
    <link rel="shortcut icon" type="image/png" href="/img/Logo01.png">

    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        * { font-family: 'Barlow', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }

        body { background: #0a2540; min-height: 100vh; overflow-x: hidden; }

        /* ===== NAVBAR ===== */
        .access-nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            height: 60px;
            background: rgba(10, 37, 64, 0.95);
            backdrop-filter: blur(12px);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 25px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .access-nav-brand {
            display: flex; align-items: center; gap: 10px;
            color: white; text-decoration: none; font-weight: 700; font-size: 1rem;
        }
        .access-nav-brand img { height: 32px; width: auto; }
        .access-nav-links { display: flex; align-items: center; gap: 20px; list-style: none; margin: 0; padding: 0; }
        .access-nav-links a { color: rgba(255,255,255,0.65); text-decoration: none; font-size: 0.88rem; transition: color 0.3s; }
        .access-nav-links a:hover { color: white; }
        .btn-nav-login {
            background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.25);
            color: white; padding: 7px 18px; border-radius: 8px; font-weight: 600;
            font-size: 0.85rem; text-decoration: none; display: flex; align-items: center; gap: 6px;
            transition: all 0.3s;
        }
        .btn-nav-login:hover { background: rgba(255,255,255,0.2); color: white; }

        /* ===== LAYOUT SPLIT ===== */
        .access-split { display: flex; min-height: 100vh; padding-top: 60px; }

        /* LEFT PANEL */
        .access-left {
            flex: 1; padding: 50px 45px;
            background: linear-gradient(160deg, #0a2540, #0f3d66 50%, #1a5276);
            display: flex; flex-direction: column; justify-content: center;
            position: relative; overflow: hidden;
        }
        .access-left::before {
            content: ''; position: absolute; top: -40%; right: -15%; width: 350px; height: 350px;
            background: radial-gradient(circle, rgba(14,165,233,0.07), transparent 70%); border-radius: 50%;
        }
        .access-left-inner { position: relative; z-index: 1; max-width: 520px; }

        .badge-restricted {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(5,150,105,0.15); border: 1px solid rgba(5,150,105,0.3);
            color: #34d399; padding: 5px 12px; border-radius: 20px;
            font-size: 0.78rem; font-weight: 600; margin-bottom: 22px;
        }
        .access-title { font-size: 2rem; font-weight: 800; color: white; line-height: 1.25; margin-bottom: 12px; }
        .access-desc { font-size: 0.98rem; color: rgba(255,255,255,0.55); line-height: 1.7; margin-bottom: 35px; }

        .feat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 30px; }
        .feat-card {
            background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.07);
            border-radius: 12px; padding: 18px; transition: all 0.3s;
        }
        .feat-card:hover { background: rgba(255,255,255,0.07); transform: translateY(-2px); }
        .feat-icon {
            width: 38px; height: 38px; border-radius: 9px; display: flex; align-items: center;
            justify-content: center; font-size: 1rem; color: white; margin-bottom: 10px;
        }
        .feat-card h6 { color: white; font-weight: 700; font-size: 0.85rem; margin-bottom: 3px; }
        .feat-card p { color: rgba(255,255,255,0.45); font-size: 0.75rem; margin: 0; line-height: 1.5; }

        .roles-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 25px; }
        .role-chip {
            display: flex; align-items: center; gap: 5px;
            background: rgba(255,255,255,0.06); padding: 6px 12px; border-radius: 7px;
            font-size: 0.8rem;
        }
        .role-chip.admin { color: #f87171; }
        .role-chip.inv { color: #fbbf24; }
        .role-chip.view { color: #60a5fa; }

        .stats-row {
            display: flex; gap: 28px; padding-top: 18px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .stat-box text-align: center; }
        .stat-num { font-size: 1.3rem; font-weight: 800; color: white; }
        .stat-lbl { font-size: 0.7rem; color: rgba(255,255,255,0.45); text-transform: uppercase; letter-spacing: 0.5px; }

        /* RIGHT PANEL */
        .access-right {
            width: 460px; min-width: 460px;
            background: #f8fafc;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 50px 35px; position: relative;
        }
        .login-box { width: 100%; max-width: 370px; }
        .login-head { text-align: center; margin-bottom: 28px; }
        .login-logo { margin-bottom: 15px; }
        .login-logo img { height: 55px; width: auto; }
        .login-head h4 { font-weight: 700; color: #0a2540; margin-bottom: 4px; font-size: 1.2rem; }
        .login-head p { color: #94a3b8; font-size: 0.88rem; }

        .form-label { font-weight: 600; color: #334155; font-size: 0.85rem; }
        .login-input-group {
            border-radius: 10px; overflow: hidden;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04); margin-bottom: 16px;
        }
        .login-input-group .input-group-text {
            background: #f1f5f9; border: 2px solid #e2e8f0; border-right: none; color: #64748b;
        }
        .login-input-group .form-control {
            border: 2px solid #e2e8f0; border-left: none; padding: 11px 14px; font-size: 0.93rem;
        }
        .login-input-group .form-control:focus { border-color: #0a2540; box-shadow: none; }
        .login-input-group:focus-within .input-group-text { border-color: #0a2540; }
        .btn-eye {
            background: #f1f5f9; border: 2px solid #e2e8f0; border-left: none;
            color: #64748b; cursor: pointer;
        }
        .btn-eye:hover { background: #e2e8f0; color: #334155; }

        .btn-access {
            width: 100%; background: linear-gradient(135deg, #0a2540, #1e3a5f);
            color: white; border: none; padding: 13px; font-weight: 700;
            font-size: 0.98rem; border-radius: 10px; transition: all 0.3s; cursor: pointer;
        }
        .btn-access:hover { background: linear-gradient(135deg, #0d3157, #254b73); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(10,37,64,0.3); }

        .divider-label {
            display: flex; align-items: center; gap: 12px; margin: 22px 0;
            color: #94a3b8; font-size: 0.8rem;
        }
        .divider-label::before, .divider-label::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }

        .sec-list { display: flex; flex-direction: column; gap: 8px; }
        .sec-item { display: flex; align-items: center; gap: 8px; color: #64748b; font-size: 0.8rem; }
        .sec-item i {
            width: 26px; height: 26px; background: #ecfdf5; color: #059669;
            border-radius: 7px; display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem; flex-shrink: 0;
        }

        .back-link {
            position: absolute; bottom: 25px; left: 50%; transform: translateX(-50%);
            color: #94a3b8; text-decoration: none; font-size: 0.83rem; transition: color 0.3s;
        }
        .back-link:hover { color: #0a2540; }

        .alert-box { border-radius: 10px; border: none; padding: 10px 14px; font-size: 0.87rem; display: flex; align-items: center; gap: 8px; }

        /* RESPONSIVE */
        @media (max-width: 992px) {
            .access-split { flex-direction: column; }
            .access-left { padding: 35px 20px; }
            .access-right { width: 100%; min-width: 100%; padding: 35px 20px; }
            .access-title { font-size: 1.5rem; }
            .feat-grid { grid-template-columns: 1fr; }
            .access-nav-links.desktop { display: none; }
            .back-link { position: static; transform: none; display: block; text-align: center; margin-top: 18px; }
        }
        @media (min-width: 993px) {
            .access-nav-links.mobile { display: none; }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="access-nav">
    <a href="/" class="access-nav-brand">
        <img src="/img/Logo01.png" alt="EPCO">
        <span>Canal de Denuncias</span>
    </a>
    <ul class="access-nav-links desktop">
        <li><a href="/"><i class="bi bi-house me-1"></i>Inicio</a></li>
        <li><a href="/nueva_denuncia"><i class="bi bi-pencil-square me-1"></i>Realizar Denuncia</a></li>
        <li><a href="/seguimiento"><i class="bi bi-search me-1"></i>Seguimiento</a></li>
    </ul>
</nav>

<div class="access-split">

    <!-- IZQUIERDA: Info -->
    <div class="access-left">
        <div class="access-left-inner">
            <div class="badge-restricted">
                <i class="bi bi-lock-fill"></i> Acceso Restringido · Personal Autorizado
            </div>
            <h1 class="access-title">Dashboard de<br>Gestión de Denuncias</h1>
            <p class="access-desc">
                Panel centralizado para la administración, investigación y seguimiento de
                denuncias recibidas a través del Canal de Integridad, conforme a la
                Ley N° 21.643 (Ley Karin).
            </p>

            <div class="feat-grid">
                <div class="feat-card">
                    <div class="feat-icon" style="background: linear-gradient(135deg, #0369a1, #0ea5e9);"><i class="bi bi-speedometer2"></i></div>
                    <h6>Panel de Control</h6>
                    <p>KPIs en tiempo real, gráficos y estadísticas.</p>
                </div>
                <div class="feat-card">
                    <div class="feat-icon" style="background: linear-gradient(135deg, #059669, #34d399);"><i class="bi bi-shield-check"></i></div>
                    <h6>Datos Protegidos</h6>
                    <p>Información confidencial y segura.</p>
                </div>
                <div class="feat-card">
                    <div class="feat-icon" style="background: linear-gradient(135deg, #7c3aed, #a78bfa);"><i class="bi bi-search"></i></div>
                    <h6>Investigación</h6>
                    <p>Gestión de casos, notas y resoluciones.</p>
                </div>
                <div class="feat-card">
                    <div class="feat-icon" style="background: linear-gradient(135deg, #dc2626, #f87171);"><i class="bi bi-graph-up"></i></div>
                    <h6>Reportes</h6>
                    <p>Auditoría completa y trazabilidad.</p>
                </div>
            </div>

            <p style="color: rgba(255,255,255,0.4); font-size: 0.68rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Roles con acceso</p>
            <div class="roles-row">
                <div class="role-chip admin"><i class="bi bi-shield-fill-check"></i> Administrador</div>
                <div class="role-chip inv"><i class="bi bi-person-badge"></i> Investigador</div>
                <div class="role-chip view"><i class="bi bi-eye"></i> Visor</div>
            </div>

            <div class="stats-row">
                <div style="text-align:center;"><div class="stat-num"><?= $dashStats['total'] ?? 0 ?></div><div class="stat-lbl">Denuncias</div></div>
                <div style="text-align:center;"><div class="stat-num"><?= $dashStats['recibidas'] ?? 0 ?></div><div class="stat-lbl">Pendientes</div></div>
                <div style="text-align:center;"><div class="stat-num"><?= $dashStats['resueltas'] ?? 0 ?></div><div class="stat-lbl">Resueltas</div></div>
            </div>
        </div>
    </div>

    <!-- DERECHA: Login -->
    <div class="access-right">
        <div class="login-box">
            <div class="login-head">
                <div class="login-logo">
                    <img src="/img/Logo01.png" alt="EPCO">
                </div>
                <h4>Iniciar Sesión</h4>
                <p>Ingresa tus credenciales para acceder</p>
            </div>

            <?php $flash = getFlashMessage(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-box mb-3">
                <i class="bi bi-info-circle"></i> <?= htmlspecialchars($flash['message']) ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-box mb-3">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="/acceso">
                <?= csrfInput() ?>

                <label for="identifier" class="form-label">Usuario o Email</label>
                <div class="input-group login-input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="identifier" id="identifier" class="form-control"
                           placeholder="Tu usuario o email" required autofocus
                           value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>">
                </div>

                <label for="password" class="form-label">Contraseña</label>
                <div class="input-group login-input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" id="password" class="form-control"
                           placeholder="Tu contraseña" required>
                    <button type="button" class="btn btn-eye" onclick="togglePwd()">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>

                <button type="submit" class="btn-access">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Acceder al Dashboard
                </button>
            </form>

            <div class="divider-label">Seguridad del portal</div>

            <div class="sec-list">
                <div class="sec-item"><i class="bi bi-shield-check"></i> Protección CSRF activa</div>
                <div class="sec-item"><i class="bi bi-lock-fill"></i> Bloqueo tras 5 intentos fallidos</div>
                <div class="sec-item"><i class="bi bi-clock-history"></i> Auditoría completa de accesos</div>
                <div class="sec-item"><i class="bi bi-shield-lock"></i> Datos protegidos y confidenciales</div>
            </div>
        </div>

        <a href="/" class="back-link"><i class="bi bi-arrow-left me-1"></i>Volver al portal público</a>
    </div>

</div>

<script>
function togglePwd() {
    const p = document.getElementById('password');
    const i = document.getElementById('eyeIcon');
    p.type = p.type === 'password' ? 'text' : 'password';
    i.className = p.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
