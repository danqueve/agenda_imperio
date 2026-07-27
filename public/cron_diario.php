<?php
declare(strict_types=1);

/**
 * Marca como 'incumplida' las agendas 'pendiente' cuya fecha ya pasó.
 * Pensado para correr una vez por día, dos formas posibles:
 *  - CLI: C:\wamp64\bin\php\php8.3.28\php.exe C:\wamp64\www\agenda\public\cron_diario.php
 *    (WAMP local, Programador de tareas de Windows) o Cron Jobs de cPanel
 *    en producción (ver DEPLOY.md, Fase 4).
 *  - URL con token: GET /cron_diario.php?token=... , para hosts donde
 *    solo se puede disparar un cron pegándole a una URL.
 * Vive en public/ (no en la raíz, como cron_apertura_tickets.php) para
 * seguir siendo alcanzable por HTTP incluso cuando el Document Root de
 * producción apunte a agenda/public (ver DEPLOY.md).
 *
 * Idempotente: correrlo más de una vez el mismo día no vuelve a tocar lo
 * que ya quedó en 'incumplida' ni afecta agendas de hoy o futuras.
 */

date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once __DIR__ . '/../config/database.php';

const CRON_DIARIO_TOKEN = 'CAMBIAR_este_token_del_cron_2026'; // AJUSTAR: token propio, distinto al de SAS

$esCli = PHP_SAPI === 'cli';

if (!$esCli) {
    header('Content-Type: text/plain; charset=utf-8');
    if (!hash_equals(CRON_DIARIO_TOKEN, (string) ($_GET['token'] ?? ''))) {
        http_response_code(403);
        echo "Token invalido\n";
        exit;
    }
}

$lock = fopen(sys_get_temp_dir() . '/agenda_cron_diario.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo '[' . date('Y-m-d H:i:s') . "] Ya hay una corrida en curso, se aborta.\n";
    exit(0);
}

$hoy = (new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Y-m-d');

$stmt = Database::pdo()->prepare("
    UPDATE agendas_cobro
    SET estado = 'incumplida', fecha_resolucion = NOW()
    WHERE estado = 'pendiente' AND fecha_agendada < ?
");
$stmt->execute([$hoy]);

echo sprintf(
    "[%s] %d agenda(s) marcada(s) como incumplida.\n",
    date('Y-m-d H:i:s'),
    $stmt->rowCount()
);

flock($lock, LOCK_UN);
fclose($lock);
