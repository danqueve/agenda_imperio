<?php
declare(strict_types=1);

/**
 * Íconos SVG inline (paths tomados de Lucide, lucide.dev, licencia ISC -
 * libre para embeber sin atribución obligatoria). Sin dependencias
 * externas ni build step: cada uno es un string estático.
 *
 * IMPORTANTE: el resultado es HTML de confianza (siempre sale de este
 * array fijo, nunca de datos externos) - se imprime tal cual, SIN pasar
 * por e(): `<?= icon('calendario') ?>`. Envolverlo en e() escaparía el
 * <svg> y se vería como texto literal en la pantalla.
 *
 * El ícono de "whatsapp" usa un glifo de chat genérico (message-circle),
 * no el logo de marca real, para no depender de lineamientos de marca de
 * terceros por un beneficio estético marginal.
 */
function icon(string $nombre, string $claseExtra = ''): string
{
    static $paths = [
        'severidad'    => '<circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>',
        'calendario'   => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        'check'        => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'paquete'      => '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><polyline points="3.29 7 12 12 20.71 7"/><path d="m7.5 4.27 9 5.15"/>',
        'refinanciar'  => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
        'telefono'     => '<path d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 6.392 6.384"/>',
        'whatsapp'     => '<path d="M2.992 16.342a2 2 0 0 1 .094 1.167l-1.065 3.29a1 1 0 0 0 1.236 1.168l3.413-.998a2 2 0 0 1 1.099.092 10 10 0 1 0-4.777-4.719"/>',
        'cerrar'       => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
        'gestion'      => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
        'chevron-abajo'=> '<path d="m6 9 6 6 6-6"/>',
        'chevron-arriba' => '<path d="m18 15-6-6-6 6"/>',
        'ver'          => '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>',
        'ocultar'      => '<path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/>',
        'buscar'       => '<path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/>',
    ];

    if (!isset($paths[$nombre])) {
        return '';
    }

    $clase = trim('icon ' . $claseExtra);

    return '<svg class="' . $clase . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . $paths[$nombre] . '</svg>';
}
