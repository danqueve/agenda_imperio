<?php
declare(strict_types=1);

/**
 * Bloque compartido de buscador + filtro de zona + filtro de cobrador,
 * usado por lista_tickets.php y por las solapas Resumen/Agendas del
 * dashboard. Se apoya en Alpine (x-model="busqueda"/"zonaFiltro"/
 * "cobradorFiltro"), que el caller debe declarar en su x-data padre.
 *
 * El caller DEBE setear estas variables antes de hacer el `require`:
 *   $prefijoFiltro : string única por instancia en la misma página
 *                    (ej. 'lista', 'resumen', 'agendas') - evita ids
 *                    duplicados cuando dashboard.php incluye este
 *                    partial más de una vez (Resumen y Agendas conviven
 *                    en el DOM, Alpine solo las oculta con x-show).
 *   $cobradores    : array de nombres (Tickets::cobradoresDistintos()).
 */
?>
<div class="form-fila" style="margin-bottom: var(--space-4)">
    <div class="form-field">
        <label class="form-label" for="busqueda_<?= e($prefijoFiltro) ?>">Buscar por nombre o DNI</label>
        <input class="form-input" type="text" id="busqueda_<?= e($prefijoFiltro) ?>" x-model="busqueda">
    </div>
    <div class="form-field">
        <label class="form-label" for="zona_<?= e($prefijoFiltro) ?>">Zona</label>
        <select class="form-select" id="zona_<?= e($prefijoFiltro) ?>" x-model="zonaFiltro">
            <option value="">Todas las zonas</option>
            <option value="tucuman">Tucumán</option>
            <option value="santiago">Santiago</option>
            <option value="catamarca">Catamarca</option>
        </select>
    </div>
    <div class="form-field">
        <label class="form-label" for="cobrador_<?= e($prefijoFiltro) ?>">Cobrador</label>
        <select class="form-select" id="cobrador_<?= e($prefijoFiltro) ?>" x-model="cobradorFiltro">
            <option value="">Todos los cobradores</option>
            <?php foreach ($cobradores as $c): ?>
                <option value="<?= e($c) ?>"><?= e($c) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
