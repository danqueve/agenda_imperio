<?php
declare(strict_types=1);

/**
 * Config del cliente hacia la API de solo lectura de SAS Imperio
 * (sas_side/api_readonly.php). El token debe coincidir con el que
 * valida sas_side/config_readonly.php del lado de SAS.
 */

const SAS_API_URL     = 'http://localhost/agenda/sas_side/api_readonly.php'; // WAMP local. En producción: mover sas_side/ al hosting de SAS y AJUSTAR esta URL
const SAS_API_TOKEN   = 'CAMBIAR_este_token_compartido_2026';        // AJUSTAR: generar uno propio y copiarlo en sas_side/config_readonly.php
const SAS_API_TIMEOUT = 8; // segundos, ver src/SasApiClient.php
