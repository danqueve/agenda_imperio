<?php
declare(strict_types=1);

/** @var array $ticket cargado por el router (Tickets::porId) */
$ticketId         = $ticket['ticket_id'];
$deuda            = SasApiClient::deuda($ticket['dni']);
$pagos            = SasApiClient::pagos($ticket['dni']);
$gestiones        = Gestiones::timelinePorTicket($ticketId);
$agendas          = Agendas::paraTicket($ticketId);
$refinanciaciones = Refinanciaciones::paraTicket($ticketId);
$estaAbierto      = $ticket['estado'] === 'abierto';
?>
<div x-data="{ tab: 'deuda', modalCerrar: false }" class="<?= $estaAbierto ? 'app-main--con-actionbar' : '' ?>">
    <h1><?= e($ticket['nombre_completo']) ?></h1>
    <p class="texto-secundario">
        DNI <?= e($ticket['dni']) ?> · Cartera de: <?= e($ticket['cobrador_nombre'] ?? '—') ?> · Zona: <?= e(ucfirst($ticket['zona'])) ?>
    </p>

    <?php if (!$estaAbierto): ?>
        <p class="alerta">
            Ticket cerrado el <?= e((new DateTimeImmutable($ticket['fecha_cierre']))->format('d/m/Y H:i')) ?>
            — motivo: <strong><?= e(ucfirst(str_replace('_', ' ', (string) $ticket['motivo_cierre']))) ?></strong>
            <?php if ($ticket['observacion_cierre']): ?><br><?= e($ticket['observacion_cierre']) ?><?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if (($_GET['msg'] ?? '') === 'gestion_ok'): ?>
        <p class="alerta" style="background:var(--color-verde-bg);color:var(--color-verde-text);border:1px solid var(--color-verde-border)">
            Gestión registrada correctamente.
        </p>
    <?php endif; ?>

    <?php if (!empty($_GET['error'])): ?>
        <p class="alerta alerta--error"><?= e($_GET['error']) ?></p>
    <?php endif; ?>

    <div class="tabs__list">
        <button type="button" class="tabs__tab" :class="{ 'is-activo': tab === 'deuda' }" @click="tab = 'deuda'">Deuda</button>
        <button type="button" class="tabs__tab" :class="{ 'is-activo': tab === 'gestiones' }" @click="tab = 'gestiones'">Gestiones (<?= count($gestiones) ?>)</button>
        <button type="button" class="tabs__tab" :class="{ 'is-activo': tab === 'agendas' }" @click="tab = 'agendas'">Agendas (<?= count($agendas) ?>)</button>
    </div>

    <div x-show="tab === 'deuda'">
        <?php if (!$deuda['ok']): ?>
            <p class="alerta alerta--error">No se pudo obtener la deuda en vivo de SAS en este momento.</p>
        <?php else: ?>
            <div class="card" style="margin-bottom: var(--space-4)">
                <p>Deuda total: <strong>$<?= e(number_format((float) $deuda['data']['deuda_total'], 0, ',', '.')) ?></strong></p>
                <p>Cuotas vencidas: <?= (int) $deuda['data']['cuotas_vencidas'] ?></p>
                <p>Días de atraso: <?= (int) $deuda['data']['dias_atraso'] ?></p>
                <?php if (!empty($deuda['data']['proximo_vencimiento'])): ?>
                    <p>Próximo vencimiento: <?= e((new DateTimeImmutable($deuda['data']['proximo_vencimiento']))->format('d/m/Y')) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <h3>Últimos pagos</h3>
        <?php if (!$pagos['ok'] || empty($pagos['data'])): ?>
            <p class="texto-secundario">Sin pagos registrados.</p>
        <?php else: ?>
            <ul class="timeline">
                <?php foreach ($pagos['data'] as $pago): ?>
                    <li class="timeline__item">
                        <div class="timeline__cabecera">
                            <span><?= e((new DateTimeImmutable($pago['fecha_pago']))->format('d/m/Y')) ?></span>
                            <span>$<?= e(number_format((float) $pago['monto_total'], 0, ',', '.')) ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div x-show="tab === 'gestiones'">
        <?php if (empty($gestiones)): ?>
            <p class="texto-secundario">Todavía no hay gestiones registradas.</p>
        <?php else: ?>
            <ul class="timeline">
                <?php foreach ($gestiones as $g): ?>
                    <li class="timeline__item">
                        <div class="timeline__cabecera">
                            <span><?= e(ucfirst($g['canal'])) ?> · <?= e($g['usuario_nombre']) ?></span>
                            <span><?= e((new DateTimeImmutable($g['fecha_hora']))->format('d/m/Y H:i')) ?></span>
                        </div>
                        <p class="timeline__obs">
                            <?= e(ucfirst(str_replace('_', ' ', $g['resultado']))) ?><?= $g['observacion'] ? ' — ' . e($g['observacion']) : '' ?>
                        </p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div x-show="tab === 'agendas'">
        <?php if (empty($agendas)): ?>
            <p class="texto-secundario">Este ticket no tiene agendas de cobro.</p>
        <?php else: ?>
            <ul class="timeline">
                <?php foreach ($agendas as $a): ?>
                    <li class="timeline__item">
                        <div class="timeline__cabecera">
                            <span><?= e(agenda_tipo_label($a['tipo'])) ?></span>
                            <span><?= e((new DateTimeImmutable($a['fecha_agendada']))->format('d/m/Y')) ?></span>
                        </div>
                        <p class="timeline__obs">
                            $<?= e(number_format((float) $a['monto_esperado'], 0, ',', '.')) ?> — <?= e(ucfirst($a['estado'])) ?>
                        </p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (!empty($refinanciaciones)): ?>
            <h3>Refinanciaciones propuestas</h3>
            <ul class="timeline">
                <?php foreach ($refinanciaciones as $r): ?>
                    <li class="timeline__item">
                        <div class="timeline__cabecera">
                            <span><?= e(ucfirst(str_replace('_', ' ', $r['estado']))) ?></span>
                            <span><?= e((new DateTimeImmutable($r['fecha_propuesta']))->format('d/m/Y')) ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <?php if ($estaAbierto): ?>
        <div class="actionbar-fija">
            <a class="btn btn--secondary" href="tel:<?= e($ticket['telefono_principal']) ?>"><?= icon('telefono') ?> Llamar</a>
            <button type="button" class="btn btn--secondary" @click="$store.whatsapp.abrir(<?= $ticketId ?>)"><?= icon('whatsapp') ?> WhatsApp</button>
            <a class="btn btn--secondary" href="index.php?p=registrar_gestion&ticket_id=<?= $ticketId ?>"><?= icon('gestion') ?> Gestión</a>
            <button type="button" class="btn btn--danger" @click="modalCerrar = true"><?= icon('cerrar') ?> Cerrar</button>
        </div>

        <?php require __DIR__ . '/cerrar_ticket.php'; ?>
    <?php endif; ?>
</div>
