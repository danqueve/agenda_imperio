<?php
declare(strict_types=1);

require_once __DIR__ . '/Helpers.php';

final class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function campoOculto(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(self::token()) . '">';
    }

    public static function validar(?string $tokenRecibido): bool
    {
        return is_string($tokenRecibido)
            && $tokenRecibido !== ''
            && !empty($_SESSION[self::SESSION_KEY])
            && hash_equals($_SESSION[self::SESSION_KEY], $tokenRecibido);
    }

    /** Corta la ejecución con 403 si el POST actual no trae un token válido. */
    public static function requerirValido(): void
    {
        if (!self::validar($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo 'Token CSRF inválido o expirado. Volvé atrás y reintentá.';
            exit;
        }
    }
}
