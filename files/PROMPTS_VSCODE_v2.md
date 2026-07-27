# Prompts para VS Code v2 (Claude Code / Copilot / Cursor)
## Agenda de Cobranza — Imperio Comercial (modelo de TICKETS)

Cómo usarlos:
1. Abrir carpeta vacía en VS Code (ej: C:\wamp64\www\agenda)
2. Pegar el PROMPT MAESTRO completo → esperar confirmación
3. Pegar los prompts de fase EN ORDEN, probando cada fase antes de seguir

---

## ═══════════════════════════════════════
## PROMPT MAESTRO v2 (pegar primero, completo)
## ═══════════════════════════════════════

Actuá como desarrollador senior PHP full-stack. Vamos a construir desde cero
una aplicación web llamada "Agenda de Cobranza" para Imperio Comercial, una
empresa de créditos y ventas en cuotas de Tucumán, Argentina.

CONTEXTO DEL NEGOCIO:
- La empresa tiene un sistema de créditos llamado SAS Imperio (PHP/MySQL,
  base `c2881399_credit`) que registra clientes, créditos, cuotas, pagos y
  cobradores. Ese sistema NO SE TOCA JAMÁS.
- La Agenda de Cobranza es un sistema NUEVO e INDEPENDIENTE para gestionar
  el contacto con clientes atrasados mediante TICKETS de gestión.

MODELO DE TICKETS (concepto central):
- Solo entran al sistema los clientes con MÁS DE 7 DÍAS de atraso
  (el dato de atraso viene en vivo de SAS).
- Cuando un cliente supera los 7 días de atraso y no tiene ticket abierto,
  el sistema le ABRE UN TICKET automáticamente.
- Un cliente tiene UN SOLO ticket abierto a la vez.
- Todas las gestiones (llamadas, WhatsApp, visitas), agendas de cobro y
  propuestas de refinanciación cuelgan del ticket.
- El ticket se CIERRA únicamente por 3 motivos:
  1. ABONÓ (el cliente pagó)
  2. SE RETIRÓ PRODUCTO (se recuperó la mercadería)
  3. REFINANCIÓ (se reestructuró el crédito)
- Al cerrar se registra: motivo, fecha, usuario que cerró, observación.
- Los tickets cerrados quedan en un historial consultable. Si el cliente
  vuelve a atrasarse en el futuro, se abre un ticket NUEVO.

USUARIOS Y ROLES (importante):
- SOLO acceden dos perfiles: ADMINISTRADOR y SUPERVISION.
- Administrador (Alejandro): todo, incluye administrar usuarios y marcar
  refinanciaciones como procesadas en SAS.
- Supervisión: gestionar tickets, registrar gestiones, agendar cobros,
  cerrar tickets, ver dashboard y agendas.
- Los cobradores (Enzo Teceira, Maxi Sanchez, Santi Zalazar, Juan Pablo
  Bicego) NO tienen acceso al sistema. Solo aparecen como dato informativo
  en el ticket ("cartera de: X") indicando a qué cobrador pertenece el
  cliente en SAS. Gonzalo Carrazan no debe aparecer nunca.

AGENDAS DE COBRO Y RECORDATORIOS (funcionalidad clave):
- Al registrar una gestión se puede "agendar un día de cobro": fecha,
  monto esperado y tipo (cliente_viene / se_visita / transferencia).
- El Dashboard tiene una SOLAPA "AGENDAS" para seguimiento: Hoy / Esta
  semana / Vencidas, con acciones [Cumplió] [No cumplió] [Reprogramar].
- RECORDATORIO: si al hacer login hay agendas con fecha de HOY pendientes,
  aparece un banner destacado "AGENDAS DE HOY (n)" con la lista (nombre,
  monto, tipo). El banner persiste en todas las pantallas (y un badge con
  el número en la solapa Agendas) hasta que todas las agendas del día
  estén resueltas.
- Si una agenda se marca "Cumplió" y el usuario lo indica, se ofrece
  cerrar el ticket con motivo ABONÓ en el mismo flujo.

