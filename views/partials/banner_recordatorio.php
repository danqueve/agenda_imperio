<?php
declare(strict_types=1);

/**
 * Recordatorio de agendas de cobro de HOY. Se incluye desde
 * header.php, así que aparece en todas las pantallas de un usuario
 * logueado. Solo toca la base propia (nunca SAS), así que es barato
 * correrlo en cada request. Ver plan §8.
 *
 * La fecha "hoy" se calcula en PHP con zona horaria explícita y se
 * pasa bindeada — nunca CURDATE() de MySQL (el motor puede tener
 * otra zona horaria configurada, sobre todo en hosting compartido).
 */

$hoyRecordatorio = (new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Y-m-d');

$stmtAgendasHoy = Database::pdo()->prepare("
    SELECT a.id, a.monto_esperado, a.tipo, c.nombre_completo
    FROM agendas_cobro a
    JOIN tickets t ON t.id = a.ticket_id
    JOIN contactos_agenda c ON c.id = t.contacto_id
    WHERE a.estado = 'pendiente' AND a.fecha_agendada = ?
    ORDER BY a.fecha_agendada
");
$stmtAgendasHoy->execute([$hoyRecordatorio]);
$agendasDeHoy = $stmtAgendasHoy->fetchAll();

// Expansión completa la primera vez de la sesión (no la primera vez del día):
// el flag se resetea a false en Auth::login().
$expandirBannerAhora = !empty($agendasDeHoy) && empty($_SESSION['banner_expandido_mostrado']);
if ($expandirBannerAhora) {
    $_SESSION['banner_expandido_mostrado'] = true;
}
?>
<?php if (!empty($agendasDeHoy)): ?>
<div class="banner-recordatorio" x-data="{ expandido: <?= $expandirBannerAhora ? 'true' : 'false' ?> }">
    <button type="button" class="banner-recordatorio__toggle" @click="expandido = !expandido">
        📅 AGENDAS DE HOY (<?= count($agendasDeHoy) ?>)
    </button>
    <ul class="banner-recordatorio__lista" x-show="expandido">
        <?php foreach ($agendasDeHoy as $agenda): ?>
        <li>
            <strong><?= e($agenda['nombre_completo']) ?></strong>
            — $<?= e(number_format((float) $agenda['monto_esperado'], 0, ',', '.')) ?>
            (<?= e(agenda_tipo_label($agenda['tipo'])) ?>)
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
