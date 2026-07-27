<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

final class Agendas
{
    public static function crear(PDO $pdo, array $datos): int
    {
        $stmt = $pdo->prepare('
            INSERT INTO agendas_cobro (ticket_id, gestion_id, usuario_id, fecha_agendada, monto_esperado, tipo)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $datos['ticket_id'],
            $datos['gestion_id'] ?? null,
            $datos['usuario_id'],
            $datos['fecha_agendada'],
            $datos['monto_esperado'],
            $datos['tipo'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** Todas las agendas de un ticket puntual (tab "Agendas" de la ficha + variables de WhatsApp). */
    public static function paraTicket(int $ticketId): array
    {
        $stmt = Database::pdo()->prepare('
            SELECT * FROM agendas_cobro WHERE ticket_id = ? ORDER BY fecha_agendada DESC
        ');
        $stmt->execute([$ticketId]);

        return $stmt->fetchAll();
    }

    /**
     * Agendas pendientes con fecha_agendada = HOY (solapa Agendas + banner
     * de recordatorio). Solo de tickets todavía abiertos: si el ticket ya
     * se cerró por otro motivo (p.ej. pagó todo en efectivo en el local),
     * cualquier agenda pendiente que le quedaba deja de ser un pendiente
     * operativo real y no debe seguir apareciendo acá.
     */
    public static function deHoy(): array
    {
        $stmt = Database::pdo()->prepare(
            self::sqlBase() . ' WHERE a.estado = "pendiente" AND a.fecha_agendada = ? AND t.estado = "abierto" ORDER BY a.fecha_agendada'
        );
        $stmt->execute([self::hoy()]);

        return $stmt->fetchAll();
    }

    /** Agendas pendientes de hoy hasta el domingo de esta semana (mismo criterio de ticket abierto que deHoy()). */
    public static function estaSemana(): array
    {
        $hoy       = self::hoy();
        $finSemana = (new DateTimeImmutable($hoy))->modify('sunday this week')->format('Y-m-d');

        $stmt = Database::pdo()->prepare(
            self::sqlBase() . ' WHERE a.estado = "pendiente" AND a.fecha_agendada BETWEEN ? AND ? AND t.estado = "abierto" ORDER BY a.fecha_agendada'
        );
        $stmt->execute([$hoy, $finSemana]);

        return $stmt->fetchAll();
    }

    /**
     * Vencidas sin resolver: 'incumplida' (ya marcadas por el cron) más
     * 'pendiente' con fecha ya pasada (todavía no pasó cron_diario.php).
     * Mismo criterio de ticket abierto que deHoy().
     */
    public static function vencidas(): array
    {
        $stmt = Database::pdo()->prepare(
            self::sqlBase() . " WHERE (a.estado = 'incumplida' OR (a.estado = 'pendiente' AND a.fecha_agendada < ?)) AND t.estado = 'abierto' ORDER BY a.fecha_agendada"
        );
        $stmt->execute([self::hoy()]);

        return $stmt->fetchAll();
    }

    /** Todas las agendas pendientes de tickets abiertos (sin filtro de fecha). */
    public static function pendientes(): array
    {
        return Database::pdo()->query(self::sqlBase() . " WHERE a.estado = 'pendiente' AND t.estado = 'abierto' ORDER BY a.fecha_agendada")->fetchAll();
    }

    /** Chequeo rápido para el recordatorio global - ver banner_recordatorio.php. */
    public static function hayAgendasHoySinResolver(): bool
    {
        $stmt = Database::pdo()->prepare("
            SELECT 1
            FROM agendas_cobro a
            JOIN tickets t ON t.id = a.ticket_id
            WHERE a.estado = 'pendiente' AND a.fecha_agendada = ? AND t.estado = 'abierto'
            LIMIT 1
        ");
        $stmt->execute([self::hoy()]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Marca una agenda pendiente como cumplida o incumplida.
     *
     * @return array{ok: bool, ticket_id?: int, error?: string}
     */
    public static function resolver(int $id, string $estado, ?float $montoReal, ?string $observacion): array
    {
        if (!in_array($estado, ['cumplida', 'incumplida'], true)) {
            return ['ok' => false, 'error' => 'Estado inválido'];
        }

        $pdo  = Database::pdo();
        $stmt = $pdo->prepare("
            UPDATE agendas_cobro
            SET estado = ?, fecha_resolucion = NOW(), monto_real = ?, observacion = ?
            WHERE id = ? AND estado = 'pendiente'
        ");
        $stmt->execute([$estado, $montoReal, $observacion, $id]);

        if ($stmt->rowCount() === 0) {
            return ['ok' => false, 'error' => 'La agenda ya estaba resuelta o no existe'];
        }

        $stmt = $pdo->prepare('SELECT ticket_id FROM agendas_cobro WHERE id = ?');
        $stmt->execute([$id]);

        return ['ok' => true, 'ticket_id' => (int) $stmt->fetchColumn()];
    }

    /**
     * Reprograma: marca la agenda actual como 'reprogramada' y crea una
     * nueva 'pendiente' con la fecha nueva, atribuida a quien reprograma
     * (no a quien la había creado originalmente).
     *
     * @return array{ok: bool, nuevo_id?: int, error?: string}
     */
    public static function reprogramar(int $id, string $nuevaFecha, int $usuarioId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("SELECT * FROM agendas_cobro WHERE id = ? AND estado = 'pendiente' FOR UPDATE");
            $stmt->execute([$id]);
            $original = $stmt->fetch();

            if (!$original) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'La agenda ya estaba resuelta o no existe'];
            }

            $pdo->prepare("UPDATE agendas_cobro SET estado = 'reprogramada', fecha_resolucion = NOW() WHERE id = ?")->execute([$id]);

            $nuevoId = self::crear($pdo, [
                'ticket_id'      => $original['ticket_id'],
                'gestion_id'     => null,
                'usuario_id'     => $usuarioId,
                'fecha_agendada' => $nuevaFecha,
                'monto_esperado' => $original['monto_esperado'],
                'tipo'           => $original['tipo'],
            ]);

            $pdo->commit();

            return ['ok' => true, 'nuevo_id' => $nuevoId];
        } catch (Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }
    }

    private static function sqlBase(): string
    {
        // a.* ya trae ticket_id (FK de agendas_cobro) - no hace falta repetirlo desde t.
        return '
            SELECT a.*, c.nombre_completo, c.telefono_principal
            FROM agendas_cobro a
            JOIN tickets t ON t.id = a.ticket_id
            JOIN contactos_agenda c ON c.id = t.contacto_id
        ';
    }

    private static function hoy(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Y-m-d');
    }
}
