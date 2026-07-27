<?php
declare(strict_types=1);

$vistaLista = Tickets::listaParaVista();
$tickets    = $vistaLista['tickets'];
?>
<div x-data="{ filtro: 'todos' }">
    <h1>Tickets en gestión</h1>

    <?php if (!empty($_GET['msg']) && $_GET['msg'] === 'ticket_cerrado'): ?>
        <p class="alerta" style="background:var(--color-verde-bg);color:var(--color-verde-text);border:1px solid var(--color-verde-border)">
            Ticket cerrado correctamente.
        </p>
    <?php endif; ?>

    <?php if (!$vistaLista['sync']['ok']): ?>
        <p class="alerta alerta--error">
            No se pudo sincronizar con SAS en este momento (<?= e($vistaLista['sync']['error'] ?? '') ?>).
            Mostrando los tickets ya guardados, sin datos en vivo de deuda.
        </p>
    <?php endif; ?>

    <div class="filter-chips">
        <button type="button" class="filter-chip" :class="{ 'is-activo': filtro === 'todos' }" @click="filtro = 'todos'">Todos</button>
        <button type="button" class="filter-chip" :class="{ 'is-activo': filtro === 'rojo' }" @click="filtro = 'rojo'">Rojo</button>
        <button type="button" class="filter-chip" :class="{ 'is-activo': filtro === 'agenda_hoy' }" @click="filtro = 'agenda_hoy'">Con agenda hoy</button>
        <button type="button" class="filter-chip" :class="{ 'is-activo': filtro === 'sin_gestionar' }" @click="filtro = 'sin_gestionar'">Sin gestionar</button>
    </div>

    <?php if (empty($tickets)): ?>
        <p class="texto-secundario">No hay tickets abiertos en este momento.</p>
    <?php endif; ?>

    <?php foreach ($tickets as $t): ?>
        <?php
        $esRojo         = $t['prioridad'] === 'rojo' ? 'true' : 'false';
        $conAgendaHoy   = $t['tiene_agenda_hoy'] ? 'true' : 'false';
        $sinGestionar   = $t['sin_gestionar'] ? 'true' : 'false';
        ?>
        <div class="card card-ticket"
             x-show="filtro === 'todos'
                || (filtro === 'rojo' && <?= $esRojo ?>)
                || (filtro === 'agenda_hoy' && <?= $conAgendaHoy ?>)
                || (filtro === 'sin_gestionar' && <?= $sinGestionar ?>)">
            <div class="card-ticket__header">
                <strong><?= e($t['nombre_completo']) ?></strong>
                <span class="badge badge--<?= e($t['prioridad']) ?>"><?= (int) $t['dias_atraso'] ?> días</span>
            </div>
            <div class="card-ticket__meta">
                <span>Deuda: <?= $t['deuda_total'] !== null ? '$' . e(number_format($t['deuda_total'], 0, ',', '.')) : 'no disponible ahora' ?></span>
                <span>Cartera de: <?= e($t['cobrador_nombre'] ?? '—') ?></span>
                <span>Última gestión: <?= $t['ultima_gestion'] ? e((new DateTimeImmutable($t['ultima_gestion']))->format('d/m/Y H:i')) : 'Nunca' ?></span>
                <?php if ($t['tiene_agenda_hoy']): ?>
                    <span>📅 Tiene agenda de cobro para hoy</span>
                <?php endif; ?>
            </div>
            <div class="card-ticket__acciones">
                <a class="btn btn--secondary btn--sm" href="index.php?p=ficha_ticket&id=<?= $t['ticket_id'] ?>">Ver ticket</a>
                <button type="button" class="btn btn--secondary btn--sm" @click="$store.whatsapp.abrir(<?= $t['ticket_id'] ?>)">WhatsApp</button>
                <a class="btn btn--primary btn--sm" href="index.php?p=registrar_gestion&ticket_id=<?= $t['ticket_id'] ?>">Gestión</a>
            </div>
        </div>
    <?php endforeach; ?>
</div>
