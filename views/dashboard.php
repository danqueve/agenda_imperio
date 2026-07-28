<?php
declare(strict_types=1);

$resumen                   = Tickets::estadisticasResumen();
$gestionesHoyDashboard      = Gestiones::gestionesDeHoy();
$tasaContactoDashboard      = Gestiones::tasaContacto(7);
$agendasHoyDashboard        = Agendas::deHoy();
$agendasSemanaDashboard     = Agendas::estaSemana();
$agendasVencidasDashboard   = Agendas::vencidas();
$refinanciacionesPorEstado  = Refinanciaciones::porEstado();
$cobradores                 = Tickets::cobradoresDistintos();

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
    <div x-show="tabActiva === 'resumen'" x-data="{ busqueda: '', zonaFiltro: '', cobradorFiltro: '' }">
        <?php if (!$resumen['sync']['ok']): ?>
            <p class="alerta alerta--error">No se pudo sincronizar con SAS en este momento. Los conteos usan los datos ya guardados.</p>
        <?php endif; ?>

        <div class="kpi-hero">
            <span class="kpi-hero__label">Tasa de contacto (7 días)</span>
            <span class="kpi-hero__valor"><?= $tasaContactoDashboard ?>%</span>
            <span class="kpi-hero__contexto">sobre las gestiones por llamada/visita de los últimos 7 días</span>
        </div>

        <div class="stat-tile-grid stat-tile-grid--secundaria">
            <div class="stat-tile"><span class="stat-tile__valor"><?= $resumen['abiertos'] ?></span><span class="stat-tile__label">Tickets abiertos</span></div>
            <div class="stat-tile"><span class="stat-tile__valor"><?= $resumen['abiertos_hoy'] ?></span><span class="stat-tile__label">Abiertos hoy</span></div>
            <div class="stat-tile"><span class="stat-tile__valor"><?= count($gestionesHoyDashboard) ?></span><span class="stat-tile__label">Gestiones hoy</span></div>
        </div>

        <h3>Cerrados esta semana</h3>
        <div class="stat-tile-grid stat-tile-grid--secundaria">
            <div class="stat-tile"><span class="stat-tile__valor"><?= $resumen['cerrados_semana']['abono'] ?></span><span class="stat-tile__label"><?= icon('check', 'icon--sm') ?> Abonó</span></div>
            <div class="stat-tile"><span class="stat-tile__valor"><?= $resumen['cerrados_semana']['retiro_producto'] ?></span><span class="stat-tile__label"><?= icon('paquete', 'icon--sm') ?> Se retiró producto</span></div>
            <div class="stat-tile"><span class="stat-tile__valor"><?= $resumen['cerrados_semana']['refinanciacion'] ?></span><span class="stat-tile__label"><?= icon('refinanciar', 'icon--sm') ?> Refinanció</span></div>
        </div>

        <h3>Rojos sin gestionar</h3>
        <?php $prefijoFiltro = 'resumen'; require __DIR__ . '/partials/filtro_busqueda.php'; ?>
        <?php if (empty($resumen['rojos_sin_gestionar'])): ?>
            <p class="texto-secundario">No hay tickets rojos sin gestionar.</p>
        <?php else: ?>
            <p class="texto-secundario"
               x-show="busqueda === '' && zonaFiltro === '' && cobradorFiltro === '' && <?= (int) count($resumen['rojos_sin_gestionar']) ?> > 10">
                Mostrando los 10 más urgentes de <?= (int) count($resumen['rojos_sin_gestionar']) ?> — buscá o filtrá para ver el resto.
            </p>
            <div class="tickets-grid">
                <?php foreach ($resumen['rojos_sin_gestionar'] as $idx => $t): ?>
                    <a class="card"
                       href="index.php?p=ficha_ticket&id=<?= $t['ticket_id'] ?>"
                       x-data='<?= json_attr(['idx' => $idx, 't' => [
                           'nombre'   => $t['nombre_completo'],
                           'dni'      => $t['dni'],
                           'zona'     => $t['zona'],
                           'cobrador' => $t['cobrador_nombre'] ?? '',
                       ]]) ?>'
                       x-show="
                          (busqueda !== '' || zonaFiltro !== '' || cobradorFiltro !== '' || idx < 10)
                          && (busqueda === '' || t.nombre.toLowerCase().includes(busqueda.toLowerCase()) || t.dni.includes(busqueda))
                          && (zonaFiltro === '' || t.zona === zonaFiltro)
                          && (cobradorFiltro === '' || t.cobrador === cobradorFiltro)
                       ">
                        <div class="card-ticket__header">
                            <strong><?= e($t['nombre_completo']) ?></strong>
                            <span class="badge badge--rojo"><?= icon('severidad') ?><?= (int) $t['dias_atraso'] ?> días</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ---- Agendas ---- -->
    <div x-show="tabActiva === 'agendas'" x-data="{ subFiltro: 'hoy', busqueda: '', zonaFiltro: '', cobradorFiltro: '' }">
        <?php $prefijoFiltro = 'agendas'; require __DIR__ . '/partials/filtro_busqueda.php'; ?>

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

                            <div class="kanban__card-acciones">
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
                                    <button class="btn btn--primary btn--sm" type="submit"><?= icon('refinanciar', 'icon--sm') ?> Procesada en SAS (cierra el ticket)</button>
                                </form>
                            <?php endif; ?>
                            </div>
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