ARQUITECTURA (regla de oro — NUNCA violarla):
- Base propia: `c2881399_agenda` (MySQL). Acá se escribe TODO.
- Los datos financieros (días de atraso, deuda, cuotas, pagos) se consultan
  EN VIVO a SAS vía una API PHP de solo lectura (api_readonly.php) con un
  usuario MySQL que solo tiene GRANT SELECT. La agenda JAMÁS escribe en
  `c2881399_credit` ni copia datos financieros a su base.
- WhatsApp: integración MANUAL vía enlaces wa.me con mensaje prellenado.
  NADA de APIs de WhatsApp. Al hacer clic se registra la gestión vía
  fetch() y se abre wa.me en _blank.

STACK OBLIGATORIO:
- PHP 8.x sin frameworks (MVC liviano propio, router simple por ?p=)
- MySQL con PDO y consultas preparadas SIEMPRE
- Alpine.js (CDN) para tabs, toggles y modales
- CSS propio mobile-first, sin Bootstrap/Tailwind. Acentos #1e3a5f,
  semáforos rojo/naranja/amarillo/verde, botones táctiles min-height 48px
- Sesiones PHP nativas, password_hash/password_verify
- Desarrollo: WAMP64 en Windows. Producción futura: VPS Linux con cPanel.

ESTRUCTURA DE CARPETAS:
agenda/
├── config/database.php , config/sas_api.php
├── public/index.php , public/assets/css/app.css , public/assets/js/app.js
├── src/Auth.php , src/SasApiClient.php , src/Tickets.php ,
│   src/Gestiones.php , src/Agendas.php , src/Refinanciaciones.php ,
│   src/Prioridad.php
├── views/ (login, lista_tickets, ficha_ticket, registrar_gestion,
│   cerrar_ticket, dashboard, historial_tickets)
├── sql/01_schema.sql , 02_usuarios.sql , 03_sas_readonly.sql
└── sas_side/api_readonly.php

BASE DE DATOS `c2881399_agenda` (6 tablas):

1. usuarios_acceso: id PK, nombre VARCHAR(80), usuario VARCHAR(40) UNIQUE,
   password_hash VARCHAR(255), rol ENUM('administrador','supervision'),
   activo TINYINT DEFAULT 1, ultimo_login DATETIME NULL
   → Insertar: Alejandro (administrador) y un usuario "supervision1"
   (supervision). Password inicial "cambiar123" hasheado.

2. contactos_agenda: id PK, dni VARCHAR(10) UNIQUE NOT NULL,
   nombre_completo VARCHAR(120), telefono_principal VARCHAR(20),
   telefono_alt VARCHAR(20), direccion_referencia VARCHAR(200),
   cobrador_nombre VARCHAR(80) (informativo, viene de SAS),
   zona ENUM('tucuman','santiago','catamarca'), activo TINYINT DEFAULT 1,
   fecha_alta DATETIME

3. tickets: id PK, contacto_id FK, fecha_apertura DATETIME,
   estado ENUM('abierto','cerrado') DEFAULT 'abierto',
   motivo_cierre ENUM('abono','retiro_producto','refinanciacion') NULL,
   fecha_cierre DATETIME NULL, cerrado_por INT FK usuarios_acceso NULL,
   observacion_cierre TEXT NULL, dias_atraso_apertura INT,
   monto_cierre DECIMAL(12,2) NULL
   → Índice único que garantice UN SOLO ticket abierto por contacto
   (truco MySQL: columna generada abierto_flag = IF(estado='abierto',
   contacto_id, NULL) con UNIQUE, o validación en código + índice).

4. gestiones: id PK, ticket_id FK, usuario_id FK, fecha_hora DATETIME,
   canal ENUM('llamada','whatsapp','visita','sms'),
   resultado ENUM('atendio','no_atendio','ocupado','buzon',
   'fuera_servicio','numero_erroneo','enviado'),
   observacion TEXT, agenda_cobro TINYINT DEFAULT 0,
   solicita_refinanciar TINYINT DEFAULT 0

