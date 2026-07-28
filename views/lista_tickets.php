<?php
declare(strict_types=1);

$vistaLista = Tickets::listaParaVista();
$tickets    = $vistaLista['tickets'];
$cobradores = Tickets::cobradoresDistintos();
?>
<div x-data="{ filtro: 'todos', busqueda: '', zonaFiltro: '', cobradorFiltro: '' }">
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

    <?php $prefijoFiltro = 'lista'; require __DIR__ . '/partials/filtro_busqueda.php'; ?>

    <div class="filter-chips">
        <button type="button" class="filter-chip" :class="{ 'is-activo': filtro === 'todos' }" @click="filtro = 'todos'">Todos</button>
        <button type="button" class="filter-chip" :class="{ 'is-activo': filtro === 'rojo' }" @click="filtro = 'rojo'">Rojo</button>
        <button type="button" class="filter-chip" :class="{ 'is-activo': filtro === 'agenda_hoy' }" @click="filtro = 'agenda_hoy'">Con agenda hoy</button>
        <button type="button" class="filter-chip" :class="{ 'is-activo': filtro === 'sin_gestionar' }" @click="filtro = 'sin_gestionar'">Sin gestionar</button>
    </div>

    <?php if (empty($tickets)): ?>
        <p class="texto-secundario">No hay tickets abiertos en este momento.</p>
    <?php endif; ?>

    <div class="tickets-grid">
        <?php foreach ($tickets as $t): ?>
            <div class="card card-ticket"
                 x-data='<?= json_attr(['t' => [
                     'nombre'         => $t['nombre_completo'],
                     'dni'            => $t['dni'],
                     'zona'           => $t['zona'],
                     'cobrador'       => $t['cobrador_nombre'] ?? '',
                     'prioridad'      => $t['prioridad'],
                     'tieneAgendaHoy' => $t['tiene_agenda_hoy'],
                     'sinGestionar'   => $t['sin_gestionar'],
                 ]]) ?>'
                 x-show="
                    (filtro === 'todos' || (filtro === 'rojo' && t.prioridad === 'rojo') || (filtro === 'agenda_hoy' && t.tieneAgendaHoy) || (filtro === 'sin_gestionar' && t.sinGestionar))
                    && (busqueda === '' || t.nombre.toLowerCase().includes(busqueda.toLowerCase()) || t.dni.includes(busqueda))
                    && (zonaFiltro === '' || t.zona === zonaFiltro)
                    && (cobradorFiltro === '' || t.cobrador === cobradorFiltro)
                 ">
                <div class="card-ticket__header">
                    <strong><?= e($t['nombre_completo']) ?></strong>
                    <span class="badge badge--<?= e($t['prioridad']) ?>"><?= icon('severidad') ?><?= (int) $t['dias_atraso'] ?> días</span>
                </div>
                <div class="card-ticket__meta">
                    <span>Deuda: <?= $t['deuda_total'] !== null ? '$' . e(number_format($t['deuda_total'], 0, ',', '.')) : 'no disponible ahora' ?></span>
                    <span>Cartera de: <?= e($t['cobrador_nombre'] ?? '—') ?></span>
                    <span>Zona: <?= e(ucfirst($t['zona'])) ?></span>
                    <span>Última gestión: <?= $t['ultima_gestion'] ? e((new DateTimeImmutable($t['ultima_gestion']))->format('d/m/Y H:i')) : 'Nunca' ?></span>
                    <?php if ($t['tiene_agenda_hoy']): ?>
                        <span class="con-icono"><?= icon('calendario', 'icon--sm') ?> Tiene agenda de cobro para hoy</span>
                    <?php endif; ?>
                </div>
                <div class="card-ticket__acciones">
                    <a class="btn btn--secondary btn--sm" href="index.php?p=ficha_ticket&id=<?= $t['ticket_id'] ?>">Ver ticket</a>
                    <button type="button" class="btn btn--secondary btn--sm" @click="$store.whatsapp.abrir(<?= $t['ticket_id'] ?>)"><?= icon('whatsapp', 'icon--sm') ?> WhatsApp</button>
                    <a class="btn btn--primary btn--sm" href="index.php?p=registrar_gestion&ticket_id=<?= $t['ticket_id'] ?>">Gestión</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
