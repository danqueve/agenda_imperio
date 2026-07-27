<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Agendas.php';
require_once __DIR__ . '/Refinanciaciones.php';

final class Gestiones
{
    /**
     * Registra una gestión y, en la misma transacción, crea la agenda de
     * cobro (si $datos['agenda_cobro']) y/o el borrador de refinanciación
     * (si $datos['solicita_refinanciar']).
     *
     * @param array{
     *   ticket_id: int, usuario_id: int, canal: string, resultado: string,
     *   observacion?: ?string, agenda_cobro?: bool, fecha_agendada?: ?string,
     *   monto_esperado?: ?float, tipo_agenda?: ?string, solicita_refinanciar?: bool
     * } $datos
     * @return array{ok: bool, gestion_id?: int}
     */
    public static function registrar(array $datos): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('
                INSERT INTO gestiones (ticket_id, usuario_id, fecha_hora, canal, resultado, observacion, agenda_cobro, solicita_refinanciar)
                VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $datos['ticket_id'],
                $datos['usuario_id'],
                $datos['canal'],
                $datos['resultado'],
                $datos['observacion'] ?? null,
                !empty($datos['agenda_cobro']) ? 1 : 0,
                !empty($datos['solicita_refinanciar']) ? 1 : 0,
            ]);
            $gestionId = (int) $pdo->lastInsertId();

            if (!empty($datos['agenda_cobro'])) {
                Agendas::crear($pdo, [
                    'ticket_id'      => $datos['ticket_id'],
                    'gestion_id'     => $gestionId,
                    'usuario_id'     => $datos['usuario_id'],
                    'fecha_agendada' => $datos['fecha_agendada'],
                    'monto_esperado' => $datos['monto_esperado'],
                    'tipo'           => $datos['tipo_agenda'],
                ]);
            }

            if (!empty($datos['solicita_refinanciar'])) {
                Refinanciaciones::crearBorrador($pdo, [
                    'ticket_id'  => $datos['ticket_id'],
                    'gestion_id' => $gestionId,
                ]);
            }

            $pdo->commit();

            return ['ok' => true, 'gestion_id' => $gestionId];
        } catch (Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }
    }

    /** Timeline completo de un ticket (tab "Gestiones" de la ficha), más nuevo primero. */
    public static function timelinePorTicket(int $ticketId): array
    {
        $stmt = Database::pdo()->prepare('
            SELECT g.*, u.nombre AS usuario_nombre
            FROM gestiones g
            JOIN usuarios_acceso u ON u.id = g.usuario_id
            WHERE g.ticket_id = ?
            ORDER BY g.fecha_hora DESC
        ');
        $stmt->execute([$ticketId]);

        return $stmt->fetchAll();
    }

    /** Gestiones registradas hoy, para la tasa de contacto del dashboard (Fase 3). */
    public static function gestionesDeHoy(): array
    {
        $hoy = (new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Y-m-d');

        $stmt = Database::pdo()->prepare('
            SELECT g.*, u.nombre AS usuario_nombre
            FROM gestiones g
            JOIN usuarios_acceso u ON u.id = g.usuario_id
            WHERE DATE(g.fecha_hora) = ?
            ORDER BY g.fecha_hora DESC
        ');
        $stmt->execute([$hoy]);

        return $stmt->fetchAll();
    }

    /**
     * % de gestiones de los últimos $dias días que lograron contacto
     * (resultado='atendio'). Solo cuenta llamada/visita: whatsapp/sms
     * siempre quedan como 'enviado', nunca 'atendio', así que incluirlos
     * en el denominador subestimaría la tasa artificialmente.
     */
    public static function tasaContacto(int $dias = 7): float
    {
        $desde = (new DateTimeImmutable("-{$dias} days"))->format('Y-m-d 00:00:00');

        $stmt = Database::pdo()->prepare("
            SELECT
                SUM(CASE WHEN resultado = 'atendio' THEN 1 ELSE 0 END) AS atendidas,
                COUNT(*) AS total
            FROM gestiones
            WHERE fecha_hora >= ? AND canal IN ('llamada', 'visita')
        ");
        $stmt->execute([$desde]);
        $fila = $stmt->fetch();

        if (!$fila || (int) $fila['total'] === 0) {
            return 0.0;
        }

        return round(((int) $fila['atendidas'] / (int) $fila['total']) * 100, 1);
    }
}
