<?php
declare(strict_types=1);

/**
 * Credenciales del usuario MySQL de solo lectura (ver sql/03_sas_readonly.sql)
 * y token compartido con config/sas_api.php del lado de la Agenda.
 *
 * Este archivo vive en el hosting de SAS Imperio, junto a api_readonly.php
 * (NO se despliega dentro de la carpeta de la Agenda de Cobranza).
 */

const SAS_DB_HOST    = 'localhost';
const SAS_DB_NAME    = 'c2881399_credit';
const SAS_DB_USER    = 'agenda_readonly';
const SAS_DB_PASS    = 'CAMBIAR_esta_password_2026'; // debe coincidir con sql/03_sas_readonly.sql
const SAS_DB_CHARSET = 'utf8mb4';

// Debe coincidir con SAS_API_TOKEN en config/sas_api.php (lado Agenda)
const SAS_API_TOKEN_VALIDO = 'CAMBIAR_este_token_compartido_2026';
