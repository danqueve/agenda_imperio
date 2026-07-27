<?php
declare(strict_types=1);

/** Escapa para salida HTML segura. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** Datos del usuario logueado (id, nombre, usuario, rol), o null si no hay sesión. */
function current_user(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

/** Etiqueta legible para el tipo de agenda de cobro (usado en el banner y en el dashboard). */
function agenda_tipo_label(string $tipo): string
{
    return match ($tipo) {
        'cliente_viene' => 'viene al local',
        'se_visita'      => 'se lo visita',
        'transferencia'  => 'transferencia',
        default          => $tipo,
    };
}
