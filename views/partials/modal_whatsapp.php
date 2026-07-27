<?php
declare(strict_types=1);

/**
 * Modal de WhatsApp compartido por toda la app (se incluye una sola vez
 * desde footer.php). El estado vive en Alpine.store('whatsapp') porque se
 * dispara desde botones en islas de Alpine distintas (tarjetas de
 * lista_tickets.php, barra de acción de ficha_ticket.php) - ver app.js.
 */
?>
<div class="modal-backdrop"
     x-show="$store.whatsapp.ticketId !== null"
     @click.self="$store.whatsapp.cerrar()"
     style="display: none">
    <div class="modal-panel">
        <h2 class="modal-panel__titulo">Enviar WhatsApp</h2>

        <template x-if="$store.whatsapp.cargando">
            <p class="texto-secundario">Cargando…</p>
        </template>

        <template x-if="$store.whatsapp.error">
            <p class="alerta alerta--error" x-text="$store.whatsapp.error"></p>
        </template>

        <template x-if="!$store.whatsapp.cargando && !$store.whatsapp.error && $store.whatsapp.plantillas.length === 0">
            <p class="texto-secundario">No hay plantillas disponibles para este ticket.</p>
        </template>

        <template x-for="plantilla in $store.whatsapp.plantillas" :key="plantilla.id">
            <button type="button" class="modal-panel__opcion" @click="$store.whatsapp.enviar(plantilla.id, '<?= e(Csrf::token()) ?>')">
                <strong x-text="plantilla.titulo"></strong>
                <span x-text="plantilla.texto"></span>
            </button>
        </template>

        <div class="modal-panel__acciones">
            <button type="button" class="btn btn--secondary btn--block" @click="$store.whatsapp.cerrar()">Cancelar</button>
        </div>
    </div>
</div>
