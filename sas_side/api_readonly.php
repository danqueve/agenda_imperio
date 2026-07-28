<?php
declare(strict_types=1);

/**
 * API de solo lectura hacia SAS Imperio (c2881399_credit).
 *
 * Vive y se despliega del lado del hosting de SAS, NO del lado de la
 * Agenda de Cobranza. La Agenda solo la consume vía HTTP (ver
 * src/SasApiClient.php) y nunca escribe nada acá.
 *
 * Esquema real confirmado contra c2881399_credit (prefijo ic_):
 * ic_clientes (dni, nombres, apellidos, telefono, zona libre, estado
 * ACTIVO/INACTIVO), ic_creditos (estado EN_CURSO/FINALIZADO/MOROSO/
 * CANCELADO, cobrador_id -> ic_usuarios), ic_cuotas (estado PENDIENTE/
 * VENCIDA/PARCIAL/PAGADA/CAP_PAGADA/CANCELADA - la columna dias_atraso
 * existe pero no se mantiene actualizada, se calcula acá con DATEDIFF),
 * ic_usuarios (rol='cobrador' hace de "cobrador"; no hay tabla
 * ic_cobradores separada), ic_pagos_confirmados (revertido=0 para
 * pagos vigentes).
 */

require_once __DIR__ . '/config_readonly.php';

header('Content-Type: application/json; charset=utf-8');

// Gonzalo Carrazan (ic_usuarios.id = 11) nunca debe aparecer en la Agenda
// por regla de negocio, aunque en SAS figura activo=1 (confirmado en vivo
// al inspeccionar la base). No se toca su estado en SAS: solo se excluye
// acá, del lado de lectura.
const SAS_COBRADOR_EXCLUIDO_ID = 11;

function sas_responder(bool $ok, $data = null, ?string $error = null, int $httpCode = 200): never
{
    http_response_code($httpCode);
    echo json_encode(['ok' => $ok, 'data' => $data, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

function sas_obtener_header_authorization(): ?string
{
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                return $value;
            }
        }
    }
    // Fallback para hosting compartido / PHP-FPM, donde getallheaders()
    // o HTTP_AUTHORIZATION no siempre llegan sin CGIPassAuth (ver DEPLOY.md).
    foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
        if (!empty($_SERVER[$key])) {
            return $_SERVER[$key];
        }
    }
    return null;
}

/**
 * ic_clientes.zona es texto libre ("Zona 1", "Bella Vista", "Famailla",
 * "Tafi del Valle", "Sgo", "Cat", "Duros", "Capital", vacío, etc. -
 * confirmado en vivo). Se devuelve tal cual viene (recortada), sin
 * resumir a buckets - la Agenda ya no fuerza un ENUM propio de zona.
 */
function sas_normalizar_zona(?string $zonaSas): string
{
    $normalizada = trim((string) $zonaSas);

    return $normalizada !== '' ? $normalizada : 'Sin zona';
}

// ---- Autenticación por Bearer token ----
$authHeader = sas_obtener_header_authorization();
if (
    $authHeader === null
    || !preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $m)
    || !hash_equals(SAS_API_TOKEN_VALIDO, $m[1])
) {
    sas_responder(false, null, 'No autorizado', 401);
}

// ---- Conexión de solo lectura ----
try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', SAS_DB_HOST, SAS_DB_NAME, SAS_DB_CHARSET);
    $pdo = new PDO($dsn, SAS_DB_USER, SAS_DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    sas_responder(false, null, 'No se pudo conectar a SAS', 500);
}

/**
 * Clientes con MÁS DE 7 días de atraso. Alimenta la apertura
 * automática de tickets (ver src/Tickets.php::sincronizarDesdeSAS()).
 * "días de atraso" = días desde la cuota vencida más antigua impaga
 * (VENCIDA o PARCIAL, con fecha_vencimiento ya pasada).
 *
 * Se excluyen clientes sin DNI cargado en SAS (16 casos activos al
 * momento de escribir esto) porque contactos_agenda.dni es NOT NULL:
 * no pueden entrar a la Agenda hasta que se les cargue el DNI en SAS.
 */
