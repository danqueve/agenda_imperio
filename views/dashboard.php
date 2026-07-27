<?php
declare(strict_types=1);

$resumen                   = Tickets::estadisticasResumen();
$gestionesHoyDashboard      = Gestiones::gestionesDeHoy();
$tasaContactoDashboard      = Gestiones::tasaContacto(7);
$agendasHoyDashboard        = Agendas::deHoy();
$agendasSemanaDashboard     = Agendas::estaSemana();
$agendasVencidasDashboard   = Agendas::vencidas();
$refinanciacionesPorEstado  = Refinanciaciones::porEstado();

$tabInicial = in_array($_GET['tab'] ?? '', ['resumen', 'agendas', 'refinanciaciones', 'historial'], true)
    ? $_GET['tab']
    : 'resumen';
?>
<div x-data="{ tabActiva: '<?= e($tabInicial) ?>' }">
    <h1>Dashboard</h1>

    <div class="tabs__list">
        <button type="button" class="tabs__tab" :class="{ 'is-activo': tabActiva === 'resumen' }" @click="tabActiva = 'resumen'">Resumen</button>
        <button type="button" class="tabs__tab" :class="{ 'is-activo': tabActiva === 'agendas' }" @click="tabActiva = 'agendas'">Agendas (<?= count($agendasHoyDashboard) ?>)</button>
        <button type="button" class="tabs__tab" :class="{ 'is-activo': tabActiva === 'refinanciaciones' }" @click="tabActiva = 'refinanciaciones'">Refinanciaciones</button>
        <button type="button" class="tabs__tab" :class="{ 'is-activo': tabActiva === 'historial' }" @click="tabActiva = 'historial'">Historial</button>
    </div>

    <!-- ---- Resumen ---- -->
    <div x-show="tabActiva === 'resumen'">
        <?php if (!$resumen['sync']['ok']): ?>
            <p class="alerta alerta--error">No se pudo sincronizar con SAS en este momento. Los conteos usan los datos ya guardados.</p>
        <?php endif; ?>

        <div class="stat-tile-grid">
            <div class="stat-tile"><span class="stat-tile__valor"><?= $resumen['abiertos'] ?></span><span class="stat-tile__label">Tickets abiertos</span></div>
            <div class="stat-tile"><span class="stat-tile__valor"><?= $resumen['abiertos_hoy'] ?></span><span class="stat-tile__label">Abiertos hoy</span></div>
            <div class="stat-tile"><span class="stat-tile__valor"><?= count($gestionesHoyDashboard) ?></span><span class="stat-tile__label">Gestiones hoy</span></div>
            <div class="stat-tile"><span class="stat-tile__valor"><?= $tasaContactoDashboard ?>%</span><span class="stat-tile__label">Tasa de contacto (7 días)</span></div>
        </div>

        <h3>Cerrados esta semana</h3>
        <div class="stat-tile-grid">
            <div class="stat-tile"><span class="stat-tile__valor"><?= $resumen['cerrados_semana']['abono'] ?></span><span class="stat-tile__label">✅ Abonó</span></div>
            <div class="stat-tile"><span class="stat-tile__valor"><?= $resumen['cerrados_semana']['retiro_producto'] ?></span><span class="stat-tile__label">📦 Se retiró producto</span></div>
            <div class="stat-tile"><span class="stat-tile__valor"><?= $resumen['cerrados_semana']['refinanciacion'] ?></span><span class="stat-tile__label">🔄 Refinanció</span></div>
        </div>

        <h3>Top 10 rojos sin gestionar</h3>
        <?php if (empty($resumen['top10_rojos'])): ?>
            <p class="texto-secundario">No hay tickets rojos sin gestionar.</p>
        <?php else: ?>
            <?php foreach ($resumen['top10_rojos'] as $t): ?>
                <a class="card" style="display: block; margin-bottom: var(--space-3); text-decoration: none; color: inherit"
                   href="index.php?p=ficha_ticket&id=<?= $t['ticket_id'] ?>">
                    <div class="card-ticket__header">
                        <strong><?= e($t['nombre_completo']) ?></strong>
                        <span class="badge badge--rojo"><?= (int) $t['dias_atraso'] ?> días</span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ---- Agendas ---- -->
    <div x-show="tabActiva === 'agendas'" x-data="{ subFiltro: 'hoy' }">
        <div class="filter-chips">
            <button type="button" class="filter-chip" :class="{ 'is-activo': subFiltro === 'hoy' }" @click="subFiltro = 'hoy'">Hoy (<?= count($agendasHoyDashboard) ?>)</button>
            <button type="button" class="filter-chip" :class="{ 'is-activo': subFiltro === 'semana' }" @click="subFiltro = 'semana'">Esta semana (<?= count($agendasSemanaDashboard) ?>)</button>
            <button type="button" class="filter-chip" :class="{ 'is-activo': subFiltro === 'vencidas' }" @click="subFiltro = 'vencidas'">Vencidas (<?= count($agendasVencidasDashboard) ?>)</button>
        </div>

        <div x-show="subFiltro === 'hoy'">
            <?php if (empty($agendasHoyDashboard)): ?>
                <p class="texto-secundario">No hay agendas de cobro para hoy.</p>
            <?php endif; ?>
            <?php foreach ($agendasHoyDashboard as $a): ?>
                <?php require __DIR__ . '/partials/fila_agenda.php'; ?>
            <?php endforeach; ?>
        </div>

        <div x-show="subFiltro === 'semana'">
            <?php if (empty($agendasSemanaDashboard)): ?>
                <p class="texto-secundario">No hay agendas de cobro para esta semana.</p>
            <?php endif; ?>
            <?php foreach ($agendasSemanaDashboard as $a): ?>
                <?php require __DIR__ . '/partials/fila_agenda.php'; ?>
            <?php endforeach; ?>
        </div>

        <div x-show="subFiltro === 'vencidas'">
            <?php if (empty($agendasVencidasDashboard)): ?>
                <p class="texto-secundario">No hay agendas vencidas sin resolver.</p>
            <?php endif; ?>
            <?php foreach ($agendasVencidasDashboard as $a): ?>
                <?php require __DIR__ . '/partials/fila_agenda.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ---- Refinanciaciones (kanban) ---- -->
    <div x-show="tabActiva === 'refinanciaciones'" x-data="{ columnaActiva: 'borrador' }">
        <div class="filter-chips">
            <button type="button" class="filter-chip" :class="{ 'is-activo': columnaActiva === 'borrador' }" @click="columnaActiva = 'borrador'">Borrador (<?= count($refinanciacionesPorEstado['borrador']) ?>)</button>
            <button type="button" class="filter-chip" :class="{ 'is-activo': columnaActiva === 'ofrecida' }" @click="columnaActiva = 'ofrecida'">Ofrecida (<?= count($refinanciacionesPorEstado['ofrecida']) ?>)</button>
            <button type="button" class="filter-chip" :class="{ 'is-activo': columnaActiva === 'aceptada' }" @click="columnaActiva = 'aceptada'">Aceptada (<?= count($refinanciacionesPorEstado['aceptada']) ?>)</button>
            <button type="button" class="filter-chip" :class="{ 'is-activo': columnaActiva === 'procesada_en_sas' }" @click="columnaActiva = 'procesada_en_sas'">Procesada en SAS (<?= count($refinanciacionesPorEstado['procesada_en_sas']) ?>)</button>
        </div>

        <div class="kanban">
            <?php foreach (['borrador' => 'Borrador', 'ofrecida' => 'Ofrecida', 'aceptada' => 'Aceptada', 'procesada_en_sas' => 'Procesada en SAS'] as $estadoKey => $estadoLabel): ?>
                <div class="kanban__column" :class="{ 'is-activa': columnaActiva === '<?= $estadoKey ?>' }">
                    <h3 class="kanban__column-titulo"><?= e($estadoLabel) ?></h3>

                    <?php if (empty($refinanciacionesPorEstado[$estadoKey])): ?>
                        <p class="texto-secundario">Sin propuestas en este estado.</p>
                    <?php endif; ?>

                    <?php foreach ($refinanciacionesPorEstado[$estadoKey] as $r): ?>
                        <div class="kanban__card">
                            <strong><a href="index.php?p=ficha_ticket&id=<?= (int) $r['ticket_id'] ?>" style="color: inherit"><?= e($r['nombre_completo']) ?></a></strong>
                            <p class="texto-secundario"><?= e((new DateTimeImmutable($r['fecha_propuesta']))->format('d/m/Y')) ?></p>

                            <?php if ($estadoKey === 'borrador'): ?>
                                <form method="post" action="index.php?p=refinanciaciones_accion">
                                    <?= Csrf::campoOculto() ?>
                                    <input type="hidden" name="refinanciacion_id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="accion" value="ofrecida">
                                    <button class="btn btn--secondary btn--sm" type="submit">Marcar ofrecida</button>
                                </form>
                            <?php elseif ($estadoKey === 'ofrecida'): ?>
                                <form method="post" action="index.php?p=refinanciaciones_accion">
                                    <?= Csrf::campoOculto() ?>
                                    <input type="hidden" name="refinanciacion_id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="accion" value="aceptada">
                                    <button class="btn btn--secondary btn--sm" type="submit">Aceptada</button>
                                </form>
                                <form method="post" action="index.php?p=refinanciaciones_accion">
                                    <?= Csrf::campoOculto() ?>
                                    <input type="hidden" name="refinanciacion_id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="accion" value="rechazada">
                                    <button class="btn btn--danger btn--sm" type="submit">Rechazar</button>
                                </form>
                            <?php elseif ($estadoKey === 'aceptada' && Auth::esAdmin()): ?>
                                <form method="post" action="index.php?p=refinanciaciones_accion">
                                    <?= Csrf::campoOculto() ?>
                                    <input type="hidden" name="refinanciacion_id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="accion" value="procesada_en_sas">
                                    <button class="btn btn--primary btn--sm" type="submit">Procesada en SAS (cierra el ticket)</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ---- Historial ---- -->
    <div x-show="tabActiva === 'historial'">
        <?php require __DIR__ . '/historial_tickets.php'; ?>
    </div>
</div>
