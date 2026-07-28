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
        <a class="app-header__marca" href="index.php?p=lista_tickets">Agenda de Cobranza</a>
        <nav class="app-header__nav">
            <a class="btn btn--ghost btn--sm" href="index.php?p=dashboard">Dashboard</a>
            <?php if (Auth::esAdmin()): ?>
                <a class="btn btn--ghost btn--sm" href="index.php?p=administrar_usuarios">Usuarios</a>
            <?php endif; ?>
            <span class="app-header__usuario"><?= e(current_user()['nombre']) ?> · <?= e(current_user()['rol']) ?></span>
            <a class="btn btn--ghost btn--sm" href="index.php?p=logout">Salir</a>
        </nav>
    </header>
<?php endif; ?>
<?php
// Páginas de "explorar muchas tarjetas" aprovechan el ancho real de
// pantalla en escritorio; el resto (ficha, formularios, login) se queda
// en la columna angosta de siempre - ver app.css, .app-main--ancho.
$anchoCompleto = in_array($p ?? '', ['lista_tickets', 'dashboard'], true);
?>
<main class="app-main<?= $anchoCompleto ? ' app-main--ancho' : '' ?>">
