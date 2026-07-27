<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/SasApiClient.php';

final class Tickets
{
    /**
     * Llama a SAS (accion=atrasados) y por cada cliente con más de 7 días
     * de atraso: crea el contacto si no existe, y abre un ticket si
     * todavía no tiene uno abierto.
     *
     * No cachea nada acá — quien la llame desde una request web (Fase 2,
     * lista_tickets.php) decide si conviene envolver esta llamada con una
     * caché de sesión de ~10 min para no golpear a SAS en cada refresh.
     * El cron (cron_apertura_tickets.php) la llama directo.
     *
     * @return array{ok: bool, procesados?: int, error?: string}
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

        return ['ok' => true, 'procesados' => $procesados];
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
