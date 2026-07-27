<div class="login-shell">
    <form class="card login-card" method="post" action="index.php?p=login"
          x-data="{ enviando: false, verPassword: false }" @submit="enviando = true">
        <h1 class="login-card__titulo">Agenda de Cobranza</h1>
        <p class="login-card__subtitulo">Imperio Comercial</p>

        <?php if (!empty($errorLogin)): ?>
            <p class="alerta alerta--error"><?= e($errorLogin) ?></p>
        <?php endif; ?>

        <?= Csrf::campoOculto() ?>

        <div class="form-field">
            <label class="form-label" for="usuario">Usuario</label>
            <input class="form-input" type="text" id="usuario" name="usuario"
                   autocomplete="username" required autofocus>
        </div>

        <div class="form-field">
            <label class="form-label" for="password">Contraseña</label>
            <div class="input-con-boton">
                <input class="form-input" :type="verPassword ? 'text' : 'password'"
                       id="password" name="password" autocomplete="current-password" required>
                <button type="button" class="btn btn--ghost btn--sm" @click="verPassword = !verPassword"
                        x-text="verPassword ? 'Ocultar' : 'Ver'"></button>
            </div>
        </div>

        <button class="btn btn--primary btn--block" type="submit" :disabled="enviando">
            <span x-show="!enviando">Ingresar</span>
            <span x-show="enviando">Ingresando…</span>
        </button>
    </form>
</div>
