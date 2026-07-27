<?php
declare(strict_types=1);

/**
 * Sincroniza contactos y tickets desde SAS (clientes con más de 7 días
 * de atraso) y abre los tickets que falten. Pensado para correr:
 *
 *  - WAMP local: Programador de tareas de Windows apuntando a
 *    C:\wamp64\bin\php\php8.3.28\php.exe C:\wamp64\www\agenda\cron_apertura_tickets.php
 *  - Producción (cPanel): sección "Cron Jobs" del panel (ver DEPLOY.md, Fase 4).
 *
 * Corre por CLI, no por HTTP: sin sesión, sin la caché de 10 min que
 * usará lista_tickets.php en Fase 2 (esa caché es solo para no golpear
 * a SAS en cada refresh de pantalla).
 */

date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once __DIR__ . '/src/Tickets.php';

// Evita ejecuciones solapadas si el scheduler dispara una corrida
// nueva antes de que termine la anterior.
$lock = fopen(sys_get_temp_dir() . '/agenda_cron_apertura.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . "] Ya hay una sincronización en curso, se aborta.\n");
    exit(0);
}

$inicio    = microtime(true);
$resultado = Tickets::sincronizarDesdeSAS();
$duracion  = round(microtime(true) - $inicio, 2);

if ($resultado['ok']) {
    fwrite(STDOUT, sprintf(
        "[%s] Sincronización OK: %d contacto(s) procesado(s) en %ss.\n",
        date('Y-m-d H:i:s'),
        $resultado['procesados'],
        $duracion
    ));
} else {
    fwrite(STDERR, sprintf(
        "[%s] Sincronización con error: %s\n",
        date('Y-m-d H:i:s'),
        $resultado['error']
    ));
}

flock($lock, LOCK_UN);
fclose($lock);
