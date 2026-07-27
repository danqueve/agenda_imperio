-- =========================================================
-- Agenda de Cobranza - Imperio Comercial
-- 02_usuarios.sql - Usuarios iniciales
--
-- Password inicial para ambos usuarios: "cambiar123"
-- Hash generado con PHP 8.3 (password_hash con PASSWORD_DEFAULT).
-- Para regenerar el hash (por ejemplo con otra contraseña):
--   php -r "echo password_hash('cambiar123', PASSWORD_DEFAULT), PHP_EOL;"
-- =========================================================

USE c2881399_agenda;

INSERT INTO usuarios_acceso (nombre, usuario, password_hash, rol, activo) VALUES
('Alejandro', 'alejandro', '$2y$10$PZT3YTGX.RXgG7fgqjt9y.LknuuHYaIJ39GAI5CqKGV4zP9ooqLWa', 'administrador', 1),
('Supervision 1', 'supervision1', '$2y$10$PZT3YTGX.RXgG7fgqjt9y.LknuuHYaIJ39GAI5CqKGV4zP9ooqLWa', 'supervision', 1);
