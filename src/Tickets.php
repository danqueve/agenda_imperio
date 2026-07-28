<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/SasApiClient.php';
require_once __DIR__ . '/Prioridad.php';
require_once __DIR__ . '/Refinanciaciones.php';

final class Tickets
{
    private const CACHE_SESSION_KEY = 'sas_sync_cache';
    private const CACHE_TTL_SEGUNDOS = 600;

    /**
     * Llama a SAS (accion=atrasados) y por cada cliente con más de 7 días
     * de atraso: crea el contacto si no existe, y abre un ticket si
     * todavía no tiene uno abierto.
     *
     * No cachea nada acá — sincronizarConCache() es quien envuelve esta
     * llamada con la caché de sesión de ~10 min para no golpear a SAS en
     * cada refresh de lista_tickets.php. El cron (cron_apertura_tickets.php)
     * llama a este método directo, sin caché (no hay sesión en CLI).
     *
     * @return array{ok: bool, procesados?: int, datos?: array, error?: string}
     */
    public static function sincronizarDesdeSAS(): array
    {
        $resp = SasApiClient::atrasados();
        if (!$resp['ok']) {
            return ['ok' => false, 'error' => $resp['error'] ?? 'No se pudo consultar SAS'];
        }

        $pdo        = Database::pdo();
        $procesados = 0;

        foreach ($resp['data'] as $atrasado) {
            $contactoId = self::upsertContacto($pdo, $atrasado);
            self::abrirSiNoExiste($pdo, $contactoId, (int) $atrasado['dias_atraso']);
            $procesados++;
        }

        return ['ok' => true, 'procesados' => $procesados, 'datos' => $resp['data']];
    }

    /**
     * Envoltorio de sincronizarDesdeSAS() con caché de sesión de 10 min,
     * pensado para lista_tickets.php (no golpear a SAS en cada refresh de
     * pantalla). Si SAS falló, no se cachea el fallo: el próximo request
     * reintenta enseguida en vez de quedar 10 min mostrando "SAS caído".
     */
    public static function sincronizarConCache(): array
    {
        $cache = $_SESSION[self::CACHE_SESSION_KEY] ?? null;
        if ($cache !== null && (time() - $cache['ts']) < self::CACHE_TTL_SEGUNDOS) {
            return $cache['resultado'];
        }

        $resultado = self::sincronizarDesdeSAS();
        if ($resultado['ok']) {
            $_SESSION[self::CACHE_SESSION_KEY] = ['ts' => time(), 'resultado' => $resultado];
        }

        return $resultado;
    }

