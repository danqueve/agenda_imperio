<?php
declare(strict_types=1);

/** @var array $ticket cargado por el router (Tickets::porId) */
$ticketId = $ticket['ticket_id'];
?>
<div x-data="{ canal: 'llamada', atendio: null, agendaCobro: false, quiereRefinanciar: false, enviando: false }">
    <h1>Registrar gestión</h1>
    <p class="texto-secundario"><?= e($ticket['nombre_completo']) ?> — <?= e($ticket['telefono_principal'] ?? 'sin teléfono') ?></p>

    <?php if (!empty($_GET['error'])): ?>
        <p class="alerta alerta--error"><?= e($_GET['error']) ?></p>
    <?php endif; ?>

    <form method="post" action="index.php?p=registrar_gestion&ticket_id=<?= $ticketId ?>" @submit="enviando = true">
        <?= Csrf::campoOculto() ?>

        <div class="form-field">
            <label class="form-label">Canal</label>
            <div class="filter-chips">
                <button type="button" class="filter-chip" :class="{ 'is-activo': canal === 'llamada' }" @click="canal = 'llamada'">Llamada</button>
                <button type="button" class="filter-chip" :class="{ 'is-activo': canal === 'visita' }" @click="canal = 'visita'">Visita</button>
                <button type="button" class="filter-chip" :class="{ 'is-activo': canal === 'sms' }" @click="canal = 'sms'">SMS</button>
            </div>
            <input type="hidden" name="canal" :value="canal">
        </div>

        <div class="form-field">
            <label class="form-label">¿Atendió?</label>
            <div class="filter-chips">
                <button type="button" class="filter-chip" :class="{ 'is-activo': atendio === true }" @click="atendio = true">Sí</button>
                <button type="button" class="filter-chip" :class="{ 'is-activo': atendio === false }" @click="atendio = false">No</button>
            </div>
        </div>

        <template x-if="atendio === false">
            <div class="form-field">
                <label class="form-label" for="resultado_no">Motivo</label>
                <select class="form-select" name="resultado" id="resultado_no">
                    <option value="no_atendio">No atendió</option>
                    <option value="ocupado">Ocupado</option>
                    <option value="buzon">Buzón de voz</option>
                    <option value="fuera_servicio">Fuera de servicio</option>
                    <option value="numero_erroneo">Número erróneo</option>
                </select>
            </div>
        </template>

        <template x-if="atendio === true">
            <div>
                <input type="hidden" name="resultado" value="atendio">

                <div class="form-field">
                    <label class="form-label" for="observacion">¿Qué dijo?</label>
                    <textarea class="form-textarea" name="observacion" id="observacion" rows="3"></textarea>
                </div>

                <label class="form-toggle">
                    <input class="form-toggle__input" type="checkbox" name="agenda_cobro" value="1" x-model="agendaCobro">
                    <span class="form-toggle__switch"></span>
                    Se agendó día de cobro
                </label>

                <template x-if="agendaCobro">
                    <div style="margin-top: var(--space-3)">
                        <div class="form-field">
                            <label class="form-label" for="fecha_agendada">Fecha</label>
                            <input class="form-input" type="date" name="fecha_agendada" id="fecha_agendada" :required="agendaCobro">
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="monto_esperado">Monto esperado</label>
                            <input class="form-input" type="number" step="0.01" min="0" name="monto_esperado" id="monto_esperado" :required="agendaCobro">
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="tipo_agenda">Tipo</label>
                            <select class="form-select" name="tipo_agenda" id="tipo_agenda">
                                <option value="cliente_viene">Cliente viene al local</option>
                                <option value="se_visita">Se lo visita</option>
                                <option value="transferencia">Transferencia</option>
                            </select>
                        </div>
                    </div>
                </template>

                <label class="form-toggle" style="margin-top: var(--space-3)">
                    <input class="form-toggle__input" type="checkbox" name="solicita_refinanciar" value="1" x-model="quiereRefinanciar">
                    <span class="form-toggle__switch"></span>
                    Quiere refinanciar
                </label>
            </div>
        </template>

        <div class="form-field" style="margin-top: var(--space-5)">
            <button class="btn btn--primary btn--block" type="submit" :disabled="atendio === null || enviando">
                <span x-show="!enviando">Guardar gestión</span>
                <span x-show="enviando">Guardando…</span>
            </button>
            <a class="btn btn--ghost btn--block" href="index.php?p=ficha_ticket&id=<?= $ticketId ?>" style="margin-top: var(--space-2)">Cancelar</a>
        </div>
    </form>
</div>
