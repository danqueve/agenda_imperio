-- =========================================================
-- Agenda de Cobranza - Imperio Comercial
-- 01_schema.sql - Base de datos y esquema completo (6 tablas)
-- =========================================================

CREATE DATABASE IF NOT EXISTS c2881399_agenda
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE c2881399_agenda;

-- ---------------------------------------------------------
-- 1. usuarios_acceso
-- Solo entran administrador y supervision. Los cobradores
-- NUNCA tienen fila acá (son un dato informativo en contactos_agenda).
-- ---------------------------------------------------------
CREATE TABLE usuarios_acceso (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre              VARCHAR(80)  NOT NULL,
  usuario             VARCHAR(40)  NOT NULL,
  password_hash       VARCHAR(255) NOT NULL,
  rol                 ENUM('administrador','supervision') NOT NULL,
  activo              TINYINT(1)   NOT NULL DEFAULT 1,
  ultimo_login        DATETIME     NULL,
  intentos_fallidos   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta     DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- 2. contactos_agenda
-- cobrador_nombre es informativo (viene de SAS), nunca un login.
-- ---------------------------------------------------------
CREATE TABLE contactos_agenda (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  dni                   VARCHAR(10)  NOT NULL,
  nombre_completo       VARCHAR(120) NOT NULL,
  telefono_principal    VARCHAR(20)  NULL,
  telefono_alt          VARCHAR(20)  NULL,
  direccion_referencia  VARCHAR(200) NULL,
  cobrador_nombre       VARCHAR(80)  NULL,
  zona                  ENUM('tucuman','santiago','catamarca') NOT NULL,
  activo                TINYINT(1)   NOT NULL DEFAULT 1,
  fecha_alta            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_dni (dni),
  KEY idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- 3. tickets  -- eje del sistema
--
-- contacto_si_abierto: columna generada que colapsa a NULL
-- cuando el ticket está cerrado. Un UNIQUE INDEX permite
-- múltiples NULL, así que esto garantiza a nivel de motor
-- que un contacto no puede tener dos tickets 'abierto' a la vez,
-- sin depender únicamente de la validación en PHP.
-- ---------------------------------------------------------
CREATE TABLE tickets (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  contacto_id            INT UNSIGNED NOT NULL,
  fecha_apertura         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estado                 ENUM('abierto','cerrado') NOT NULL DEFAULT 'abierto',
  motivo_cierre          ENUM('abono','retiro_producto','refinanciacion') NULL,
  fecha_cierre           DATETIME NULL,
  cerrado_por            INT UNSIGNED NULL,
  observacion_cierre     TEXT NULL,
  dias_atraso_apertura   INT UNSIGNED NOT NULL,
  monto_cierre           DECIMAL(12,2) NULL,
  contacto_si_abierto    INT UNSIGNED GENERATED ALWAYS AS (IF(estado = 'abierto', contacto_id, NULL)) STORED,
  PRIMARY KEY (id),
  UNIQUE KEY uk_un_solo_ticket_abierto (contacto_si_abierto),
  KEY idx_contacto_id (contacto_id),
  KEY idx_estado_fecha (estado, fecha_apertura),
  CONSTRAINT fk_tickets_contacto    FOREIGN KEY (contacto_id) REFERENCES contactos_agenda(id) ON DELETE RESTRICT,
  CONSTRAINT fk_tickets_cerrado_por FOREIGN KEY (cerrado_por) REFERENCES usuarios_acceso(id)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- 4. gestiones
-- ---------------------------------------------------------
CREATE TABLE gestiones (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id             INT UNSIGNED NOT NULL,
  usuario_id            INT UNSIGNED NOT NULL,
  fecha_hora            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  canal                 ENUM('llamada','whatsapp','visita','sms') NOT NULL,
  resultado             ENUM('atendio','no_atendio','ocupado','buzon','fuera_servicio','numero_erroneo','enviado') NOT NULL,
  observacion           TEXT NULL,
  agenda_cobro          TINYINT(1) NOT NULL DEFAULT 0,
  solicita_refinanciar  TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_ticket_fecha (ticket_id, fecha_hora),
  KEY idx_usuario (usuario_id),
  CONSTRAINT fk_gestiones_ticket  FOREIGN KEY (ticket_id)  REFERENCES tickets(id)         ON DELETE RESTRICT,
  CONSTRAINT fk_gestiones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_acceso(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- 5. agendas_cobro -- alimenta la solapa AGENDAS y el recordatorio del día
-- ---------------------------------------------------------
CREATE TABLE agendas_cobro (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id         INT UNSIGNED NOT NULL,
  gestion_id        INT UNSIGNED NULL,
  usuario_id        INT UNSIGNED NOT NULL,
  fecha_agendada    DATE NOT NULL,
  monto_esperado    DECIMAL(12,2) NOT NULL,
  tipo              ENUM('cliente_viene','se_visita','transferencia') NOT NULL,
  estado            ENUM('pendiente','cumplida','incumplida','reprogramada') NOT NULL DEFAULT 'pendiente',
  fecha_resolucion  DATETIME NULL,
  monto_real        DECIMAL(12,2) NULL,
  observacion       TEXT NULL,
  PRIMARY KEY (id),
  KEY idx_estado_fecha (estado, fecha_agendada),
  KEY idx_ticket (ticket_id),
  CONSTRAINT fk_agendas_ticket   FOREIGN KEY (ticket_id)  REFERENCES tickets(id)         ON DELETE RESTRICT,
  CONSTRAINT fk_agendas_gestion  FOREIGN KEY (gestion_id) REFERENCES gestiones(id)       ON DELETE RESTRICT,
  CONSTRAINT fk_agendas_usuario  FOREIGN KEY (usuario_id) REFERENCES usuarios_acceso(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- 6. refinanciaciones
-- Al pasar a 'procesada_en_sas' se cierra el ticket con motivo
-- 'refinanciacion' en la misma transacción (ver src/Refinanciaciones.php).
-- ---------------------------------------------------------
CREATE TABLE refinanciaciones (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id           INT UNSIGNED NOT NULL,
  gestion_id          INT UNSIGNED NULL,
  propuesta_detalle   TEXT NULL,
  nueva_cadencia      ENUM('semanal','quincenal','mensual') NULL,
  nueva_cant_cuotas   INT UNSIGNED NULL,
  nuevo_monto_cuota   DECIMAL(12,2) NULL,
  estado              ENUM('borrador','ofrecida','aceptada','rechazada','procesada_en_sas') NOT NULL DEFAULT 'borrador',
  fecha_propuesta     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_respuesta     DATETIME NULL,
  notas               TEXT NULL,
  PRIMARY KEY (id),
  KEY idx_ticket (ticket_id),
  KEY idx_estado (estado),
  CONSTRAINT fk_refi_ticket  FOREIGN KEY (ticket_id)  REFERENCES tickets(id)   ON DELETE RESTRICT,
  CONSTRAINT fk_refi_gestion FOREIGN KEY (gestion_id) REFERENCES gestiones(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
