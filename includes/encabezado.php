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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Tailwind CSS (sin preflight para no conflictar con Bootstrap) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { corePlugins: { preflight: false } }</script>

    <!-- GSAP -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        .card-epco:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
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

        .fade-in { opacity: 0; transform: translateY(30px); }
        .slide-in-left { opacity: 0; transform: translateX(-50px); }
        .slide-in-right { opacity: 0; transform: translateX(50px); }
        .scale-in { opacity: 0; transform: scale(0.9); }

        /* Animación de entrada de página */
        @keyframes pageEntrance {
            0% { opacity: 0; transform: translateY(15px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        @keyframes navSlideDown {
            0% { opacity: 0; transform: translateY(-100%); }
            100% { opacity: 1; transform: translateY(0); }
        }

        @keyframes subtlePulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.2); }
            50% { box-shadow: 0 0 0 10px rgba(255, 255, 255, 0); }
        }

        .page-entrance {
            animation: pageEntrance 0.6s ease-out forwards;
        }

        .nav-animate {
            animation: navSlideDown 0.5s ease-out forwards;
        }

        .pulse-soft {
            animation: subtlePulse 3s ease-in-out infinite;
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
</head>
<body>
