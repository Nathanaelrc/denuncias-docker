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

    <!-- Estilos del portal -->
    <link rel="stylesheet" href="/css/app.css">

    <!-- GSAP -->
    <script src="<?= CDN_GSAP ?>"></script>

    <!-- Chart.js -->
    <script src="<?= CDN_CHARTJS ?>"></script>
</head>
<body>
