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

/**
 * JSON-encodea un valor para insertarlo como valor completo de un
 * atributo x-data/x-show de Alpine. Los flags JSON_HEX_* escapan
 * comillas simples/dobles, < > y & como \u00XX: el resultado es
 * simultáneamente JS válido (Alpine lo evalúa como expresión) y seguro
 * dentro de un atributo HTML entre comillas dobles, sin pasar también
 * por e()/htmlspecialchars (envolverlo en e() rompería el JSON).
 */
function json_attr(mixed $valor): string
{
    return json_encode(
        $valor,
        JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE
    );
}
