-- =========================================================
-- Agenda de Cobranza - Imperio Comercial
-- 03_sas_readonly.sql - Usuario MySQL de SOLO LECTURA sobre SAS
--
-- Este usuario lo usa sas_side/api_readonly.php, que vive y corre
-- del lado del hosting de SAS Imperio (base c2881399_credit), NO
-- del lado de la app Agenda. GRANT SELECT únicamente: la Agenda
-- jamás debe poder escribir en la base de créditos.
--
-- IMPORTANTE: cambiar la contraseña de más abajo antes de usar
-- esto fuera de WAMP local. En cPanel probablemente el host no
-- sea 'localhost' sino el que indique el panel (o '%' si el motor
-- de BD corre en un host separado del PHP).
-- =========================================================

CREATE USER IF NOT EXISTS 'agenda_readonly'@'localhost' IDENTIFIED BY 'CAMBIAR_esta_password_2026';

GRANT SELECT ON c2881399_credit.* TO 'agenda_readonly'@'localhost';

FLUSH PRIVILEGES;

-- Verificación rápida (ejecutar aparte, conectado como agenda_readonly):
--   SHOW GRANTS FOR CURRENT_USER();
--   SELECT 1 FROM information_schema.tables LIMIT 1;   -- debe funcionar
--   INSERT INTO cualquier_tabla ...                     -- debe fallar (error 1142)
