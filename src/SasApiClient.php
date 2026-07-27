<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/sas_api.php';

/**
 * Cliente HTTP hacia sas_side/api_readonly.php. Si SAS no responde a
 * tiempo o cae, devuelve ['ok' => false] en vez de lanzar una excepción:
 * las vistas que consuman esto deben degradar mostrando un aviso, sin
 * romper el resto de la pantalla.
 */
final class SasApiClient
{
    /** Clientes con MÁS DE 7 días de atraso (alimenta la apertura automática de tickets). */
    public static function atrasados(): array
    {
        return self::llamar('atrasados');
    }

    /** @return array{ok: bool, data?: mixed, error?: string} */
    private static function llamar(string $accion, array $params = []): array
    {
        $query = array_merge(['accion' => $accion], $params);
        $url   = SAS_API_URL . '?' . http_build_query($query);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => SAS_API_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => SAS_API_TIMEOUT,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . SAS_API_TOKEN],
        ]);

        $respuestaCruda = curl_exec($ch);
        $errorCurl       = curl_error($ch);
        curl_close($ch);

        if ($respuestaCruda === false || $errorCurl !== '') {
            return ['ok' => false, 'error' => 'SAS no respondió: ' . $errorCurl];
        }

        $decodificada = json_decode($respuestaCruda, true);
        if (!is_array($decodificada) || !array_key_exists('ok', $decodificada)) {
            return ['ok' => false, 'error' => 'Respuesta de SAS inválida'];
        }

        return $decodificada;
    }
}
