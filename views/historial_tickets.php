<?php
declare(strict_types=1);

/** Parcial incluido por dashboard.php en la solapa "Historial". */

$busqueda      = trim((string) ($_GET['q'] ?? ''));
$motivoFiltro  = (string) ($_GET['motivo'] ?? '');
$desde         = (string) ($_GET['desde'] ?? '');
$hasta         = (string) ($_GET['hasta'] ?? '');
$zonaFiltro    = (string) ($_GET['zona'] ?? '');
$cobradorFiltro = (string) ($_GET['cobrador'] ?? '');
$cobradores    = Tickets::cobradoresDistintos();
$zonas         = Tickets::zonasDistintas();

$condiciones = ["t.estado = 'cerrado'"];
$params      = [];

if ($busqueda !== '') {
    $condiciones[] = '(c.nombre_completo LIKE ? OR c.dni LIKE ?)';
    $params[]      = '%' . $busqueda . '%';
    $params[]      = '%' . $busqueda . '%';
}
if (in_array($motivoFiltro, ['abono', 'retiro_producto', 'refinanciacion'], true)) {
    $condiciones[] = 't.motivo_cierre = ?';
    $params[]      = $motivoFiltro;
}
if ($desde !== '') {
    $condiciones[] = 'DATE(t.fecha_cierre) >= ?';
    $params[]      = $desde;
}
if ($hasta !== '') {
    $condiciones[] = 'DATE(t.fecha_cierre) <= ?';
    $params[]      = $hasta;
}
if ($zonaFiltro !== '') {
    $condiciones[] = 'c.zona = ?';
    $params[]      = $zonaFiltro;
}
if ($cobradorFiltro !== '') {
    $condiciones[] = 'c.cobrador_nombre = ?';
    $params[]      = $cobradorFiltro;
}

$stmt = Database::pdo()->prepare('
    SELECT t.id AS ticket_id, t.fecha_cierre, t.motivo_cierre, t.monto_cierre, c.nombre_completo, c.dni, c.zona, c.cobrador_nombre
    FROM tickets t
    JOIN contactos_agenda c ON c.id = t.contacto_id
    WHERE ' . implode(' AND ', $condiciones) . '
    ORDER BY t.fecha_cierre DESC
    LIMIT 100
');
$stmt->execute($params);
$historial = $stmt->fetchAll();
?>
<form method="get" action="index.php" class="card" style="margin-bottom: var(--space-4)">
    <input type="hidden" name="p" value="dashboard">
    <input type="hidden" name="tab" value="historial">

    <div class="form-fila">
        <div class="form-field">
            <label class="form-label" for="q">Buscar por nombre o DNI</label>
            <input class="form-input" type="text" name="q" id="q" value="<?= e($busqueda) ?>">
        </div>

        <div class="form-field">
            <label class="form-label" for="motivo">Motivo</label>
            <select class="form-select" name="motivo" id="motivo">
                <option value="">Todos</option>
                <option value="abono" <?= $motivoFiltro === 'abono' ? 'selected' : '' ?>>Abonó</option>
                <option value="retiro_producto" <?= $motivoFiltro === 'retiro_producto' ? 'selected' : '' ?>>Se retiró producto</option>
                <option value="refinanciacion" <?= $motivoFiltro === 'refinanciacion' ? 'selected' : '' ?>>Refinanció</option>
            </select>
        </div>

        <div class="form-field">
            <label class="form-label" for="desde">Desde</label>
            <input class="form-input" type="date" name="desde" id="desde" value="<?= e($desde) ?>">
        </div>

        <div class="form-field">
            <label class="form-label" for="hasta">Hasta</label>
            <input class="form-input" type="date" name="hasta" id="hasta" value="<?= e($hasta) ?>">
        </div>

        <div class="form-field">
            <label class="form-label" for="zona">Zona</label>
            <select class="form-select" name="zona" id="zona">
                <option value="">Todas las zonas</option>
                <?php foreach ($zonas as $z): ?>
                    <option value="<?= e($z) ?>" <?= $zonaFiltro === $z ? 'selected' : '' ?>><?= e($z) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-field">
            <label class="form-label" for="cobrador">Cobrador</label>
            <select class="form-select" name="cobrador" id="cobrador">
                <option value="">Todos los cobradores</option>
                <?php foreach ($cobradores as $c): ?>
                    <option value="<?= e($c) ?>" <?= $cobradorFiltro === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <button class="btn btn--primary btn--block" type="submit"><?= icon('buscar', 'icon--sm') ?> Buscar</button>
</form>

<?php if (empty($historial)): ?>
    <p class="texto-secundario">No hay tickets cerrados que coincidan con la búsqueda.</p>
<?php else: ?>
    <div class="tickets-grid">
        <?php foreach ($historial as $h): ?>
            <a class="card" href="index.php?p=ficha_ticket&id=<?= (int) $h['ticket_id'] ?>">
                <strong><?= e($h['nombre_completo']) ?></strong> (DNI <?= e($h['dni']) ?>)
                <br>
                <span class="texto-secundario">
                    Cerrado el <?= e((new DateTimeImmutable($h['fecha_cierre']))->format('d/m/Y')) ?>
                    — <?= e(ucfirst(str_replace('_', ' ', $h['motivo_cierre']))) ?>
                    <?php if ($h['monto_cierre'] !== null): ?>
                        — $<?= e(number_format((float) $h['monto_cierre'], 0, ',', '.')) ?>
                    <?php endif; ?>
                    <br>
                    Zona: <?= e($h['zona']) ?> · Cartera: <?= e($h['cobrador_nombre'] ?? '—') ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
