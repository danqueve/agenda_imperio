# Plan v2 — Agenda de Gestión de Cobranza
## Imperio Comercial (versión con modificaciones)

---

## 1. Cambios respecto al plan anterior

| # | Cambio | Impacto |
|---|--------|---------|
| 1 | Acceso SOLO para perfiles **Administrador** y **Supervisión** | Los cobradores NO tienen login. Son un dato de referencia (a quién pertenece la cartera), no usuarios del sistema |
| 2 | **Sistema de tickets** | Cada cliente en gestión tiene un ticket abierto. Se cierra con motivo: **Abonó** / **Se retiró producto** / **Refinanció** |
| 3 | **Solapa "Agendas" en el Dashboard** | Cuando se agenda un día de cobro, aparece en la solapa para seguimiento. El día que corresponde, el sistema muestra un **recordatorio con las agendas del día** |
| 4 | **Filtro de más de una semana** | Al verificar clientes, solo se muestran los que tienen **más de 7 días de atraso** |

---

## 2. El nuevo modelo: TICKETS

El concepto central ahora es el **ticket de gestión**:

```
Cliente supera 7 días de atraso (dato de SAS)
        │
        ▼
┌─────────────────────┐
│  TICKET ABIERTO      │ ◀── acá viven todas las gestiones:
│                      │     llamadas, WhatsApp, agendas de cobro,
│  Estado: EN GESTIÓN  │     compromisos, propuestas
└─────────┬───────────┘
          │
          ▼  El ticket se CIERRA solo por 3 motivos:
   ┌──────┼──────────────┐
   ▼      ▼              ▼
 ABONÓ  SE RETIRÓ    REFINANCIÓ
        PRODUCTO
   │      │              │
   └──────┴──────────────┘
          ▼
   TICKET CERRADO (con fecha, motivo y quién lo cerró)
```

Reglas del ticket:
- Se abre automáticamente cuando el cliente aparece con **más de 7 días de atraso** en SAS (o manualmente por el admin)
- Un cliente tiene **un solo ticket abierto** a la vez
- Todas las gestiones, agendas y propuestas quedan colgadas del ticket
- Al cerrarse, queda el historial completo para consulta
- Si el cliente vuelve a atrasarse más de una semana en el futuro, se abre un ticket NUEVO (el historial anterior se conserva)

---

## 3. Roles de acceso

| Rol | Puede |
|-----|-------|
| **Administrador** (Alejandro) | Todo: gestionar tickets, cerrar tickets, ver dashboard completo, administrar usuarios, marcar refinanciaciones como procesadas en SAS |
| **Supervisión** | Gestionar tickets (registrar gestiones, agendar cobros, proponer refinanciaciones), cerrar tickets con motivo, ver dashboard y agendas |

Los cobradores (Enzo, Maxi, Santi, Juan Pablo) **no acceden al sistema**. Aparecen solo como dato informativo en cada ticket ("cartera de: Enzo Teceira") para saber a qué cobrador pertenece el cliente en SAS.

---

## 4. Base de datos `c2881399_agenda` (6 tablas)

Nota de nomenclatura: la base se llama `c2881399_agenda` desde el inicio,
usando el prefijo de cPanel del hosting (igual que `c2881399_credit` y
`c2881399_crm`). Así el nombre es idéntico en desarrollo (WAMP) y en
producción (VPS), y el deploy no requiere tocar consultas ni configs
más allá de usuario/password de MySQL.

### usuarios_acceso
- id, nombre, usuario UNIQUE, password_hash
- rol ENUM('administrador','supervision')
- activo, ultimo_login
- Iniciales: Alejandro (administrador) + los usuarios de supervisión que definas

### contactos_agenda
- id, dni UNIQUE, nombre_completo, telefono_principal, telefono_alt
- direccion_referencia, cobrador_nombre (informativo, viene de SAS)
- zona ENUM('tucuman','santiago','catamarca'), activo, fecha_alta

