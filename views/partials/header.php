<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Agenda de Cobranza · Imperio Comercial</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body>
<?php if (Auth::check()): ?>
    <?php require __DIR__ . '/banner_recordatorio.php'; ?>
    <header class="app-header">
        <div class="app-header__marca">Agenda de Cobranza</div>
        <nav class="app-header__nav">
            <span class="app-header__usuario"><?= e(current_user()['nombre']) ?> · <?= e(current_user()['rol']) ?></span>
            <a class="btn btn--ghost btn--sm" href="index.php?p=logout">Salir</a>
        </nav>
    </header>
<?php endif; ?>
<main class="app-main">
