<?php
declare(strict_types=1);

/**
 * Modal de cierre de ticket (P5). Se incluye desde ficha_ticket.php,
 * dentro del x-data que ya define `modalCerrar`. Ambos roles pueden
 * cerrar por cualquiera de los 3 motivos, incluyendo REFINANCIÓ - el
 * servidor exige que ya exista una refinanciación aceptada (ver
 * Tickets::cerrar()); "Procesada en SAS" (solo admin, Fase 3) es un
 * camino aparte que cierra el ticket automáticamente.
 */
?>
<div class="modal-backdrop" x-show="modalCerrar" @click.self="modalCerrar = false" style="display: none"
     x-data="{ motivo: null, enviando: false }">
    <div class="modal-panel">
        <h2 class="modal-panel__titulo">Cerrar ticket</h2>

        <template x-if="!motivo">
            <div>
                <button type="button" class="btn btn--primary btn--block" style="margin-bottom: var(--space-3)" @click="motivo = 'abono'"><?= icon('check') ?> Abonó</button>
                <button type="button" class="btn btn--primary btn--block" style="margin-bottom: var(--space-3)" @click="motivo = 'retiro_producto'"><?= icon('paquete') ?> Se retiró producto</button>
                <button type="button" class="btn btn--primary btn--block" style="margin-bottom: var(--space-3)" @click="motivo = 'refinanciacion'"><?= icon('refinanciar') ?> Refinanció</button>
                <button type="button" class="btn btn--secondary btn--block" @click="modalCerrar = false">Cancelar</button>
            </div>
        </template>

        <template x-if="motivo">
            <form method="post" action="index.php?p=cerrar_ticket">
                <?= Csrf::campoOculto() ?>
                <input type="hidden" name="ticket_id" value="<?= (int) $ticketId ?>">
                <input type="hidden" name="motivo" :value="motivo">

                <template x-if="motivo === 'abono'">
                    <div class="form-field">
                        <label class="form-label" for="monto_cierre">Monto abonado (opcional)</label>
                        <input class="form-input" type="number" step="0.01" min="0" name="monto_cierre" id="monto_cierre">
                    </div>
                </template>

                <p class="texto-secundario" x-show="motivo === 'refinanciacion'">
                    Se va a cerrar como REFINANCIÓ. Esto requiere que ya exista una refinanciación aceptada para este ticket.
                </p>

                <div class="form-field">
                    <label class="form-label" for="observacion_cierre">Observación</label>
                    <textarea class="form-textarea" name="observacion_cierre" id="observacion_cierre" rows="3"></textarea>
                </div>

                <div class="modal-panel__acciones">
                    <button type="button" class="btn btn--secondary" @click="motivo = null">Volver</button>
                    <button class="btn btn--primary" type="submit" :disabled="enviando" @click="enviando = true">Confirmar cierre</button>
                </div>
            </form>
        </template>
    </div>
</div>
