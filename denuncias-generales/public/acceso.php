<?php
/**
 * Portal Denuncias Ciudadanas - Acceso al Dashboard
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if (isLoggedIn()) { header('Location: /panel'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Token de seguridad inválido.';
    } else {
        $identifier = sanitize($_POST['identifier'] ?? '');
        $password   = $_POST['password'] ?? '';
        if (empty($identifier) || empty($password)) {
            $error = 'Completa todos los campos.';
        } elseif (login($identifier, $password)) {
            logActivity($_SESSION['user_id'], 'login', 'user', $_SESSION['user_id'], 'Login portal ciudadano');
            header('Location: /panel'); exit;
        } else {
            $error = 'Credenciales incorrectas o cuenta bloqueada.';
            logActivity(null, 'login_fallido', null, null, "Intento: $identifier");
        }
    }
}

$dashStats = ['total' => 0, 'recibidas' => 0, 'resueltas' => 0];
try {
    if (isset($pdo)) {
        $dashStats = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='recibida' THEN 1 ELSE 0 END) as recibidas, SUM(CASE WHEN status='resuelta' THEN 1 ELSE 0 END) as resueltas FROM complaints")->fetch();
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso - Portal Ciudadano de Denuncias EPCO</title>
    <link rel="icon" type="image/png" href="/img/Logo01.png">
    <link href="https://fonts.googleapis.com/css2?family=Onest:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { font-family: 'Onest', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #1a6591; min-height: 100vh; }

        .access-nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100; height: 60px;
            background: rgba(26,101,145,0.95); backdrop-filter: blur(12px);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 25px; border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .access-nav-brand { display: flex; align-items: center; gap: 10px; color: white; text-decoration: none; font-weight: 700; font-size: 1rem; }
        .access-nav-brand img { height: 32px; width: auto; }
        .nav-links { display: flex; align-items: center; gap: 20px; list-style: none; margin: 0; padding: 0; }
        .nav-links a { color: rgba(255,255,255,0.65); text-decoration: none; font-size: 0.88rem; transition: color 0.3s; }
        .nav-links a:hover { color: white; }

        .access-split { display: flex; min-height: 100vh; padding-top: 60px; }

        .access-left {
            flex: 1; padding: 50px 45px;
            background: linear-gradient(160deg, #1a6591, #2380b0 50%, #2d9ad0);
            display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden;
        }
        .access-left::before {
            content: ''; position: absolute; top: -40%; right: -15%; width: 350px; height: 350px;
            background: radial-gradient(circle, rgba(255,255,255,0.04), transparent 70%); border-radius: 50%;
        }
        .access-left-inner { position: relative; z-index: 1; max-width: 520px; }

        .badge-restricted {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
            color: #93c5fd; padding: 5px 12px; border-radius: 20px;
            font-size: 0.78rem; font-weight: 600; margin-bottom: 22px;
        }
        .access-title { font-size: 2rem; font-weight: 800; color: white; line-height: 1.25; margin-bottom: 12px; }
        .access-desc { font-size: 0.98rem; color: rgba(255,255,255,0.55); line-height: 1.7; margin-bottom: 35px; }

        .feat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 30px; }
        .feat-card {
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px; padding: 18px; transition: all 0.3s;
        }
        .feat-card:hover { background: rgba(255,255,255,0.09); transform: translateY(-2px); }
        .feat-icon { width: 38px; height: 38px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 1rem; color: white; margin-bottom: 10px; }
        .feat-card h6 { color: white; font-weight: 700; font-size: 0.85rem; margin-bottom: 3px; }
        .feat-card p { color: rgba(255,255,255,0.45); font-size: 0.75rem; margin: 0; line-height: 1.5; }

        .stats-row { display: flex; gap: 28px; padding-top: 18px; border-top: 1px solid rgba(255,255,255,0.1); }
        .stat-num { font-size: 1.3rem; font-weight: 800; color: white; }
        .stat-lbl { font-size: 0.7rem; color: rgba(255,255,255,0.45); text-transform: uppercase; letter-spacing: 0.5px; }

        .access-right {
            width: 480px; min-width: 480px;
            background: linear-gradient(160deg, #1a6591 0%, #2380b0 100%);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 50px 40px; position: relative;
        }
        .login-card {
            width: 100%; max-width: 380px;
            background: #ffffff;
            border-radius: 20px;
            padding: 38px 36px 32px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25), 0 4px 16px rgba(0,0,0,0.12);
        }
        .login-box { width: 100%; }
        .login-head { text-align: center; margin-bottom: 28px; }
        .login-logo img { height: 58px; width: auto; margin-bottom: 14px; }
        .login-head h4 { font-weight: 800; color: #1a6591; margin-bottom: 4px; font-size: 1.2rem; }
        .login-head p { color: #64748b; font-size: 0.87rem; }

        .login-input-group { border-radius: 10px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.08); margin-bottom: 14px; }
        .login-input-group .input-group-text { background: #f1f5f9; border: 1.5px solid #e2e8f0; border-right: none; color: #94a3b8; }
        .login-input-group .form-control { background: #f8fafc; border: 1.5px solid #e2e8f0; border-left: none; padding: 12px 14px; font-size: 0.93rem; color: #1e293b; }
        .login-input-group .form-control::placeholder { color: #94a3b8; }
        .login-input-group .form-control:focus { border-color: #2380b0; box-shadow: none; background: #fff; outline: none; }
        .login-input-group:focus-within .input-group-text { border-color: #2380b0; background: #eff6ff; color: #2380b0; }
        .btn-eye { background: #f8fafc; border: 1.5px solid #e2e8f0; border-left: none; color: #94a3b8; cursor: pointer; transition: all 0.2s; }
        .btn-eye:hover { background: #eff6ff; color: #2380b0; }

        .btn-access {
            width: 100%; background: linear-gradient(135deg, #1a6591, #2380b0);
            color: white; border: none; padding: 13px; font-weight: 700;
            font-size: 0.97rem; border-radius: 10px; transition: all 0.3s; cursor: pointer;
            margin-top: 6px; letter-spacing: 0.2px;
        }
        .btn-access:hover { background: linear-gradient(135deg, #2380b0, #2d9ad0); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(26,101,145,0.4); }

        .login-divider { border: none; border-top: 1px solid #e2e8f0; margin: 20px 0 16px; }

        .sec-list { display: flex; flex-direction: column; gap: 7px; }
        .sec-item { display: flex; align-items: center; gap: 8px; color: #64748b; font-size: 0.78rem; }
        .sec-item i { width: 24px; height: 24px; background: #eff6ff; color: #2380b0; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.72rem; flex-shrink: 0; }

        .back-link { display: block; text-align: center; margin-top: 18px; color: rgba(255,255,255,0.6); text-decoration: none; font-size: 0.83rem; transition: color 0.3s; }
        .back-link:hover { color: #ffffff; }

        @media (max-width: 992px) {
            .access-split { flex-direction: column; }
            .access-left { padding: 35px 20px; }
            .access-right { width: 100%; min-width: 100%; padding: 35px 20px; }
            .login-card { max-width: 100%; }
            .feat-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<nav class="access-nav">
    <a href="/" class="access-nav-brand">
        <img src="/img/Logo01.png" alt="EPCO" onerror="this.style.display='none'">
        <span>Portal Ciudadano</span>
    </a>
    <ul class="nav-links">
        <li><a href="/"><i class="bi bi-house me-1"></i>Inicio</a></li>
        <li><a href="/nueva_denuncia"><i class="bi bi-pencil-square me-1"></i>Realizar Denuncia</a></li>
        <li><a href="/seguimiento"><i class="bi bi-search me-1"></i>Seguimiento</a></li>
    </ul>
</nav>

<div class="access-split">
    <div class="access-left">
        <div class="access-left-inner">
            <div class="badge-restricted"><i class="bi bi-lock-fill"></i> Acceso Restringido · Personal Autorizado</div>
            <h1 class="access-title">Panel de Gestión<br>de Denuncias Ciudadanas</h1>
            <p class="access-desc">
                Plataforma centralizada para la revisión, seguimiento y resolución de denuncias ciudadanas
                recibidas conforme a la legislación chilena vigente (Ley 19.496, Ley 19.880, Ley 19.300 y otras).
            </p>
            <div class="feat-grid">
                <div class="feat-card">
                    <div class="feat-icon" style="background:linear-gradient(135deg,#1a6591,#2d9ad0);"><i class="bi bi-speedometer2"></i></div>
                    <h6>Panel de Control</h6><p>KPIs en tiempo real, gráficos y estadísticas.</p>
                </div>
                <div class="feat-card">
                    <div class="feat-icon" style="background:linear-gradient(135deg,#1a6591,#4db8e0);"><i class="bi bi-shield-check"></i></div>
                    <h6>Datos Protegidos</h6><p>Información cifrada AES-256 por defecto.</p>
                </div>
                <div class="feat-card">
                    <div class="feat-icon" style="background:linear-gradient(135deg,#7c3aed,#a78bfa);"><i class="bi bi-search"></i></div>
                    <h6>Revisión de Casos</h6><p>Gestión de denuncias, notas y resoluciones.</p>
                </div>
                <div class="feat-card">
                    <div class="feat-icon" style="background:linear-gradient(135deg,#dc2626,#f87171);"><i class="bi bi-graph-up"></i></div>
                    <h6>Reportes</h6><p>Auditoría completa y trazabilidad.</p>
                </div>
            </div>
            <div class="stats-row">
                <div style="text-align:center;"><div class="stat-num"><?= $dashStats['total'] ?? 0 ?></div><div class="stat-lbl">Denuncias</div></div>
                <div style="text-align:center;"><div class="stat-num"><?= $dashStats['recibidas'] ?? 0 ?></div><div class="stat-lbl">Pendientes</div></div>
                <div style="text-align:center;"><div class="stat-num"><?= $dashStats['resueltas'] ?? 0 ?></div><div class="stat-lbl">Resueltas</div></div>
            </div>
        </div>
    </div>

    <div class="access-right">
        <div class="login-card">
            <div class="login-box">
                <div class="login-head">
                    <div class="login-logo">
                        <img src="/img/Logo01.png" alt="EPCO" onerror="this.style.display='none'">
                    </div>
                    <h4>Iniciar Sesión</h4>
                    <p>Acceso para delegados y administradores</p>
                </div>

                <?php $flash = getFlashMessage(); if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'info' ?> py-2 small mb-3"><?= htmlspecialchars($flash['message']) ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-danger py-2 small mb-3"><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="/acceso">
                    <?= csrfInput() ?>
                    <div class="login-input-group input-group mb-3">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="identifier" class="form-control" placeholder="Usuario o correo" value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>" required autofocus>
                    </div>
                    <div class="login-input-group input-group mb-4">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="passInput" class="form-control" placeholder="Contraseña" required>
                        <button type="button" class="btn-eye" onclick="togglePass()"><i class="bi bi-eye" id="eyeIcon"></i></button>
                    </div>
                    <button type="submit" class="btn-access"><i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión</button>
                </form>

                <hr class="login-divider">

                <div class="sec-list">
                    <div class="sec-item"><i class="bi bi-shield-lock"></i>Conexión segura y datos encriptados</div>
                    <div class="sec-item"><i class="bi bi-clock-history"></i>Sesión con expiración automática</div>
                    <div class="sec-item"><i class="bi bi-journal-text"></i>Acceso registrado en auditoría</div>
                </div>
            </div>
        </div>
        <a href="/" class="back-link"><i class="bi bi-arrow-left me-1"></i>Volver al portal público</a>
    </div>
</div>

<script>
function togglePass() {
    const i = document.getElementById('passInput'), e = document.getElementById('eyeIcon');
    i.type = i.type === 'password' ? 'text' : 'password';
    e.className = i.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>
</body>
</html>
