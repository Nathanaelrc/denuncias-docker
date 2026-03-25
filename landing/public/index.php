<?php
$urlKarin   = getenv('APP_URL_KARIN')   ?: 'http://localhost:8091';
$urlGeneral = getenv('APP_URL_GENERAL') ?: 'http://localhost:8093';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPCO – Portales de Denuncia</title>
    <link rel="icon" type="image/png" href="/img/Logo01.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Onest:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            font-family: 'Onest', system-ui, sans-serif;
            background: #1a6591;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 24px;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 44px;
            animation: fadeIn 0.5s ease both;
        }
        .header-logo {
            display: block;
            height: 64px;
            width: auto;
            margin: 0 auto 16px;
            filter: brightness(0) invert(1);
            object-fit: contain;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 5px 14px;
            border-radius: 100px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #ffffff;
            margin-bottom: 16px;
        }
        .header h1 {
            font-size: clamp(1.6rem, 3.5vw, 2.4rem);
            font-weight: 800;
            color: #ffffff;
        }
        .header-org {
            font-size: 1rem;
            font-weight: 600;
            color: rgba(255,255,255,0.75);
            margin-top: -4px;
            margin-bottom: 4px;
            letter-spacing: 0.5px;
        }
        .header p {
            margin-top: 8px;
            color: rgba(255,255,255,0.8);
            font-size: 0.95rem;
        }

        /* Grid */
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
            width: 100%;
            max-width: 880px;
        }
        @media (max-width: 640px) {
            .grid { grid-template-columns: 1fr; max-width: 420px; }
        }

        /* Card */
        .card {
            background: #ffffff;
            border-radius: 18px;
            padding: 32px;
            display: flex;
            flex-direction: column;
            border-top: 4px solid transparent;
            box-shadow: 0 4px 24px rgba(0,0,0,0.15);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            animation: fadeUp 0.5s ease both;
        }
        .card:nth-child(1) { animation-delay: 0.1s; border-top-color: #145275; }
        .card:nth-child(2) { animation-delay: 0.2s; border-top-color: #145275; }
        .card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
        }

        /* Icono */
        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem;
            margin-bottom: 20px;
            flex-shrink: 0;
        }
        .card:nth-child(1) .card-icon { background: #e8f0f6; color: #145275; }
        .card:nth-child(2) .card-icon { background: #e8f0f6; color: #145275; }

        /* Label */
        .card-label {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 1.8px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .card:nth-child(1) .card-label { color: #145275; }
        .card:nth-child(2) .card-label { color: #145275; }

        /* Título */
        .card-title {
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1.25;
            color: #1e293b;
            margin-bottom: 10px;
        }

        /* Descripción */
        .card-desc {
            font-size: 0.88rem;
            line-height: 1.65;
            color: #64748b;
            margin-bottom: 20px;
            flex: 1;
        }

        /* Tags */
        .tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 24px;
        }
        .tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 500;
        }
        .card:nth-child(1) .tag { background: #e8f0f6; color: #145275; }
        .card:nth-child(2) .tag { background: #e8f0f6; color: #145275; }

        /* Botón principal */
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 13px 20px;
            border-radius: 12px;
            font-size: 0.93rem;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
            transition: background 0.15s ease, transform 0.12s ease;
        }
        .btn:hover { transform: scale(1.02); }
        .card:nth-child(1) .btn { background: #1a6591; }
        .card:nth-child(1) .btn:hover { background: #145275; }
        .card:nth-child(2) .btn { background: #1a6591; }
        .card:nth-child(2) .btn:hover { background: #145275; }

        /* Link secundario */
        .link-sec {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            margin-top: 10px;
            font-size: 0.8rem;
            color: #94a3b8;
            text-decoration: none;
            transition: color 0.15s;
        }
        .link-sec:hover { color: #475569; }

        /* Footer */
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 0.76rem;
            color: rgba(255,255,255,0.6);
            animation: fadeIn 0.5s ease both;
            animation-delay: 0.3s;
        }
        .footer a { color: rgba(255,255,255,0.7); text-decoration: none; }
        .footer a:hover { color: #ffffff; }

        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <div class="header">
        <img src="/img/Logo01.png" alt="Empresa Portuaria Coquimbo" class="header-logo">
        <div class="badge"><i class="bi bi-shield-check"></i> Plataforma segura de denuncias</div>
        <h1>Portales de denuncia</h1>
        <p class="header-org">Empresa Portuaria Coquimbo</p>
        <p>Selecciona el portal que corresponde a tu situación</p>
    </div>

    <div class="grid">

        <!-- Card Ley Karin -->
        <div class="card">
            <div class="card-icon"><i class="bi bi-shield-lock-fill"></i></div>
            <div class="card-label">Ley N° 21.643 · Normativa Laboral</div>
            <div class="card-title">Canal de Denuncias Ley Karin</div>
            <p class="card-desc">
                Para trabajadores que necesitan denunciar acoso laboral, sexual
                o violencia en el trabajo dentro de su organización.
            </p>
            <div class="tags">
                <span class="tag"><i class="bi bi-person-fill-exclamation"></i> Acoso laboral</span>
                <span class="tag"><i class="bi bi-exclamation-triangle-fill"></i> Acoso sexual</span>
                <span class="tag"><i class="bi bi-person-fill-x"></i> Violencia laboral</span>
            </div>
            <a href="<?php echo htmlspecialchars($urlKarin); ?>" class="btn">
                <i class="bi bi-box-arrow-in-right"></i> Ingresar al canal
            </a>
            <a href="<?php echo htmlspecialchars($urlKarin); ?>/seguimiento" class="link-sec">
                <i class="bi bi-search"></i> Consultar estado de mi denuncia
            </a>
        </div>

        <!-- Card Portal Ciudadano -->
        <div class="card">
            <div class="card-icon"><i class="bi bi-people-fill"></i></div>
            <div class="card-label">Legislación Chilena General</div>
            <div class="card-title">Canal de Denuncias</div>
            <p class="card-desc">
                Para ciudadanos que desean ejercer sus derechos como consumidores,
                usuarios de servicios públicos o en otros ámbitos de la ley chilena.
            </p>
            <div class="tags">
                <span class="tag"><i class="bi bi-cart-fill"></i> Consumidor</span>
                <span class="tag"><i class="bi bi-heart-pulse-fill"></i> Salud</span>
                <span class="tag"><i class="bi bi-building-fill"></i> Sector Público</span>
                <span class="tag"><i class="bi bi-tree-fill"></i> Medioambiente</span>
            </div>
            <a href="<?php echo htmlspecialchars($urlGeneral); ?>" class="btn">
                <i class="bi bi-box-arrow-in-right"></i> Ingresar al portal
            </a>
            <a href="<?php echo htmlspecialchars($urlGeneral); ?>/seguimiento" class="link-sec">
                <i class="bi bi-search"></i> Consultar estado de mi denuncia
            </a>
        </div>

    </div>

    <div class="footer">
        &copy; <?php echo date('Y'); ?> EPCO &nbsp;&middot;&nbsp; Cifrado AES-256
        &nbsp;&middot;&nbsp;
        <a href="<?php echo htmlspecialchars($urlKarin); ?>/iniciar_sesion">Acceso interno Ley Karin</a>
        &nbsp;&middot;&nbsp;
        <a href="<?php echo htmlspecialchars($urlGeneral); ?>/iniciar_sesion">Acceso interno Portal Ciudadano</a>
    </div>

</body>
</html>
