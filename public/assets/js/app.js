// Agenda de Cobranza - Imperio Comercial
// JS propio, sin build step. La mayoría de la interactividad (tabs,
// toggles, revelado progresivo) se maneja con Alpine.js directamente en
// el HTML de cada vista. Este store es la excepción: el modal de
// WhatsApp se dispara desde botones en distintas islas de Alpine
// (tarjetas de la lista, barra de acción de la ficha), así que necesita
// estado compartido en vez de vivir dentro de un solo x-data.

document.addEventListener('alpine:init', () => {
    Alpine.store('whatsapp', {
        ticketId: null,
        cargando: false,
        telefono: null,
        plantillas: [],
        error: null,

        async abrir(ticketId) {
            this.ticketId = ticketId;
            this.cargando = true;
            this.error = null;
            this.plantillas = [];

            try {
                const resp = await fetch(`index.php?p=whatsapp_preview&ticket_id=${ticketId}`);
                const data = await resp.json();
                if (data.ok) {
                    this.telefono = data.telefono;
                    this.plantillas = data.plantillas;
                } else {
                    this.error = data.error || 'No se pudo cargar WhatsApp para este ticket.';
                }
            } catch (e) {
                this.error = 'No se pudo conectar con el servidor.';
            }

            this.cargando = false;
        },

        cerrar() {
            this.ticketId = null;
        },

        async enviar(plantillaId, csrfToken) {
            const body = new URLSearchParams({
                ticket_id: this.ticketId,
                plantilla_id: plantillaId,
                csrf_token: csrfToken,
            });

            try {
                const resp = await fetch('index.php?p=whatsapp_enviar', { method: 'POST', body });
                const data = await resp.json();
                if (data.ok) {
                    window.open(data.link, '_blank');
                    this.cerrar();
                } else {
                    this.error = data.error || 'No se pudo registrar el envío.';
                }
            } catch (e) {
                this.error = 'No se pudo conectar con el servidor.';
            }
        },
    });
});