5. agendas_cobro: id PK, ticket_id FK, gestion_id FK NULL, usuario_id FK,
   fecha_agendada DATE, monto_esperado DECIMAL(12,2),
   tipo ENUM('cliente_viene','se_visita','transferencia'),
   estado ENUM('pendiente','cumplida','incumplida','reprogramada')
   DEFAULT 'pendiente', fecha_resolucion DATETIME NULL,
   monto_real DECIMAL(12,2) NULL, observacion TEXT

6. refinanciaciones: id PK, ticket_id FK, gestion_id FK NULL,
   propuesta_detalle TEXT, nueva_cadencia ENUM('semanal','quincenal','mensual'),
   nueva_cant_cuotas INT, nuevo_monto_cuota DECIMAL(12,2),
   estado ENUM('borrador','ofrecida','aceptada','rechazada','procesada_en_sas')
   DEFAULT 'borrador', fecha_propuesta DATETIME, fecha_respuesta DATETIME NULL,
   notas TEXT
   → Al pasar a 'procesada_en_sas', cerrar el ticket con motivo
   'refinanciacion' en la misma transacción.

API DE SAS (sas_side/api_readonly.php):
- Header Authorization: Bearer {TOKEN} (constante)
- PDO con usuario `agenda_readonly` (solo SELECT sobre c2881399_credit)
- Endpoints por ?accion=:
  - accion=atrasados → clientes con MÁS DE 7 DÍAS de atraso, con:
    dni, nombre, teléfono, cobrador, días de atraso, cuotas vencidas,
    deuda total. ESTE es el endpoint que alimenta la apertura de tickets.
  - accion=cliente&dni= → datos del cliente y créditos activos
  - accion=deuda&dni= → deuda total, cuotas vencidas, días de atraso,
    próximo vencimiento
  - accion=pagos&dni= → últimos 10 pagos
- JSON {ok, data, error}. Como no conozco los nombres exactos de las
  tablas de c2881399_credit, usá nombres razonables (clientes, creditos,
  cuotas, pagos, cobradores) y marcá con // AJUSTAR donde haya que
  adaptar. El cálculo de "días de atraso" = días desde la cuota vencida
  más antigua impaga.

LÓGICA DE PRIORIDAD (src/Prioridad.php):
- ROJO: 15+ días de atraso sin gestión en los últimos 5 días
- NARANJA: agenda de cobro incumplida, o 10-14 días de atraso
- AMARILLO: 8-9 días de atraso o con gestión reciente (seguimiento)
- VERDE: con agenda de cobro pendiente a futuro

APERTURA AUTOMÁTICA DE TICKETS (src/Tickets.php):
- Método sincronizarDesdeSAS(): llama a accion=atrasados, y por cada
  cliente con >7 días de atraso: si no existe en contactos_agenda lo crea,
  y si no tiene ticket abierto se lo abre (guardando dias_atraso_apertura).
- Se ejecuta al cargar la lista de tickets (con caché de 10 minutos en
  sesión para no llamar a SAS en cada refresh).

BOTÓN WHATSAPP:
- Enlace https://wa.me/549{telefono}?text={urlencode(mensaje)}
- 4 plantillas con variables {nombre},{cuotas},{fecha},{monto}:
  1. Recordatorio: "Hola {nombre}, te contactamos de Imperio Comercial.
     Registramos {cuotas} cuota(s) pendiente(s). ¿Podemos coordinar el pago?"
  2. Agenda vencida: "Hola {nombre}, quedamos en que abonabas el {fecha}.
     ¿Pudiste realizar el pago?"
  3. Refinanciación: "Hola {nombre}, tenemos una propuesta para que te
     pongas al día con cuotas más cómodas. ¿Te interesa?"
  4. Recordatorio de agenda de HOY: "Hola {nombre}, te recordamos que hoy
     quedamos en que abonabas ${monto}. ¿Confirmás?"
- Al clic: fetch() registra gestión (canal=whatsapp, resultado=enviado)
  y abre wa.me en _blank.

SEGURIDAD:
- PDO preparadas 100%, htmlspecialchars en salida, CSRF en POST,
  regeneración de ID de sesión al login
