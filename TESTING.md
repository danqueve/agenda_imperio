# Plan de prueba manual - primera semana

Casos para validar en producción (o en un WAMP local con datos reales de
SAS) antes de confiar el sistema al uso diario. Están basados en los
mismos flujos que se probaron durante el desarrollo, así que cada uno
tiene un resultado esperado concreto - si algo no coincide, es una señal
real de que algo se rompió en el camino al deploy.

---

## Día 1 - Acceso y apertura automática

- [ ] **Login correcto**: entrar con `alejandro` (administrador) y con un
      usuario `supervision` real. Cada uno debe ver el mismo dashboard y
      lista de tickets (no hay vistas exclusivas de un rol en la
      operación diaria, salvo lo del punto siguiente).
- [ ] **Login incorrecto**: usuario o password mal escritos → mensaje de
      error, sin dar pistas de cuál de los dos está mal.
- [ ] **Bloqueo por intentos fallidos**: 5 intentos seguidos con password
      incorrecta → el usuario queda bloqueado ~15 minutos, incluso
      probando la password correcta en el intento 6.
- [ ] **Apertura automática de tickets**: confirmar que `lista_tickets.php`
      solo muestra clientes con **más de 7 días** de atraso (ninguno con
      7 o menos), y que un cliente que hoy no tenía ticket y superó los 7
      días aparece con uno nuevo la primera vez que se carga la lista (o
      tras la corrida del cron de apertura).
- [ ] **No duplica tickets**: recargar `lista_tickets.php` varias veces
      seguidas no crea tickets repetidos para el mismo cliente (mirar la
      cantidad total de tickets abiertos antes/después).
- [ ] **Acceso denegado sin rol correcto**: con un usuario `supervision`,
      entrar a "Usuarios" en el menú - no debería aparecer el link, y
      pegar la URL directamente (`?p=administrar_usuarios`) debe dar un
      error de acceso, no la pantalla.

## Día 2 - Gestiones y WhatsApp

- [ ] Abrir un ticket, registrar una gestión con "Atendió: No" y un
      motivo - confirmar que aparece en la tab "Gestiones" de la ficha.
- [ ] Registrar una gestión con "Atendió: Sí" + "Se agendó día de cobro"
      → confirmar que la agenda aparece en la tab "Agendas" de esa
      misma ficha, con la fecha y el monto correctos.
- [ ] Botón WhatsApp desde la lista y desde la ficha de un ticket: probar
      al menos 2 plantillas distintas, confirmar que el mensaje
      prellenado tiene el nombre y los datos correctos, y que al volver
      a esa ficha la gestión quedó registrada (canal WhatsApp).
- [ ] Botón "Llamar" abre el marcador del teléfono (en un celular real,
      no en desktop) con el número correcto.

## Día 3 - Recordatorio de agendas

- [ ] Agendar un cobro para **hoy** en un ticket cualquiera.
- [ ] Cerrar sesión y volver a entrar → debe aparecer el banner
      "AGENDAS DE HOY" expandido automáticamente, con nombre, monto y
      tipo correctos.
- [ ] Navegar a otra pantalla (ficha de otro ticket, dashboard) → el
      banner sigue visible pero colapsado (sin el detalle expandido).
- [ ] Resolver esa agenda (Cumplió o No cumplió) desde el dashboard →
      el banner desaparece si era la última agenda de hoy sin resolver.
- [ ] Volver a cerrar sesión y entrar de nuevo con una agenda de hoy
      todavía pendiente → el banner se expande de nuevo (es "primera vez
      de la sesión", no "primera vez del día").

## Día 4 - Cierre de tickets (los 3 motivos)

- [ ] **ABONÓ**: cerrar un ticket con este motivo, con y sin monto
      cargado. Confirmar que desaparece de `lista_tickets.php` y que
      aparece en el Historial del dashboard con la fecha y el monto.
- [ ] **SE RETIRÓ PRODUCTO**: igual, con una observación describiendo el
      estado del producto.
- [ ] **REFINANCIÓ sin refinanciación aceptada**: intentar cerrar por
      este motivo en un ticket que no tiene ninguna refinanciación en
      estado "aceptada" → debe avisar y no cerrar el ticket.
- [ ] **REFINANCIÓ con refinanciación aceptada**: crear una propuesta
      (toggle "Quiere refinanciar" al registrar una gestión), moverla en
      el kanban del dashboard hasta "Aceptada", y recién ahí cerrar el
      ticket por REFINANCIÓ desde la ficha - debe funcionar para
      **ambos roles** (administrador y supervisión).
- [ ] **Procesada en SAS**: con esa misma refinanciación aceptada, un
      usuario `supervision` intenta marcarla "Procesada en SAS" en el
      kanban → debe ser rechazado (solo administrador). Alejandro sí
      puede, y al hacerlo el ticket se cierra solo, con motivo
      REFINANCIÓ.
- [ ] **Reincidencia**: verificar que un contacto con ticket ya cerrado
      puede recibir un ticket **nuevo** más adelante si vuelve a superar
      los 7 días de atraso (no queda bloqueado para siempre).

## Día 5 - Cron diario y casos borde

- [ ] Agendar un cobro para una fecha **pasada** (o esperar a que una
      agenda de hoy quede sin resolver) y correr `cron_diario.php` (por
      cPanel o manualmente) → la agenda debe pasar a estado
      "Incumplida", y aparecer en la sub-pestaña "Vencidas" del
      dashboard.
- [ ] Reprogramar una agenda vencida a una fecha futura → debe
      desaparecer de "Vencidas" y aparecer en "Esta semana" o "Hoy"
      según corresponda.
- [ ] Cerrar un ticket que tenía una agenda futura todavía pendiente →
      confirmar que esa agenda **deja de aparecer** en el dashboard (no
      debería quedar un pendiente fantasma de un ticket ya cerrado).
- [ ] Con SAS momentáneamente inaccesible (o una URL mal puesta a
      propósito en `config/sas_api.php` para probar): confirmar que
      `lista_tickets.php` y la ficha del ticket muestran un aviso de
      "no se pudo sincronizar/obtener deuda" en vez de romperse.

## Checklist rápido de seguridad (una sola vez, no por día)

- [ ] Pegar `https://.../config/database.php` (o `/sql/`, `/src/`,
      `/views/`) en el navegador → no debe cargar (403 o Document Root
      ya lo hace inalcanzable).
- [ ] Un usuario sin sesión iniciada, pegando cualquier URL interna
      (`?p=dashboard`, `?p=ficha_ticket&id=1`) → redirige al login.
- [ ] Cambiaste las passwords "cambiar123" de los usuarios sembrados.
