<?php
if (!defined('GENERALES_APP')) {
    define('GENERALES_APP', true);
    require_once __DIR__ . '/../config/app.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Canal de Denuncias' ?> - Empresa Portuaria Coquimbo</title>

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
            --accent-light: #2d9ad0;
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
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            color: #fff;
        }
        .card-epco:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 45px rgba(0,0,0,0.22);
            border-color: rgba(255,255,255,0.18);
        }

        .navbar-epco {
            background: rgba(26, 101, 145, 0.95) !important;
            backdrop-filter: blur(10px);
        }

        .navbar-epco .navbar-brand,
        .navbar-epco .navbar-brand span,
        .navbar-epco .nav-link,
        .navbar-epco .navbar-text { color: #ffffff !important; }

        .navbar-epco .nav-link:hover,
        .navbar-epco .nav-link:focus { color: rgba(255,255,255,0.85) !important; }

        .navbar-epco .nav-link.active { color: #ffffff !important; font-weight: 600; }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(26, 101, 145, 0.25);
        }

        .gradient-bg {
            background: linear-gradient(135deg, #1a6591 0%, #2380b0 50%, #2d9ad0 100%);
        }

        .fade-in { opacity: 0; transform: translateY(12px); }
        .slide-in-left { opacity: 0; transform: translateX(-20px); }
        .slide-in-right { opacity: 0; transform: translateX(20px); }
        .scale-in { opacity: 0; transform: scale(0.97); }

        @keyframes pageEntrance {
            0% { opacity: 0; transform: translateY(8px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        @keyframes navSlideDown {
            0% { opacity: 0; transform: translateY(-100%); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .page-entrance { animation: pageEntrance 0.8s ease-out forwards; }
        .nav-animate { animation: navSlideDown 0.6s ease-out forwards; }

        .legal-badge {
            background: linear-gradient(135deg, #1a6591, #2380b0);
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

        .text-justify {
            text-align: justify;
            text-justify: inter-word;
            hyphens: auto;
        }

        /* Dark blue theme: forms, text, cards */
        .card-epco .form-label,
        .card-epco h2, .card-epco h3, .card-epco h4, .card-epco h5, .card-epco h6,
        .card-epco .fw-bold, .card-epco .fw-semibold,
        .card-epco .text-dark { color: #fff !important; }
        .card-epco .text-muted { color: rgba(255,255,255,0.55) !important; }
        .card-epco .form-text { color: rgba(255,255,255,0.45) !important; }

        .card-epco .form-control,
        .card-epco .form-select {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            color: #fff;
        }
        .card-epco .form-control::placeholder { color: rgba(255,255,255,0.35); }
        .card-epco .form-control:focus,
        .card-epco .form-select:focus {
            background: rgba(255,255,255,0.12);
            border-color: #2d9ad0;
            color: #fff;
            box-shadow: 0 0 0 0.2rem rgba(45,154,208,0.25);
        }
        .card-epco .btn-outline-dark {
            color: rgba(255,255,255,0.75);
            border-color: rgba(255,255,255,0.2);
        }
        .card-epco .btn-outline-dark:hover,
        .card-epco .btn-check:checked + .btn-outline-dark {
            background: rgba(255,255,255,0.12);
            border-color: #2d9ad0;
            color: #fff;
        }
        .card-epco .alert-info {
            background: rgba(45,154,208,0.15);
            border-color: rgba(45,154,208,0.3);
            color: rgba(255,255,255,0.85);
        }
        .card-epco .alert-warning {
            background: rgba(245,158,11,0.15);
            border-color: rgba(245,158,11,0.3);
            color: rgba(255,255,255,0.9);
        }
        .card-epco .alert-danger {
            background: rgba(239,68,68,0.15);
            border-color: rgba(239,68,68,0.3);
            color: rgba(255,255,255,0.9);
        }
        .card-epco .bg-light {
            background: rgba(255,255,255,0.06) !important;
        }
        .card-epco .form-check-input {
            background-color: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.2);
        }
        .card-epco .form-check-input:checked {
            background-color: #2380b0;
            border-color: #2380b0;
        }

        .section-dark {
            background: #145275;
        }

        /* Bootstrap overrides for dark blue theme */
        .bg-dark-blue { background: rgba(255,255,255,0.06) !important; }

        .card {
            background: rgba(255,255,255,0.06);
            border-color: rgba(255,255,255,0.1);
            color: #fff;
        }
        .card .card-header {
            background: rgba(255,255,255,0.04) !important;
            border-color: rgba(255,255,255,0.08);
            color: #fff;
        }
        .card .card-footer {
            background: rgba(255,255,255,0.04) !important;
            border-color: rgba(255,255,255,0.08);
        }
        .card .card-body { color: #fff; }
        .card .text-dark, .text-dark { color: #fff !important; }
        .text-muted { color: rgba(255,255,255,0.55) !important; }

        .table { color: #fff; --bs-table-bg: transparent; }
        .table th { color: rgba(255,255,255,0.7); border-color: rgba(255,255,255,0.08); }
        .table td { border-color: rgba(255,255,255,0.06); }
        .table-hover tbody tr:hover { background: rgba(255,255,255,0.04); }

        .border { border-color: rgba(255,255,255,0.1) !important; }
        .border-top, .border-bottom { border-color: rgba(255,255,255,0.08) !important; }

        .form-control, .form-select {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.15);
            color: #fff;
        }
        .form-control::placeholder { color: rgba(255,255,255,0.35); }
        .form-control:focus, .form-select:focus {
            background: rgba(255,255,255,0.12);
            border-color: #2d9ad0;
            color: #fff;
            box-shadow: 0 0 0 0.2rem rgba(45,154,208,0.25);
        }
        .form-control:disabled, .form-control[readonly] {
            background: rgba(255,255,255,0.04);
            color: rgba(255,255,255,0.5);
        }
        .input-group-text {
            background: rgba(255,255,255,0.06);
            border-color: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.55);
        }

        .badge { color: #fff; }
        .modal-content {
            background: #1a6591;
            border: 1px solid rgba(255,255,255,0.12);
            color: #fff;
        }
        .modal-header { border-color: rgba(255,255,255,0.1); }
        .modal-footer { border-color: rgba(255,255,255,0.1); }
        .btn-close { filter: invert(1); }

        .alert-success { background: rgba(45,154,208,0.15); border-color: rgba(45,154,208,0.3); color: rgba(255,255,255,0.9); }
        .alert-info { background: rgba(45,154,208,0.15); border-color: rgba(45,154,208,0.3); color: rgba(255,255,255,0.85); }
        .alert-warning { background: rgba(245,158,11,0.15); border-color: rgba(245,158,11,0.3); color: rgba(255,255,255,0.9); }
        .alert-danger { background: rgba(239,68,68,0.15); border-color: rgba(239,68,68,0.3); color: rgba(255,255,255,0.9); }

        .pagination .page-link {
            background: rgba(255,255,255,0.06);
            border-color: rgba(255,255,255,0.1);
            color: #93c5fd;
        }
        .pagination .page-item.active .page-link {
            background: #2380b0;
            border-color: #2380b0;
            color: #fff;
        }
        .pagination .page-link:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }

        a { color: #93c5fd; }
        a:hover { color: #b3d4f7; }
        .btn-outline-secondary {
            color: rgba(255,255,255,0.7);
            border-color: rgba(255,255,255,0.2);
        }
        .btn-outline-secondary:hover {
            background: rgba(255,255,255,0.08);
            color: #fff;
        }
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
