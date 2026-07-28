<?php
declare(strict_types=1);

/**
 * Recordatorio de agendas de cobro de HOY. Se incluye desde
 * header.php, así que aparece en todas las pantallas de un usuario
 * logueado. Solo toca la base propia (nunca SAS), así que es barato
 * correrlo en cada request. Ver plan §8 y src/Agendas.php.
 */

if (!Agendas::hayAgendasHoySinResolver()) {
    return;
}

$agendasDeHoy = Agendas::deHoy();

// Expansión completa la primera vez de la sesión (no la primera vez del
// día): el flag se resetea a false en Auth::login().
$expandirBannerAhora = empty($_SESSION['banner_expandido_mostrado']);
if ($expandirBannerAhora) {
    $_SESSION['banner_expandido_mostrado'] = true;
}
?>
<div class="banner-recordatorio" x-data="{ expandido: <?= $expandirBannerAhora ? 'true' : 'false' ?> }">
    <button type="button" class="banner-recordatorio__toggle" @click="expandido = !expandido">
        <?= icon('calendario') ?>
        <span class="banner-recordatorio__toggle-texto">AGENDAS DE HOY (<?= count($agendasDeHoy) ?>)</span>
        <span x-show="!expandido"><?= icon('chevron-abajo', 'icon--sm') ?></span>
        <span x-show="expandido"><?= icon('chevron-arriba', 'icon--sm') ?></span>
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