    /**
     * Arma la lista de tickets abiertos lista para lista_tickets.php:
     * combina los datos propios (contacto, última gestión, flags de
     * agenda) con los datos en vivo de SAS (días de atraso, deuda) cuando
     * están disponibles, calcula el semáforo y ordena por prioridad.
     *
     * @return array{sync: array, tickets: array}
     */
    public static function listaParaVista(): array
    {
        $sync     = self::sincronizarConCache();
        $sasPorDni = [];
        if ($sync['ok'] && !empty($sync['datos'])) {
            foreach ($sync['datos'] as $fila) {
                $sasPorDni[$fila['dni']] = $fila;
            }
        }

        $pdo = Database::pdo();
        $hoy = (new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Y-m-d');

        $stmt = $pdo->prepare('
            SELECT
                t.id AS ticket_id, t.dias_atraso_apertura,
                c.dni, c.nombre_completo, c.telefono_principal, c.cobrador_nombre, c.zona,
                (SELECT MAX(g.fecha_hora) FROM gestiones g WHERE g.ticket_id = t.id) AS ultima_gestion,
                (SELECT COUNT(*) FROM gestiones g WHERE g.ticket_id = t.id) AS cantidad_gestiones,
                EXISTS(SELECT 1 FROM agendas_cobro a WHERE a.ticket_id = t.id AND a.estado = "pendiente" AND a.fecha_agendada = ?) AS tiene_agenda_hoy,
                EXISTS(SELECT 1 FROM agendas_cobro a WHERE a.ticket_id = t.id AND a.estado = "incumplida") AS tiene_agenda_incumplida,
                EXISTS(SELECT 1 FROM agendas_cobro a WHERE a.ticket_id = t.id AND a.estado = "pendiente" AND a.fecha_agendada > ?) AS tiene_agenda_futura
            FROM tickets t
            JOIN contactos_agenda c ON c.id = t.contacto_id
            WHERE t.estado = "abierto"
        ');
        $stmt->execute([$hoy, $hoy]);
        $filas = $stmt->fetchAll();

        $resultado = [];
        foreach ($filas as $fila) {
            $sas        = $sasPorDni[$fila['dni']] ?? null;
            $diasAtraso = $sas['dias_atraso'] ?? $fila['dias_atraso_apertura'];
            $deudaTotal = $sas['deuda_total'] ?? null;

            $prioridad = Prioridad::calcular(
                (int) $diasAtraso,
                $fila['ultima_gestion'],
                (bool) $fila['tiene_agenda_incumplida'],
                (bool) $fila['tiene_agenda_futura']
            );

            $resultado[] = [
                'ticket_id'        => (int) $fila['ticket_id'],
                'dni'              => $fila['dni'],
                'nombre_completo'  => $fila['nombre_completo'],
                'telefono'         => $fila['telefono_principal'],
                'cobrador_nombre'  => $fila['cobrador_nombre'],
                'zona'             => $fila['zona'],
                'dias_atraso'      => (int) $diasAtraso,
                'deuda_total'      => $deudaTotal !== null ? (float) $deudaTotal : null,
                'ultima_gestion'   => $fila['ultima_gestion'],
                'sin_gestionar'    => ((int) $fila['cantidad_gestiones']) === 0,
                'tiene_agenda_hoy' => (bool) $fila['tiene_agenda_hoy'],
                'prioridad'        => $prioridad,
            ];
        }

        $ordenPrioridad = ['rojo' => 0, 'naranja' => 1, 'amarillo' => 2, 'verde' => 3];
        usort($resultado, static function (array $a, array $b) use ($ordenPrioridad): int {
            return $ordenPrioridad[$a['prioridad']] <=> $ordenPrioridad[$b['prioridad']]
                ?: $b['dias_atraso'] <=> $a['dias_atraso'];
        });

        return ['sync' => $sync, 'tickets' => $resultado];
    }

    /**
     * Estadísticas para la solapa "Resumen" del dashboard. Reutiliza
     * listaParaVista() (misma sincronización cacheada) para el conteo de
     * abiertos y la lista de rojos sin gestionar (completa, sin cortar a
     * 10 - el corte a "top 10 por defecto, todos si hay filtro activo" se
     * hace en Alpine, ver views/dashboard.php), y agrega 2 consultas
     * chicas propias para lo que esa vista no calcula.
     */
    public static function estadisticasResumen(): array
    {
        $pdo          = Database::pdo();
        $hoy          = new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires'));
        $hoyStr       = $hoy->format('Y-m-d');
        $inicioSemana = $hoy->modify('monday this week')->format('Y-m-d 00:00:00');

        $abiertos = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE estado = 'abierto'")->fetchColumn();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM tickets WHERE DATE(fecha_apertura) = ?');
        $stmt->execute([$hoyStr]);
        $abiertosHoy = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT motivo_cierre, COUNT(*) AS cantidad
            FROM tickets
            WHERE estado = 'cerrado' AND fecha_cierre >= ?
            GROUP BY motivo_cierre
        ");
        $stmt->execute([$inicioSemana]);
        $cerradosPorMotivo = ['abono' => 0, 'retiro_producto' => 0, 'refinanciacion' => 0];
        foreach ($stmt->fetchAll() as $fila) {
            $cerradosPorMotivo[$fila['motivo_cierre']] = (int) $fila['cantidad'];
        }

        $lista             = self::listaParaVista();
        $rojosSinGestionar = array_values(array_filter($lista['tickets'], static function (array $t): bool {
            return $t['prioridad'] === 'rojo' && $t['sin_gestionar'];
        }));
        usort($rojosSinGestionar, static fn (array $a, array $b): int => $b['dias_atraso'] <=> $a['dias_atraso']);

        return [
            'sync'                => $lista['sync'],
            'abiertos'            => $abiertos,
            'abiertos_hoy'        => $abiertosHoy,
            'cerrados_semana'     => $cerradosPorMotivo,
            'rojos_sin_gestionar' => $rojosSinGestionar,
        ];
    }

    /**
     * Nombres de cobrador distintos y no vacíos, para poblar el <select>
     * de filtro en lista_tickets/dashboard/historial. Conjunto pequeño y
     * estable (sincronizado desde SAS) - no necesita paginar ni cachear.
     */
    public static function cobradoresDistintos(): array
    {
        return Database::pdo()
            ->query("SELECT DISTINCT cobrador_nombre FROM contactos_agenda WHERE cobrador_nombre IS NOT NULL AND cobrador_nombre <> '' ORDER BY cobrador_nombre")
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Ticket + datos del contacto, para la ficha del ticket. */
    public static function porId(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('
            SELECT
                t.id AS ticket_id, t.estado, t.fecha_apertura, t.dias_atraso_apertura,
                t.motivo_cierre, t.fecha_cierre, t.observacion_cierre, t.monto_cierre,
                c.id AS contacto_id, c.dni, c.nombre_completo, c.telefono_principal,
                c.telefono_alt, c.direccion_referencia, c.cobrador_nombre, c.zona
            FROM tickets t
            JOIN contactos_agenda c ON c.id = t.contacto_id
            WHERE t.id = ?
        ');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /**
     * Cierra un ticket abierto con uno de los 3 motivos. Para
     * 'refinanciacion' exige que ya exista una refinanciación en estado
     * 'aceptada' o 'procesada_en_sas' (ver plan §2 - ambos roles pueden
     * cerrar así desde la ficha, siempre que ya exista la refinanciación
     * aceptada).
     *
     * @return array{ok: bool, error?: string}
     */
    public static function cerrar(int $ticketId, string $motivo, ?string $observacion, ?float $monto, int $usuarioId): array
    {
        $pdo = Database::pdo();

        if ($motivo === 'refinanciacion' && !Refinanciaciones::aceptadaParaTicket($pdo, $ticketId)) {
            return [
                'ok'    => false,
                'error' => 'No hay una refinanciación aceptada para este ticket. Creala (o aceptala) primero.',
            ];
        }

        $stmt = $pdo->prepare("
            UPDATE tickets
            SET estado = 'cerrado', motivo_cierre = ?, fecha_cierre = NOW(), cerrado_por = ?,
                observacion_cierre = ?, monto_cierre = ?
            WHERE id = ? AND estado = 'abierto'
        ");
        $stmt->execute([$motivo, $usuarioId, $observacion, $monto, $ticketId]);

        if ($stmt->rowCount() === 0) {
            return ['ok' => false, 'error' => 'El ticket ya estaba cerrado o no existe.'];
        }

        return ['ok' => true];
    }

    /** Crea el contacto si el DNI todavía no existe; si ya existe, refresca los datos informativos. */
    private static function upsertContacto(PDO $pdo, array $atrasado): int
    {
        $stmt = $pdo->prepare('SELECT id FROM contactos_agenda WHERE dni = ?');
        $stmt->execute([$atrasado['dni']]);
        $existente = $stmt->fetchColumn();

        if ($existente) {
            $pdo->prepare('
                UPDATE contactos_agenda
                SET nombre_completo = ?, telefono_principal = ?, cobrador_nombre = ?
                WHERE id = ?
            ')->execute([
                $atrasado['nombre'],
                $atrasado['telefono'] ?? null,
                $atrasado['cobrador'] ?? null,
                $existente,
            ]);

            return (int) $existente;
        }

        $pdo->prepare('
            INSERT INTO contactos_agenda (dni, nombre_completo, telefono_principal, cobrador_nombre, zona, fecha_alta)
            VALUES (?, ?, ?, ?, ?, NOW())
        ')->execute([
            $atrasado['dni'],
            $atrasado['nombre'],
            $atrasado['telefono'] ?? null,
            $atrasado['cobrador'] ?? null,
            $atrasado['zona'] ?? 'tucuman',
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Abre un ticket para el contacto si no tiene uno abierto. Combina
     * dos capas (ver sql/01_schema.sql): la columna generada
     * `contacto_si_abierto` + UNIQUE INDEX es la garantía de motor; el
     * SELECT ... FOR UPDATE de acá es la capa de orquestación, para
     * devolver un resultado controlado en vez de una excepción cruda.
     *
     * Regla para evitar deadlocks: lockear siempre contactos_agenda
     * antes que tickets, nunca al revés.
     */
    public static function abrirSiNoExiste(PDO $pdo, int $contactoId, int $diasAtraso): ?int
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id FROM contactos_agenda WHERE id = ? FOR UPDATE');
            $stmt->execute([$contactoId]);
            if (!$stmt->fetch()) {
                $pdo->rollBack();

                return null;
            }

            $stmt = $pdo->prepare("SELECT id FROM tickets WHERE contacto_id = ? AND estado = 'abierto' LIMIT 1");
            $stmt->execute([$contactoId]);
            if ($existente = $stmt->fetchColumn()) {
                $pdo->commit();

                return (int) $existente;
            }

            $stmt = $pdo->prepare("
                INSERT INTO tickets (contacto_id, fecha_apertura, estado, dias_atraso_apertura)
                VALUES (?, NOW(), 'abierto', ?)
            ");
            $stmt->execute([$contactoId, $diasAtraso]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();

            return $id;
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                // El UNIQUE index de contacto_si_abierto frenó una carrera
                // residual que el lock no llegó a cubrir; no es un error real.
                return null;
            }

            throw $e;
        }
    }
}
