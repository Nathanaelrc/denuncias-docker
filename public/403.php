<?php $pageTitle = 'Acceso Denegado'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Canal de Denuncias Empresa Portuaria Coquimbo</title>
    <link href="https://fonts.googleapis.com/css2?family=Onest:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { font-family: 'Onest', sans-serif; }
        body { background: linear-gradient(135deg, #1a6591 0%, #2380b0 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; color: white; }
        .error-card { background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); border-radius: 20px; padding: 50px; text-align: center; max-width: 500px; }
    </style>
</head>
<body>
    <div class="error-card">
        <i class="bi bi-shield-x" style="font-size: 4rem; color: #ef4444;"></i>
        <h1 class="mt-3 fw-bold">403</h1>
        <p class="opacity-75">Acceso denegado. No tienes permisos para ver esta página.</p>
        <a href="/" style="color: #60a5fa; text-decoration: none;"><i class="bi bi-arrow-left me-1"></i>Volver al inicio</a>
    </div>
</body>
</html>
