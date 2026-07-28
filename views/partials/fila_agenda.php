<?php
declare(strict_types=1);

/**
 * Fila reutilizable para las 3 sub-listas de la solapa Agendas
 * (Hoy/Semana/Vencidas). Espera $a (fila de Agendas::deHoy()/estaSemana()/
 * vencidas(), con nombre_completo/telefono_principal ya joineados).
 */
$hoyStr    = (new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Y-m-d');
$esVencida = $a['estado'] === 'incumplida' || ($a['estado'] === 'pendiente' && $a['fecha_agendada'] < $hoyStr);
?>
<div class="card list-agenda-item<?= $esVencida ? ' list-agenda-item--vencida' : '' ?>" style="margin-bottom: var(--space-3)"
     x-data='<?= json_attr([
         'reprogramando' => false,
         't' => [
             'nombre'   => $a['nombre_completo'],
             'dni'      => $a['dni'],
             'zona'     => $a['zona'],
             'cobrador' => $a['cobrador_nombre'] ?? '',
         ],
     ]) ?>'
     x-show="
        (busqueda === '' || t.nombre.toLowerCase().includes(busqueda.toLowerCase()) || t.dni.includes(busqueda))
        && (zonaFiltro === '' || t.zona === zonaFiltro)
        && (cobradorFiltro === '' || t.cobrador === cobradorFiltro)
     ">
    <div class="card-ticket__header">
        <strong><a href="index.php?p=ficha_ticket&id=<?= (int) $a['ticket_id'] ?>" style="color: inherit"><?= e($a['nombre_completo']) ?></a></strong>
        <span>$<?= e(number_format((float) $a['monto_esperado'], 0, ',', '.')) ?></span>
    </div>
    <p class="texto-secundario">
        <?= e(agenda_tipo_label($a['tipo'])) ?> · <?= e((new DateTimeImmutable($a['fecha_agendada']))->format('d/m/Y')) ?>
        <?php if ($a['estado'] === 'incumplida'): ?> · <strong>Incumplida</strong><?php endif; ?>
    </p>
    <p class="texto-secundario">Zona: <?= e($a['zona']) ?> · Cartera: <?= e($a['cobrador_nombre'] ?? '—') ?></p>

    <form method="post" action="index.php?p=agendas_accion" style="display: inline-block; margin-right: var(--space-2)">
        <?= Csrf::campoOculto() ?>
        <input type="hidden" name="agenda_id" value="<?= (int) $a['id'] ?>">
        <input type="hidden" name="accion" value="cumplida">
        <label class="texto-secundario" style="display: block; margin-bottom: var(--space-2)">
            <input type="checkbox" name="saldo_deuda" value="1"> ¿Saldó la deuda? (cierra el ticket por ABONÓ)
        </label>
        <input type="number" step="0.01" min="0" name="monto_real" placeholder="Monto real cobrado"
               class="form-input" style="margin-bottom: var(--space-2); max-width: 220px">
        <button class="btn btn--primary btn--sm" type="submit">Cumplió</button>
    </form>

    <form method="post" action="index.php?p=agendas_accion" style="display: inline-block; margin-right: var(--space-2)">
        <?= Csrf::campoOculto() ?>
        <input type="hidden" name="agenda_id" value="<?= (int) $a['id'] ?>">
        <input type="hidden" name="accion" value="incumplida">
        <button class="btn btn--secondary btn--sm" type="submit">No cumplió</button>
    </form>

    <button type="button" class="btn btn--secondary btn--sm" @click="reprogramando = !reprogramando">Reprogramar</button>

    <form method="post" action="index.php?p=agendas_accion" x-show="reprogramando" style="margin-top: var(--space-3)">
        <?= Csrf::campoOculto() ?>
        <input type="hidden" name="agenda_id" value="<?= (int) $a['id'] ?>">
        <input type="hidden" name="accion" value="reprogramar">
        <input type="date" name="nueva_fecha" class="form-input" style="margin-bottom: var(--space-2); max-width: 220px" required>
        <button class="btn btn--primary btn--sm" type="submit">Confirmar nueva fecha</button>
    </form>
</div>
