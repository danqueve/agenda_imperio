<?php
declare(strict_types=1);

$usuariosAcceso = Database::pdo()
    ->query('SELECT id, nombre, usuario, rol, activo, ultimo_login FROM usuarios_acceso ORDER BY nombre')
    ->fetchAll();
?>
<div x-data="{ mostrarForm: false }">
    <h1>Administrar usuarios</h1>

    <?php if (!empty($_GET['msg'])): ?>
        <p class="alerta" style="background:var(--color-verde-bg);color:var(--color-verde-text);border:1px solid var(--color-verde-border)">
            <?= e($_GET['msg']) ?>
        </p>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
        <p class="alerta alerta--error"><?= e($_GET['error']) ?></p>
    <?php endif; ?>

    <?php foreach ($usuariosAcceso as $u): ?>
        <div class="card" style="margin-bottom: var(--space-3); display: flex; justify-content: space-between; align-items: center; gap: var(--space-3)">
            <div>
                <strong><?= e($u['nombre']) ?></strong> (<?= e($u['usuario']) ?>) — <?= e($u['rol']) ?>
                <br>
                <span class="texto-secundario">
                    <?= $u['activo'] ? 'Activo' : 'Inactivo' ?> ·
                    Último login: <?= $u['ultimo_login'] ? e((new DateTimeImmutable($u['ultimo_login']))->format('d/m/Y H:i')) : 'nunca' ?>
                </span>
            </div>
            <form method="post" action="index.php?p=administrar_usuarios">
                <?= Csrf::campoOculto() ?>
                <input type="hidden" name="accion" value="toggle_activo">
                <input type="hidden" name="usuario_id" value="<?= (int) $u['id'] ?>">
                <button class="btn btn--secondary btn--sm" type="submit"><?= $u['activo'] ? 'Desactivar' : 'Activar' ?></button>
            </form>
        </div>
    <?php endforeach; ?>

    <button type="button" class="btn btn--primary" @click="mostrarForm = !mostrarForm" style="margin-top: var(--space-4)">
        <span x-show="!mostrarForm">+ Nuevo usuario</span>
        <span x-show="mostrarForm">Cancelar</span>
    </button>

    <form method="post" action="index.php?p=administrar_usuarios" x-show="mostrarForm" class="card" style="margin-top: var(--space-4)">
        <?= Csrf::campoOculto() ?>
        <input type="hidden" name="accion" value="crear">

        <div class="form-field">
            <label class="form-label" for="nombre">Nombre</label>
            <input class="form-input" type="text" name="nombre" id="nombre">
        </div>
        <div class="form-field">
            <label class="form-label" for="usuario_nuevo">Usuario</label>
            <input class="form-input" type="text" name="usuario" id="usuario_nuevo">
        </div>
        <div class="form-field">
            <label class="form-label" for="password">Password inicial</label>
            <input class="form-input" type="text" name="password" id="password" minlength="6">
        </div>
        <div class="form-field">
            <label class="form-label" for="rol">Rol</label>
            <select class="form-select" name="rol" id="rol">
                <option value="supervision">Supervisión</option>
                <option value="administrador">Administrador</option>
            </select>
        </div>
        <button class="btn btn--primary btn--block" type="submit">Crear usuario</button>
    </form>
</div>
