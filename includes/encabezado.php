<?php
if (!defined('DENUNCIAS_APP')) {
    define('DENUNCIAS_APP', true);
    require_once __DIR__ . '/../config/app.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Canal de Denuncias' ?> - Empresa Portuaria Coquimbo</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/img/Logo01.png">
    <link rel="shortcut icon" type="image/png" href="/img/Logo01.png">

    <!-- Google Fonts - Onest -->
    <link href="https://fonts.googleapis.com/css2?family=Onest:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="<?= CDN_BS_CSS ?>" rel="stylesheet">
    <link href="<?= CDN_BS_ICONS ?>" rel="stylesheet">

    <!-- GSAP -->
    <script src="<?= CDN_GSAP ?>"></script>

    <!-- Chart.js -->
    <script src="<?= CDN_CHARTJS ?>"></script>

    <style>
        :root {
            --primary-color: #1a6591;
            --secondary-color: #ffffff;
            --accent-color: #2380b0;
            --accent-light: #2d4a6f;
        }

        * { font-family: 'Onest', sans-serif; }

        body {
            background: var(--primary-color);
            color: var(--secondary-color);
            min-height: 100vh;
        }

        .bg-primary-dark { background-color: var(--primary-color) !important; }
        .bg-accent { background-color: var(--accent-color) !important; }
        .text-primary-dark { color: var(--primary-color) !important; }

        .modal-content { color: #1e293b; }

        .btn-epco {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            transition: all 0.3s ease;
        }
        .btn-epco:hover {
            background-color: var(--accent-light);
            border-color: var(--accent-light);
            color: white;
            transform: translateY(-2px);
        }

        .card-epco {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        .card-epco:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 45px rgba(0,0,0,0.22);
        }

        .navbar-epco {
            background: rgba(10, 37, 64, 0.95) !important;
            backdrop-filter: blur(10px);
        }

        .navbar-epco .navbar-brand,
        .navbar-epco .navbar-brand span,
        .navbar-epco .nav-link,
        .navbar-epco .navbar-text {
            color: #ffffff !important;
        }

        .navbar-epco .nav-link:hover,
        .navbar-epco .nav-link:focus {
            color: rgba(255, 255, 255, 0.85) !important;
        }

        .navbar-epco .nav-link.active {
            color: #ffffff !important;
            font-weight: 600;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(10, 37, 64, 0.25);
        }

        .gradient-bg {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
        }

        .fade-in { opacity: 0; transform: translateY(12px); }
        .slide-in-left { opacity: 0; transform: translateX(-20px); }
        .slide-in-right { opacity: 0; transform: translateX(20px); }
        .scale-in { opacity: 0; transform: scale(0.97); }

        /* Animación de entrada de página */
        @keyframes pageEntrance {
            0% { opacity: 0; transform: translateY(8px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        @keyframes navSlideDown {
            0% { opacity: 0; transform: translateY(-100%); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .page-entrance {
            animation: pageEntrance 0.8s ease-out forwards;
        }

        .nav-animate {
            animation: navSlideDown 0.6s ease-out forwards;
        }

        .pulse-soft {
            /* desactivado: animación de pulso no apta para contexto institucional */
        }

        .legal-badge {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: white;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
        }

        .stagger-1 { animation-delay: 0.1s; }
        .stagger-2 { animation-delay: 0.2s; }
        .stagger-3 { animation-delay: 0.3s; }
        .stagger-4 { animation-delay: 0.4s; }
    </style>

    <style>
        .btn-identidad {
            border: 2px solid #e2e8f0;
            background: #f8fafc;
            color: #475569;
            transition: all 0.3s ease;
            border-radius: 12px;
        }
        .btn-identidad:hover {
            border-color: #cbd5e1;
            background: #f1f5f9;
        }
        .btn-check:checked + .btn-identidad {
            border-color: #0d6efd;
            background: #eef1f6;
            color: #084298;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.15);
        }
        .btn-check:checked + .btn-identidad strong {
            color: #084298;
        }
        .btn-check:checked + .btn-identidad i {
            transform: scale(1.1);
        }
    </style>
</head>
<body>