### tickets  ⭐ NUEVA — el eje del sistema
- id, contacto_id FK, fecha_apertura
- estado ENUM('abierto','cerrado') DEFAULT 'abierto'
- motivo_cierre ENUM('abono','retiro_producto','refinanciacion') NULL
- fecha_cierre NULL, cerrado_por FK usuarios_acceso NULL
- observacion_cierre TEXT NULL
- dias_atraso_apertura INT (foto del atraso al abrir)
- UNIQUE parcial: un solo ticket abierto por contacto

### gestiones
- id, ticket_id FK, usuario_id FK (quién registró), fecha_hora
- canal ENUM('llamada','whatsapp','visita','sms')
- resultado ENUM('atendio','no_atendio','ocupado','buzon','fuera_servicio','numero_erroneo','enviado')
- observacion TEXT ("qué dijo el cliente")
- agenda_cobro TINYINT (¿se agendó un día de cobro?)
- fecha_agendada DATE NULL, monto_esperado DECIMAL NULL
- solicita_refinanciar TINYINT

### agendas_cobro  ⭐ NUEVA — reemplaza a compromisos_pago
Cada vez que en una gestión el cliente dice "vengo a pagar el viernes"
o "pasá a cobrarme el 15", se crea una agenda:
- id, ticket_id FK, gestion_id FK, usuario_id FK
- fecha_agendada DATE, monto_esperado DECIMAL(12,2)
- tipo ENUM('cliente_viene','se_visita','transferencia')
- estado ENUM('pendiente','cumplida','incumplida','reprogramada')
- fecha_resolucion NULL, monto_real NULL, observacion TEXT
- Estas agendas alimentan la SOLAPA AGENDAS del dashboard
  y el RECORDATORIO del día

### refinanciaciones
- id, ticket_id FK, gestion_id FK
- propuesta_detalle TEXT, nueva_cadencia, nueva_cant_cuotas, nuevo_monto_cuota
- estado ENUM('borrador','ofrecida','aceptada','rechazada','procesada_en_sas')
- fecha_propuesta, fecha_respuesta, notas
- Cuando pasa a 'procesada_en_sas' → dispara el CIERRE del ticket
  con motivo 'refinanciacion'

---

## 5. Pantallas actualizadas

### P1 — Login
Solo Administrador y Supervisión.

### P2 — Lista de tickets (pantalla principal)
**Muestra ÚNICAMENTE clientes con más de 7 días de atraso** (dato en vivo de SAS).
- Si el cliente tiene >7 días de atraso y no tiene ticket abierto → el sistema
  lo abre automáticamente al cargar la lista
- Cada tarjeta: nombre, días de atraso, deuda, cartera de (cobrador),
  última gestión, semáforo de prioridad
- Filtros: Todos / Rojo / Con agenda hoy / Sin gestionar
- Botones: [Ver ticket] [WhatsApp] [Registrar gestión]

Semáforo:
- 🔴 15+ días de atraso sin gestión en los últimos 5 días
- 🟠 agenda de cobro incumplida, o 10-14 días de atraso
- 🟡 8-9 días de atraso o en seguimiento activo
- 🟢 con agenda de cobro vigente a futuro

### P3 — Ficha del ticket
- Cabecera: cliente, días de atraso EN VIVO (SAS), deuda, estado del ticket
- Tab "Deuda" (SAS en vivo): detalle de cuotas, últimos pagos, producto
- Tab "Gestiones": timeline completo del ticket
- Tab "Agendas": agendas de cobro de este ticket y su estado
- Barra inferior: [Llamar] [WhatsApp] [Registrar gestión] [**Cerrar ticket**]

### P4 — Registrar gestión
Igual que antes (¿Atendió? → SÍ/NO → detalle) pero con el agregado:
- Toggle "**Se agendó día de cobro**" → fecha + monto + tipo
  (cliente viene / se lo visita / hace transferencia)
- Eso crea la agenda que aparecerá en el dashboard

### P5 — Cerrar ticket (nuevo, crítico)
Modal con 3 botones grandes:
- ✅ **ABONÓ** → opcional: monto abonado + observación
- 📦 **SE RETIRÓ PRODUCTO** → observación (qué producto, estado)
- 🔄 **REFINANCIÓ** → enlaza con la refinanciación aceptada
  (si no existe una aceptada, avisa y permite crearla primero)
