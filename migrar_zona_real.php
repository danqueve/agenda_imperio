<?php
declare(strict_types=1);

/**
 * Backfill puntual: reemplaza la zona vieja (bucket tucuman/santiago/
 * catamarca) por la zona real de SAS en TODOS los contactos ya
 * sincronizados, incluidos los de tickets ya cerrados (esos no se
 * autocorrigen solos con la sincronización de rutina, que solo toca
 * contactos actualmente "atrasados").
 *
 * Correr UNA sola vez, a mano, después de desplegar el cambio de
 * columna (sql/04_zona_texto_libre.sql) y de recopiar el
 * api_readonly.php actualizado al hosting de SAS:
 *   php migrar_zona_real.php
 *
 * Bloqueado por URL igual que cron_apertura_tickets.php (ver .htaccess).
 */

date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/SasApiClient.php';

$pdo       = Database::pdo();
$contactos = $pdo->query('SELECT id, dni FROM contactos_agenda')->fetchAll();

$actualizados = 0;
$sinDatos     = 0;

$stmtUpdate = $pdo->prepare('UPDATE contactos_agenda SET zona = ? WHERE id = ?');

foreach ($contactos as $contacto) {
    $resp = SasApiClient::cliente($contacto['dni']);
    $zona = $resp['ok'] ? ($resp['data']['cliente']['zona'] ?? '') : '';

    if ($zona === '') {
        $sinDatos++;
        continue;
    }

    $stmtUpdate->execute([$zona, $contacto['id']]);
    $actualizados++;
}

echo sprintf(
    "Actualizados: %d, sin datos en SAS: %d, total: %d\n",
    $actualizados,
    $sinDatos,
    count($contactos)
);
