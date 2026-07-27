<?php
declare(strict_types=1);

require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/../config/database.php';

final class Auth
{
    private const MAX_INTENTOS     = 5;
    private const BLOQUEO_MINUTOS  = 15;

    /** @return array{ok: bool, error?: string} */
    public static function login(string $usuario, string $password): array
    {
        $pdo = Database::pdo();

        $stmt = $pdo->prepare('SELECT * FROM usuarios_acceso WHERE usuario = ? AND activo = 1');
        $stmt->execute([$usuario]);
        $fila = $stmt->fetch();

        if (!$fila) {
            return ['ok' => false, 'error' => 'Usuario o contraseña incorrectos'];
        }

        if (!empty($fila['bloqueado_hasta']) && $fila['bloqueado_hasta'] > date('Y-m-d H:i:s')) {
            return ['ok' => false, 'error' => 'Usuario bloqueado temporalmente. Intentá de nuevo en unos minutos'];
        }

        if (!password_verify($password, $fila['password_hash'])) {
            self::registrarIntentoFallido($pdo, (int) $fila['id'], (int) $fila['intentos_fallidos']);

            return ['ok' => false, 'error' => 'Usuario o contraseña incorrectos'];
        }

        $pdo->prepare('UPDATE usuarios_acceso SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_login = NOW() WHERE id = ?')
            ->execute([$fila['id']]);

        // Regenerar el ID de sesión en cada login exitoso (mitiga session fixation).
        session_regenerate_id(true);

        $_SESSION['usuario'] = [
            'id'      => (int) $fila['id'],
            'nombre'  => $fila['nombre'],
            'usuario' => $fila['usuario'],
            'rol'     => $fila['rol'],
        ];

        // Se resetea acá (no en Csrf ni en el bootstrap) para que el banner
        // de recordatorio se re-expanda una vez por cada sesión nueva.
        $_SESSION['banner_expandido_mostrado'] = false;

        return ['ok' => true];
    }

    private static function registrarIntentoFallido(PDO $pdo, int $usuarioId, int $intentosActuales): void
    {
        $intentos       = $intentosActuales + 1;
        $bloqueadoHasta = $intentos >= self::MAX_INTENTOS
            ? date('Y-m-d H:i:s', strtotime('+' . self::BLOQUEO_MINUTOS . ' minutes'))
            : null;

        $pdo->prepare('UPDATE usuarios_acceso SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id = ?')
            ->execute([$intentos, $bloqueadoHasta, $usuarioId]);
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie('PHPSESSID', '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['usuario']);
    }

    public static function rol(): ?string
    {
        return $_SESSION['usuario']['rol'] ?? null;
    }

    public static function esAdmin(): bool
    {
        return self::rol() === 'administrador';
    }

    /** Corta la ejecución y redirige al login si no hay sesión. */
    public static function requiereLogin(): void
    {
        if (!self::check()) {
            redirect('index.php?p=login');
        }
    }

    /** Corta la ejecución con 403 si el usuario logueado no es administrador. */
    public static function requiereAdmin(): void
    {
        self::requiereLogin();

        if (!self::esAdmin()) {
            http_response_code(403);
            echo 'Acceso denegado: esta acción requiere rol administrador';
            exit;
        }
    }
}