Al confirmar: estado=cerrado, fecha, motivo, usuario que cerró.
El ticket desaparece de la lista activa y queda en el archivo consultable.

### P6 — Dashboard con SOLAPAS (Administrador y Supervisión)

**Solapa 1 — Resumen**
- Tickets abiertos totales, abiertos hoy, cerrados esta semana (por motivo)
- Gestiones de hoy, tasa de contacto
- Top 10 tickets rojos sin gestionar

**Solapa 2 — AGENDAS** ⭐ nueva
- **Recordatorio del día**: si hoy hay agendas, banner destacado arriba:
  "📅 Hoy tenés 4 agendas de cobro: Juan García $15.000 (viene al local),
  María López $8.000 (visita)..."
  → Este banner también aparece al hacer LOGIN si hay agendas hoy
- Lista de agendas: Hoy / Esta semana / Vencidas sin resolver
- Acción rápida en cada una: [Cumplió] [No cumplió] [Reprogramar]
- Si cumplió y saldó la deuda → ofrece cerrar el ticket con motivo "abonó"

**Solapa 3 — Refinanciaciones**
- Kanban: Borrador → Ofrecida → Aceptada → Procesada en SAS
- Al marcar "Procesada en SAS" (solo admin) → cierra el ticket automáticamente

**Solapa 4 — Historial de tickets cerrados**
- Buscador por cliente/DNI, filtro por motivo de cierre y fechas
- Sirve para: "¿este cliente ya tuvo tickets antes? ¿cómo se resolvieron?"

---

## 6. Flujo del recordatorio de agendas

```
Usuario hace login (admin o supervisión)
        │
        ▼
¿Hay agendas_cobro con fecha_agendada = HOY y estado = pendiente?
        │
   SÍ ──┴── NO → dashboard normal
   │
   ▼
Banner destacado (no bloqueante) arriba del dashboard:
"📅 AGENDAS DE HOY (4): [lista con nombre, monto, tipo]"
+ badge con número en la solapa Agendas
+ el banner persiste en todas las pantallas hasta que
  todas las agendas del día estén resueltas
```

---

## 7. WhatsApp (sin cambios — Opción A manual)

Botón wa.me con las 3 plantillas + registro automático de la gestión.
Se agrega una 4ta plantilla:
4. **Recordatorio de agenda**: "Hola {nombre}, te recordamos que hoy
   quedamos en que abonabas ${monto}. ¿Confirmás que podés acercarte?"

---

## 8. Fases actualizadas

**FASE 1 — Fundación (semana 1-2)**
- [ ] SQL de las 6 tablas (con tickets y agendas_cobro)
- [ ] Usuario MySQL readonly + api_readonly.php en SAS
      (endpoint mora ahora filtra: días de atraso > 7)
- [ ] Login con roles administrador/supervision
- [ ] Apertura automática de tickets para clientes >7 días

**FASE 2 — Core (semana 2-4)**
- [ ] Lista de tickets con semáforo y filtros
- [ ] Ficha del ticket con 3 tabs
- [ ] Registrar gestión con agenda de cobro
- [ ] Cerrar ticket con los 3 motivos
- [ ] Botón WhatsApp con 4 plantillas

**FASE 3 — Dashboard y seguimiento (semana 4-5)**
- [ ] Dashboard con las 4 solapas
- [ ] Recordatorio de agendas del día (login + banner persistente)
- [ ] Kanban refinanciaciones con cierre automático de ticket
- [ ] Historial de tickets cerrados con buscador
- [ ] Cron: marca agendas vencidas como incumplidas

**FASE 4 — Producción (semana 5-6)**
- [ ] Deploy VPS + SSL + cron en cPanel
- [ ] Prueba con casos reales una semana
- [ ] Ajustes

---

## 9. Criterios de éxito v2

- Solo entran administrador y supervisión (verificable)
- Ningún cliente con menos de 8 días de atraso aparece en la lista
- Todo ticket cerrado tiene motivo, fecha y responsable
- Al entrar un día con agendas, el recordatorio aparece SIEMPRE
- Una agenda de cobro nunca queda sin resolver (el cron la marca incumplida)
- Cero escrituras en la base de SAS
