-- =========================================================
-- Agenda de Cobranza - Imperio Comercial
-- 04_zona_texto_libre.sql - contactos_agenda.zona pasa de ENUM
-- de 3 valores (tucuman/santiago/catamarca) a texto libre, para
-- reflejar la zona real tal cual la tiene SAS (Bella Vista, Zona 1,
-- Zona 2, Famailla, etc.) en vez de un resumen a 3 buckets.
--
-- Correr UNA vez contra una base ya creada con el 01_schema.sql
-- viejo (local WAMP o el VPS de producción). Las instalaciones
-- nuevas ya no necesitan esto: 01_schema.sql ya trae el tipo
-- correcto.
-- =========================================================

ALTER TABLE contactos_agenda
  MODIFY zona VARCHAR(80) NOT NULL DEFAULT '';
