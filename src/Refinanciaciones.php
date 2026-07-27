<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

final class Refinanciaciones
{
    public static function crearBorrador(PDO $pdo, array $datos): int
    {
        $stmt = $pdo->prepare('
            INSERT INTO refinanciaciones (ticket_id, gestion_id, fecha_propuesta)
            VALUES (?, ?, NOW())
        ');
        $stmt->execute([$datos['ticket_id'], $datos['gestion_id'] ?? null]);

        return (int) $pdo->lastInsertId();
    }

    /** Todas las propuestas de un ticket (tab "Agendas" de la ficha). */
    public static function paraTicket(int $ticketId): array
    {
        $stmt = Database::pdo()->prepare('
            SELECT * FROM refinanciaciones WHERE ticket_id = ? ORDER BY fecha_propuesta DESC
        ');
        $stmt->execute([$ticketId]);

        return $stmt->fetchAll();
    }

    /** La refinanciación aceptada (o ya procesada en SAS) más reciente del ticket, si existe. */
    public static function aceptadaParaTicket(PDO $pdo, int $ticketId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT * FROM refinanciaciones
            WHERE ticket_id = ? AND estado IN ('aceptada', 'procesada_en_sas')
            ORDER BY fecha_propuesta DESC
            LIMIT 1
        ");
        $stmt->execute([$ticketId]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /**
     * Todas las propuestas activas (no rechazadas) agrupadas por estado,
     * para el kanban del dashboard.
     *
     * @return array{borrador: array, ofrecida: array, aceptada: array, procesada_en_sas: array}
     */
    public static function porEstado(): array
    {
        $stmt = Database::pdo()->query("
            SELECT r.*, c.nombre_completo
            FROM refinanciaciones r
            JOIN tickets t ON t.id = r.ticket_id
            JOIN contactos_agenda c ON c.id = t.contacto_id
            WHERE r.estado != 'rechazada'
            ORDER BY r.fecha_propuesta DESC
        ");

        $columnas = ['borrador' => [], 'ofrecida' => [], 'aceptada' => [], 'procesada_en_sas' => []];
        foreach ($stmt->fetchAll() as $fila) {
            $columnas[$fila['estado']][] = $fila;
        }

        return $columnas;
    }

    public static function marcarOfrecida(int $id): array
    {
        $stmt = Database::pdo()->prepare("UPDATE refinanciaciones SET estado = 'ofrecida' WHERE id = ? AND estado = 'borrador'");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0 ? ['ok' => true] : ['ok' => false, 'error' => 'No se pudo marcar como ofrecida'];
    }

    public static function marcarAceptada(int $id): array
    {
        $stmt = Database::pdo()->prepare("UPDATE refinanciaciones SET estado = 'aceptada', fecha_respuesta = NOW() WHERE id = ? AND estado = 'ofrecida'");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0 ? ['ok' => true] : ['ok' => false, 'error' => 'No se pudo marcar como aceptada'];
    }

    public static function marcarRechazada(int $id): array
    {
        $stmt = Database::pdo()->prepare("
            UPDATE refinanciaciones SET estado = 'rechazada', fecha_respuesta = NOW()
            WHERE id = ? AND estado IN ('borrador', 'ofrecida')
        ");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0 ? ['ok' => true] : ['ok' => false, 'error' => 'No se pudo rechazar'];
    }

    /**
     * Marca la refinanciación como procesada en SAS y cierra el ticket con
     * motivo 'refinanciacion' en la MISMA transacción (solo admin - se
     * verifica en el router, no acá). No reutiliza Tickets::cerrar()
     * porque ese método maneja su propia transacción: acá necesitamos que
     * ambas escrituras (refinanciación + ticket) sean atómicas juntas.
     */
    public static function marcarProcesadaEnSas(int $id, int $usuarioId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("SELECT * FROM refinanciaciones WHERE id = ? AND estado = 'aceptada' FOR UPDATE");
            $stmt->execute([$id]);
            $refi = $stmt->fetch();

            if (!$refi) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'La refinanciación debe estar aceptada antes de procesarla en SAS'];
            }

            $pdo->prepare("UPDATE refinanciaciones SET estado = 'procesada_en_sas' WHERE id = ?")->execute([$id]);

            $stmt = $pdo->prepare("
                UPDATE tickets
                SET estado = 'cerrado', motivo_cierre = 'refinanciacion', fecha_cierre = NOW(), cerrado_por = ?
                WHERE id = ? AND estado = 'abierto'
            ");
            $stmt->execute([$usuarioId, $refi['ticket_id']]);

            $pdo->commit();

            return ['ok' => true];
        } catch (Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }
    }
}