function sas_accion_atrasados(PDO $pdo): void
{
    $sql = '
        SELECT
            cli.dni                                             AS dni,
            CONCAT(cli.nombres, \' \', cli.apellidos)            AS nombre,
            cli.telefono                                        AS telefono,
            CONCAT(usr.nombre, \' \', usr.apellido)              AS cobrador,
            DATEDIFF(CURDATE(), MIN(cuo.fecha_vencimiento))     AS dias_atraso,
            COUNT(cuo.id)                                        AS cuotas_vencidas,
            SUM(cuo.monto_cuota + COALESCE(cuo.monto_mora, 0) - COALESCE(cuo.saldo_pagado, 0)) AS deuda_total,
            cli.zona                                             AS zona_sas
        FROM ic_clientes cli
        JOIN ic_creditos cre ON cre.cliente_id = cli.id
        JOIN ic_cuotas cuo   ON cuo.credito_id = cre.id
        LEFT JOIN ic_usuarios usr ON usr.id = cre.cobrador_id
        WHERE cuo.estado IN (\'VENCIDA\', \'PARCIAL\')
          AND cuo.fecha_vencimiento < CURDATE()
          AND cre.estado IN (\'EN_CURSO\', \'MOROSO\')
          AND cli.estado <> \'INACTIVO\'
          AND cli.dni IS NOT NULL AND cli.dni <> \'\'
          AND (cre.cobrador_id IS NULL OR cre.cobrador_id <> ' . SAS_COBRADOR_EXCLUIDO_ID . ')
        GROUP BY cli.id, cli.dni, cli.nombres, cli.apellidos, cli.telefono, usr.nombre, usr.apellido, cli.zona
        HAVING dias_atraso > 7
        ORDER BY dias_atraso DESC
    ';

    $stmt = $pdo->query($sql);
    $filas = $stmt->fetchAll();

    foreach ($filas as &$fila) {
        $fila['zona'] = sas_normalizar_zona($fila['zona_sas']);
        unset($fila['zona_sas']);
    }
    unset($fila);

    sas_responder(true, $filas);
}

/**
 * Datos del cliente y créditos en curso/con mora.
 */
function sas_accion_cliente(PDO $pdo, string $dni): void
{
    if ($dni === '') {
        sas_responder(false, null, 'Falta dni', 400);
    }

    $stmtCliente = $pdo->prepare('
        SELECT
            dni,
            CONCAT(nombres, \' \', apellidos) AS nombre,
            telefono,
            telefono_alt,
            direccion,
            zona,
            estado
        FROM ic_clientes
        WHERE dni = ?
    ');
    $stmtCliente->execute([$dni]);
    $cliente = $stmtCliente->fetch();

    if (!$cliente) {
        sas_responder(false, null, 'Cliente no encontrado', 404);
    }

    $cliente['zona'] = sas_normalizar_zona($cliente['zona']);

    $stmtCreditos = $pdo->prepare("
        SELECT
            cre.id,
            cre.articulo_desc,
            cre.monto_total,
            cre.cant_cuotas,
            cre.monto_cuota,
            cre.frecuencia,
            cre.estado,
            cre.fecha_alta
        FROM ic_creditos cre
        JOIN ic_clientes cli ON cli.id = cre.cliente_id
        WHERE cli.dni = ? AND cre.estado IN ('EN_CURSO', 'MOROSO')
        ORDER BY cre.fecha_alta DESC
    ");
    $stmtCreditos->execute([$dni]);

    sas_responder(true, [
        'cliente'  => $cliente,
        'creditos' => $stmtCreditos->fetchAll(),
    ]);
}

/**
 * Deuda total (todo lo no pagado, vencido o no), cuotas vencidas,
 * días de atraso y próximo vencimiento, sobre créditos EN_CURSO/MOROSO.
 */
function sas_accion_deuda(PDO $pdo, string $dni): void
{
    if ($dni === '') {
        sas_responder(false, null, 'Falta dni', 400);
    }

    $sql = "
        SELECT
            SUM(CASE WHEN cuo.estado IN ('VENCIDA', 'PARCIAL', 'PENDIENTE')
                     THEN cuo.monto_cuota + COALESCE(cuo.monto_mora, 0) - COALESCE(cuo.saldo_pagado, 0)
                     ELSE 0 END)                                                          AS deuda_total,
            SUM(CASE WHEN cuo.estado IN ('VENCIDA', 'PARCIAL') AND cuo.fecha_vencimiento < CURDATE()
                     THEN 1 ELSE 0 END)                                                    AS cuotas_vencidas,
            DATEDIFF(CURDATE(), MIN(CASE WHEN cuo.estado IN ('VENCIDA', 'PARCIAL') AND cuo.fecha_vencimiento < CURDATE()
                                          THEN cuo.fecha_vencimiento END))                  AS dias_atraso,
            MIN(CASE WHEN cuo.estado IN ('PENDIENTE', 'VENCIDA', 'PARCIAL')
                     THEN cuo.fecha_vencimiento END)                                        AS proximo_vencimiento
        FROM ic_cuotas cuo
        JOIN ic_creditos cre ON cre.id = cuo.credito_id
        JOIN ic_clientes cli ON cli.id = cre.cliente_id
        WHERE cli.dni = ? AND cre.estado IN ('EN_CURSO', 'MOROSO')
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dni]);
    $fila = $stmt->fetch();

    if (!$fila || $fila['deuda_total'] === null) {
        sas_responder(true, [
            'deuda_total'         => 0,
            'cuotas_vencidas'     => 0,
            'dias_atraso'         => 0,
            'proximo_vencimiento' => null,
        ]);
    }

    sas_responder(true, $fila);
}

/**
 * Últimos 10 pagos confirmados (no revertidos) del cliente.
 */
function sas_accion_pagos(PDO $pdo, string $dni): void
{
    if ($dni === '') {
        sas_responder(false, null, 'Falta dni', 400);
    }

    $sql = '
        SELECT
            pc.fecha_pago,
            pc.monto_total,
            pc.monto_efectivo,
            pc.monto_transferencia
        FROM ic_pagos_confirmados pc
        JOIN ic_cuotas cuo   ON cuo.id = pc.cuota_id
        JOIN ic_creditos cre ON cre.id = cuo.credito_id
        JOIN ic_clientes cli ON cli.id = cre.cliente_id
        WHERE cli.dni = ? AND pc.revertido = 0
        ORDER BY pc.fecha_pago DESC
        LIMIT 10
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dni]);
    sas_responder(true, $stmt->fetchAll());
}

// ---- Router de acciones ----
$accion = $_GET['accion'] ?? '';
$dni    = (string) ($_GET['dni'] ?? '');

match ($accion) {
    'atrasados' => sas_accion_atrasados($pdo),
    'cliente'   => sas_accion_cliente($pdo, $dni),
    'deuda'     => sas_accion_deuda($pdo, $dni),
    'pagos'     => sas_accion_pagos($pdo, $dni),
    default     => sas_responder(false, null, 'accion desconocida', 400),
};