- Middleware de rol: TODAS las rutas requieren sesión con rol
  administrador o supervision; acciones exclusivas de admin verificadas
  server-side (procesar refinanciación en SAS, administrar usuarios)

Por ahora NO generes código: confirmá que entendiste el modelo de tickets,
los roles y el flujo de agendas con recordatorio, mostrame la estructura
que vas a crear, y esperá mi primer prompt de fase.

---

## ═══════════════════════════════════════
## PROMPT FASE 1 — Fundación
## ═══════════════════════════════════════

FASE 1: Generá estos archivos completos y funcionales:

1. sql/01_schema.sql — CREATE DATABASE c2881399_agenda + las 6 tablas
   (usuarios_acceso, contactos_agenda, tickets, gestiones, agendas_cobro,
   refinanciaciones) con FK, índices (dni, ticket_id, fecha_agendada,
   estado) y el mecanismo de UN SOLO ticket abierto por contacto. utf8mb4.
2. sql/02_usuarios.sql — INSERT de Alejandro (administrador) y
   supervision1 (supervision), passwords hasheados de "cambiar123".
   Comentario con el one-liner PHP para regenerar hashes.
3. sql/03_sas_readonly.sql — CREATE USER 'agenda_readonly'@'localhost'
   + GRANT SELECT ON c2881399_credit.* + FLUSH PRIVILEGES.
4. config/database.php — singleton PDO hacia c2881399_agenda (constantes
   para WAMP: localhost/root/sin pass; fáciles de cambiar en producción).
5. config/sas_api.php — SAS_API_URL y SAS_API_TOKEN.
6. sas_side/api_readonly.php — API completa con los 4 endpoints,
   incluyendo accion=atrasados con el filtro de MÁS DE 7 DÍAS y el
   cálculo de días de atraso. Marcar // AJUSTAR donde corresponda.
7. src/Auth.php — login(), logout(), check(), rol(), esAdmin(),
   requiereLogin(), requiereAdmin().
8. src/Tickets.php — sincronizarDesdeSAS() (apertura automática),
   abiertos(), porId(), cerrar($id,$motivo,$obs,$monto,$usuarioId),
   historial($filtros).
9. public/index.php — router por ?p= con whitelist, sesión obligatoria.
10. views/login.php + public/assets/css/app.css — mobile-first, botones
    48px, clases del semáforo, estilos de banner de recordatorio
    (.banner-agendas destacado).

Al final: comandos para importar los SQL en WAMP y cómo probar el login.

---

## ═══════════════════════════════════════
## PROMPT FASE 2 — Tickets, gestiones y cierre
## ═══════════════════════════════════════

FASE 2: Con la fundación andando, generá:

1. src/SasApiClient.php — cURL hacia api_readonly con métodos
   atrasados(), cliente($dni), deuda($dni), pagos($dni). Timeout 8s;
   si SAS no responde, devolver ['ok'=>false] y que las vistas muestren
   los datos propios sin romperse.
2. src/Prioridad.php — calcular($diasAtraso,$ultimaGestion,
   $agendaIncumplida,$agendaFutura) → rojo|naranja|amarillo|verde según
   el prompt maestro.
3. src/Gestiones.php — registrar() (y si agenda_cobro=1 crear también la
   agenda en agendas_cobro en la misma transacción),
   timelinePorTicket($ticketId), gestionesDeHoy().
4. views/lista_tickets.php — pantalla principal:
   - Ejecuta Tickets::sincronizarDesdeSAS() (con caché 10 min)
   - SOLO clientes con >7 días de atraso, ordenados por prioridad
   - Tarjetas: nombre, días de atraso, deuda, cartera de (cobrador),
     última gestión, semáforo
   - Filtros: Todos / Rojo / Con agenda hoy / Sin gestionar
   - Botones [Ver ticket] [WhatsApp] [Gestión]
5. views/ficha_ticket.php — tabs Alpine (Deuda en vivo desde SAS /
   Gestiones timeline / Agendas del ticket) + barra fija inferior:
   [Llamar tel:] [WhatsApp] [Registrar gestión] [Cerrar ticket]
6. views/registrar_gestion.php — flujo ¿Atendió? SÍ/NO:
   - NO → chips motivo → guardar
   - SÍ → textarea "¿Qué dijo?" + toggle "Se agendó día de cobro"
     (fecha + monto + tipo cliente_viene/se_visita/transferencia)
     + toggle "Quiere refinanciar" (crea borrador)
7. views/cerrar_ticket.php (o modal en la ficha) — 3 botones grandes:
   ABONÓ (monto opcional + obs) / SE RETIRÓ PRODUCTO (obs) /
   REFINANCIÓ (exige refinanciación en estado aceptada o
   procesada_en_sas; si no hay, avisar y llevar a crearla).
   Confirmación previa. Al cerrar → volver a la lista con mensaje.
8. Endpoint JSON + modal WhatsApp con las 4 plantillas (preview con
   variables reemplazadas) que registra la gestión y abre wa.me.

Probar el ciclo de vida completo: sincronizar → ticket abierto →
gestionar → agendar cobro → cerrar por abonó.

---

## ═══════════════════════════════════════
## PROMPT FASE 3 — Dashboard, solapa Agendas y recordatorios
## ═══════════════════════════════════════

FASE 3: Generá:

1. src/Agendas.php — deHoy(), pendientes(), vencidas(), estaSemana(),
   resolver($id,$estado,$montoReal,$obs), reprogramar($id,$nuevaFecha),
   hayAgendasHoySinResolver() (para el recordatorio global).
2. RECORDATORIO GLOBAL: en el layout común, si
   hayAgendasHoySinResolver() → banner .banner-agendas visible en TODAS
   las pantallas: "📅 AGENDAS DE HOY (n)" con enlace a la solapa Agendas.
   Al hacer login, si hay agendas hoy, mostrar el detalle expandido
   (nombre, monto, tipo) la primera vez en esa sesión.
3. views/dashboard.php con 4 SOLAPAS (Alpine.js):
   - Resumen: tickets abiertos, abiertos hoy, cerrados esta semana por
     motivo (abonó/retiró/refinanció), gestiones hoy, tasa de contacto
     7 días, top 10 rojos sin gestionar
   - AGENDAS: sub-filtros Hoy/Esta semana/Vencidas; cada fila con
     [Cumplió] [No cumplió] [Reprogramar]; al marcar Cumplió preguntar
     "¿saldó la deuda?" → si sí, flujo de cierre de ticket motivo abonó
   - Refinanciaciones: kanban Borrador→Ofrecida→Aceptada→Procesada en SAS
     (mover con botones; "Procesada" solo admin y cierra el ticket)
   - Historial: tickets cerrados con buscador por nombre/DNI, filtro por
     motivo y rango de fechas; ver detalle con timeline completo
4. cron_diario.php (CLI o URL con token): agendas 'pendiente' con
   fecha_agendada < hoy → 'incumplida'; log de lo que hizo.

---

## ═══════════════════════════════════════
## PROMPT FASE 4 — Seguridad y deploy
## ═══════════════════════════════════════

FASE 4: Preparar producción:

1. Auditoría del código con checklist: PDO preparadas, escape de salida,
   CSRF en POST, middleware de rol en TODAS las rutas (y acciones de
   admin verificadas server-side), headers de sesión seguros.
2. config/*.prod.php.example con placeholders para cPanel: la base ya
   se llama c2881399_agenda (mismo nombre en local y producción), solo
   cambian usuario/password MySQL y la URL/token reales de api_readonly.
3. Guía de deploy en VPS/cPanel: crear base y usuario, subir a
   subdominio agenda.dominio.com.ar, importar SQL, forzar HTTPS con
   .htaccess, configurar el cron diario en cPanel.
4. TESTING.md: plan de prueba manual de la primera semana (casos:
   apertura automática, cierre por cada motivo, recordatorio de agendas,
   agenda incumplida por cron, acceso denegado sin rol).
